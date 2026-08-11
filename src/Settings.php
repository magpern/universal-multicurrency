<?php
/**
 * Plugin settings store.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Checkout\CheckoutSettings;
use UMC\Display\SwitcherSettings;
use UMC\Geo\GeoDetectionSettings;
use UMC\Rates\RateResolver;

/**
 * Sole owner of the `umc_settings` option.
 *
 * `defaults()` and `sanitize()` are pure and WordPress-free (unit-testable
 * without a bootstrap). Instance methods read/write the option, unless the
 * store is constructed with in-memory data (used by tests and by callers that
 * already hold a configuration array). Sanitization is forgiving: it never
 * throws, cleaning or dropping invalid input so persistence always succeeds.
 *
 * The base currency is intentionally NOT stored here — it lives in the
 * WooCommerce `woocommerce_currency` option and is never duplicated.
 */
final class Settings {

	public const OPTION = 'umc_settings';

	public const SCHEMA_VERSION = 6;

	public const RATE_MODE_MANUAL = 'manual';

	public const RATE_MODE_AUTOMATIC = 'automatic';

	public const DEFAULT_RATE_PROVIDER = 'frankfurter';

	public const DEFAULT_RATE_INTERVAL = 'P1D';

	public const DEFAULT_RATE_MAX_AGE_HOURS = 48;

	/**
	 * Optional upgrade runner override (tests only).
	 *
	 * @var SettingsUpgrader|null
	 */
	private static ?SettingsUpgrader $upgrader = null;

	/**
	 * Cached, sanitized settings. Null until first load.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data = null;

	/**
	 * Constructs the store, optionally from in-memory data instead of the option.
	 *
	 * @param array<string, mixed>|null $data Optional in-memory settings. When
	 *                                         provided, the option is never read.
	 */
	public function __construct( ?array $data = null ) {
		if ( null !== $data ) {
			$this->data = self::sanitize( $data );
		}
	}

	/**
	 * Overrides the upgrade runner (tests only).
	 *
	 * @param SettingsUpgrader|null $upgrader Runner to inject, or null to restore production default.
	 */
	public static function set_upgrader( ?SettingsUpgrader $upgrader ): void {
		self::$upgrader = $upgrader;
	}

	/**
	 * Resets the upgrade runner override (tests only).
	 */
	public static function reset_upgrader(): void {
		self::$upgrader = null;
	}

