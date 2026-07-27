<?php
/**
 * Plugin settings store.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

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

	public const SCHEMA_VERSION = 1;

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
	 * @return array{schema_version: int, currencies: array<string, array<string, mixed>>}
	 */
	public static function defaults(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'currencies'     => array(),
		);
	}

	/**
	 * Cleans arbitrary input into a valid, persistable settings structure.
	 *
	 * Pure and WordPress-free. Never throws.
	 *
	 * @param mixed $raw Arbitrary input.
	 * @return array{schema_version: int, currencies: array<string, array<string, mixed>>}
	 */
	public static function sanitize( mixed $raw ): array {
		$clean = self::defaults();

		if ( ! is_array( $raw ) || ! isset( $raw['currencies'] ) || ! is_array( $raw['currencies'] ) ) {
			return $clean;
		}

		foreach ( $raw['currencies'] as $code => $config ) {
			$code = is_string( $code ) ? strtoupper( trim( $code ) ) : '';

			if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) || ! is_array( $config ) ) {
				continue;
			}

			$clean['currencies'][ $code ] = self::sanitize_currency( $config );
		}

		return $clean;
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

		return array(
			'enabled'         => ! isset( $config['enabled'] ) ? true : (bool) $config['enabled'],
			'symbol'          => $symbol,
			'position'        => $position,
			'decimals'        => $decimals,
			'rate'            => self::normalize_rate( $config['rate'] ?? '' ),
			'rate_updated_at' => $updated_at,
		);
	}

	/**
	 * Normalizes a rate to a plain positive decimal string, or '' if unusable.
	 *
	 * Accepts int, float or numeric string; rejects non-numeric, non-finite,
	 * zero and negative values. A clean positive decimal string is preserved
	 * verbatim (no float round-trip); other numeric forms are rendered without
	 * an exponent.
	 *
	 * @param mixed $raw Raw rate value.
	 */
	private static function normalize_rate( mixed $raw ): string {
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
	 * Returns the full sanitized settings, loading from the option on first use.
	 *
	 * @return array{schema_version: int, currencies: array<string, array<string, mixed>>}
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
	 * The stored rate string for a code, or null when absent or unusable.
	 *
	 * @param string $code Currency code.
	 */
	public function get_rate( string $code ): ?string {
		$config = $this->get_currency_config( $code );

		if ( null === $config || ! isset( $config['rate'] ) || '' === $config['rate'] ) {
			return null;
		}

		return (string) $config['rate'];
	}
}