	/**
	 * The default settings structure.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema_version'       => self::SCHEMA_VERSION,
			'rate_mode'            => self::RATE_MODE_MANUAL,
			'rate_provider'        => self::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => self::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => self::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => array(),
			'display'              => SwitcherSettings::default_array(),
			'checkout'             => CheckoutSettings::default_array(),
			'geo'                  => GeoDetectionSettings::default_array(),
		);
	}

	/**
	 * Cleans arbitrary input into a valid, persistable settings structure.
	 *
	 * Pure and WordPress-free. Never throws.
	 *
	 * @param mixed $raw Arbitrary input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $raw ): array {
		$clean = self::defaults();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		$clean['rate_mode']            = self::sanitize_rate_mode( $raw['rate_mode'] ?? self::RATE_MODE_MANUAL, self::RATE_MODE_MANUAL );
		$clean['rate_provider']        = self::sanitize_provider( $raw['rate_provider'] ?? self::DEFAULT_RATE_PROVIDER );
		$clean['rate_update_interval'] = self::sanitize_interval( $raw['rate_update_interval'] ?? self::DEFAULT_RATE_INTERVAL );
		$clean['rate_max_age_hours']   = self::sanitize_max_age_hours( $raw['rate_max_age_hours'] ?? self::DEFAULT_RATE_MAX_AGE_HOURS );

		if ( ! isset( $raw['currencies'] ) || ! is_array( $raw['currencies'] ) ) {
			return $clean;
		}

		foreach ( $raw['currencies'] as $code => $config ) {
			$code = is_string( $code ) ? strtoupper( trim( $code ) ) : '';

			if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) || ! is_array( $config ) ) {
				continue;
			}

			$clean['currencies'][ $code ] = self::sanitize_currency( $config );
		}

		$clean['display']  = self::sanitize_display( $raw['display'] ?? null );
		$clean['checkout'] = self::sanitize_checkout( $raw['checkout'] ?? null );
		$clean['geo']      = self::sanitize_geo( $raw['geo'] ?? null );

		return $clean;
	}

	/**
	 * Sanitizes the Display switcher settings subtree.
	 *
	 * @param mixed $raw Raw display settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_display( mixed $raw ): array {
		return SwitcherSettings::sanitize_raw( $raw );
	}

	/**
	 * Sanitizes the checkout policy settings subtree.
	 *
	 * @param mixed $raw Raw checkout settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_checkout( mixed $raw ): array {
		return CheckoutSettings::sanitize_raw( $raw );
	}

	/**
	 * Sanitizes the Geo Detection settings subtree.
	 *
	 * @param mixed $raw Raw geo settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_geo( mixed $raw ): array {
		return GeoDetectionSettings::sanitize_raw( $raw );
	}

	/**
	 * Sanitizes a single currency configuration row.
	 *
	 * @param array<string, mixed> $config Raw row.
	 * @return array<string, mixed>
	 */
	private static function sanitize_currency( array $config ): array {
		$decimals = ( isset( $config['decimals'] ) && is_numeric( $config['decimals'] ) )
			? (int) $config['decimals']
			: Currency::DEFAULT_DECIMALS;

		if ( $decimals < 0 || $decimals > Currency::MAX_DECIMALS ) {
			$decimals = Currency::DEFAULT_DECIMALS;
		}

		$position = ( isset( $config['position'] ) && in_array( $config['position'], Currency::POSITIONS, true ) )
			? (string) $config['position']
			: Currency::DEFAULT_POSITION;

		$symbol = isset( $config['symbol'] ) ? trim( strip_tags( (string) $config['symbol'] ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure, WordPress-free sanitizer; output is escaped at render time.

		$updated_at = ( isset( $config['rate_updated_at'] ) && is_numeric( $config['rate_updated_at'] ) )
			? max( 0, (int) $config['rate_updated_at'] )
			: 0;

		$manual_raw = $config['manual_rate'] ?? ( $config['rate'] ?? '' );
		$adjustment = self::normalize_adjustment( $config['merchant_adjustment'] ?? '0' );
		$adjustment = self::enforce_adjustment_range( $adjustment );

		return array(
			'enabled'             => ! isset( $config['enabled'] ) ? true : (bool) $config['enabled'],
			'symbol'              => $symbol,
			'position'            => $position,
			'decimals'            => $decimals,
			'manual_rate'         => self::normalize_rate( $manual_raw ),
			'provider_rate'       => self::normalize_rate( $config['provider_rate'] ?? '' ),
			'merchant_adjustment' => $adjustment,
			'rate_mode'           => self::sanitize_rate_mode( $config['rate_mode'] ?? '', '' ),
			'rate_updated_at'     => $updated_at,
		);
	}

	/**
	 * Normalizes a rate to a plain positive decimal string, or '' if unusable.
	 *
	 * @param mixed $raw Raw rate value.
	 */
	public static function normalize_rate( mixed $raw ): string {
		if ( is_bool( $raw ) || null === $raw ) {
			return '';
		}

		$value = trim( (string) $raw );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		$float = (float) $value;

		if ( ! is_finite( $float ) || $float <= 0.0 ) {
			return '';
		}

		if ( 1 === preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
			return $value;
		}

		return rtrim( rtrim( sprintf( '%.10F', $float ), '0' ), '.' );
	}

	/**
	 * Normalizes a merchant adjustment percentage to a decimal string.
	 *
	 * @param mixed $raw Raw adjustment value.
	 */
	public static function normalize_adjustment( mixed $raw ): string {
		if ( is_bool( $raw ) || null === $raw ) {
			return '0';
		}

		$value = trim( (string) $raw );

		if ( '' === $value || ! is_numeric( $value ) ) {
			return '0';
		}

		$float = (float) $value;

		if ( ! is_finite( $float ) ) {
			return '0';
		}

		if ( 1 === preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) {
			return $value;
		}

		return rtrim( rtrim( sprintf( '%.10F', $float ), '0' ), '.' );
	}

	/**
	 * Clamps a normalized adjustment to the merchant policy range.
	 *
	 * @param string $adjustment Normalized decimal string.
	 */
	public static function enforce_adjustment_range( string $adjustment ): string {
		$float = (float) $adjustment;

		if ( $float > 50.0 ) {
			return '50';
		}

		if ( $float < -50.0 ) {
			return '-50';
		}

		return $adjustment;
	}

	/**
	 * Normalizes a per-currency or global rate mode string.
	 *
	 * @param mixed  $raw      Raw mode value.
	 * @param string $fallback Value when empty or invalid.
	 */
	private static function sanitize_rate_mode( mixed $raw, string $fallback ): string {
		$mode = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		if ( '' === $mode ) {
			return $fallback;
		}

		if ( self::RATE_MODE_MANUAL === $mode || self::RATE_MODE_AUTOMATIC === $mode ) {
			return $mode;
		}

		return $fallback;
	}

	/**
	 * Normalizes the configured rate provider identifier.
	 *
	 * @param mixed $raw Raw provider value.
	 */
	private static function sanitize_provider( mixed $raw ): string {
		$id = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		return self::DEFAULT_RATE_PROVIDER === $id ? self::DEFAULT_RATE_PROVIDER : self::DEFAULT_RATE_PROVIDER;
	}

	/**
	 * Normalizes the recurring update interval ISO-8601 duration.
	 *
	 * @param mixed $raw Raw interval value.
	 */
	private static function sanitize_interval( mixed $raw ): string {
		$value = is_string( $raw ) ? strtoupper( trim( $raw ) ) : '';

		$allowed = array( 'PT6H', 'PT12H', 'P1D', 'P3D', 'P1W' );

		return in_array( $value, $allowed, true ) ? $value : self::DEFAULT_RATE_INTERVAL;
	}

	/**
	 * Normalizes the maximum accepted automatic rate age in hours.
	 *
	 * @param mixed $raw Raw max-age value.
	 */
	private static function sanitize_max_age_hours( mixed $raw ): int {
		if ( ! is_numeric( $raw ) ) {
			return self::DEFAULT_RATE_MAX_AGE_HOURS;
		}

		$hours = (int) $raw;

		if ( $hours < 1 || $hours > 720 ) {
			return self::DEFAULT_RATE_MAX_AGE_HOURS;
		}

		return $hours;
	}

	/**
	 * Returns the full sanitized settings, loading from the option on first use.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null === $this->data ) {
			$this->load_from_option();
		}

		return $this->data;
	}

	/**
	 * Loads settings from the option, running schema upgrade when the option exists.
	 */
	private function load_from_option(): void {
		$stored = get_option( self::OPTION, false );

		if ( false === $stored ) {
			$this->data = self::defaults();
			return;
		}

		$upgrader = self::$upgrader ?? new SettingsUpgrader();
		$result   = $upgrader->upgrade( $stored );

		if ( $result->is_unsupported_future() || $result->is_failed() ) {
			$this->data = self::defaults();
			return;
		}

		$this->data = $result->settings();

		if ( $result->should_persist() ) {
			update_option( self::OPTION, $this->data );
		}
	}

	/**
	 * Sanitizes and persists settings, updating the in-memory copy.
	 *
	 * @param array<string, mixed> $settings Settings to store.
	 */
	public function save( array $settings ): void {
		$this->data = self::sanitize( $settings );
		update_option( self::OPTION, $this->data );
	}

	/**
	 * All configured currency rows, keyed by code.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_currencies(): array {
		return $this->get()['currencies'];
	}

	/**
	 * A single currency's configuration, or null when not configured.
	 *
	 * @param string $code Currency code.
	 * @return array<string, mixed>|null
	 */
	public function get_currency_config( string $code ): ?array {
		return $this->get_currencies()[ strtoupper( $code ) ] ?? null;
	}

	/**
	 * Resolves the effective rate mode for a currency code.
	 *
	 * @param string $code Currency code.
	 */
	public function get_effective_rate_mode( string $code ): string {
		$config = $this->get_currency_config( $code );
		$global = (string) ( $this->get()['rate_mode'] ?? self::RATE_MODE_MANUAL );

		if ( null === $config ) {
			return $global;
		}

		$override = isset( $config['rate_mode'] ) ? (string) $config['rate_mode'] : '';

		if ( self::RATE_MODE_MANUAL === $override || self::RATE_MODE_AUTOMATIC === $override ) {
			return $override;
		}

		return $global;
	}

	/**
	 * The derived effective rate for a code, or null when absent or unusable.
	 *
	 * @param string $code Currency code.
	 */
	public function get_rate( string $code ): ?string {
		$config = $this->get_currency_config( $code );

		if ( null === $config ) {
			return null;
		}

		return RateResolver::effective_rate(
			$this->get_effective_rate_mode( $code ),
			(string) ( $config['manual_rate'] ?? '' ),
			(string) ( $config['provider_rate'] ?? '' ),
			(string) ( $config['merchant_adjustment'] ?? '0' )
		);
	}
}
