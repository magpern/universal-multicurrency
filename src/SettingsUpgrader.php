<?php
/**
 * Settings schema upgrade runner.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Checkout\CheckoutSettings;
use UMC\Display\SwitcherSettings;
use UMC\Geo\GeoDetectionSettings;

/**
 * Applies versioned migrations to persisted `umc_settings` before sanitization.
 *
 * Production ships a single real migration (0 → 1). Additional versions exist
 * only in tests via injected migration maps.
 */
final class SettingsUpgrader {

	/**
	 * Production migration from schema version 0 to 1.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_0_TO_1 = array( self::class, 'migrate_0_to_1' );

	/**
	 * Target schema version for this runner instance.
	 *
	 * @var int
	 */
	private int $target_version;

	/**
	 * Migration callables keyed by the schema version they produce.
	 *
	 * @var array<int, callable(array<string, mixed>): array<string, mixed>>
	 */
	private array $migrations;

	/**
	 * Builds an upgrade runner.
	 *
	 * @param int|null                                                              $target_version Target schema version (defaults to {@see Settings::SCHEMA_VERSION}).
	 * @param array<int, callable(array<string, mixed>): array<string, mixed>>|null $migrations     Target version => migrator callable.
	 */
	public function __construct( ?int $target_version = null, ?array $migrations = null ) {
		$this->target_version = $target_version ?? Settings::SCHEMA_VERSION;
		$this->migrations     = $migrations ?? self::production_migrations();
	}

	/**
	 * Production migrations keyed by the schema version they produce.
	 *
	 * @return array<int, callable(array<string, mixed>): array<string, mixed>>
	 */
	public static function production_migrations(): array {
		return array(
			1 => self::MIGRATE_0_TO_1,
			2 => self::MIGRATE_1_TO_2,
			3 => self::MIGRATE_2_TO_3,
			4 => self::MIGRATE_3_TO_4,
			5 => self::MIGRATE_4_TO_5,
			6 => self::MIGRATE_5_TO_6,
			7 => self::MIGRATE_6_TO_7,
		);
	}

	/**
	 * Reads the schema version from raw persisted settings.
	 *
	 * Missing, malformed, or non-numeric values are treated as version **0**
	 * (pre-schema stores that only persisted a `currencies` array).
	 *
	 * @param mixed $raw Raw persisted option value.
	 */
	public static function parse_stored_version( mixed $raw ): int {
		if ( ! is_array( $raw ) || ! array_key_exists( 'schema_version', $raw ) ) {
			return 0;
		}

		$version = $raw['schema_version'];

		if ( is_int( $version ) ) {
			return max( 0, $version );
		}

		if ( is_string( $version ) && 1 === preg_match( '/^\d+$/', $version ) ) {
			return (int) $version;
		}

		return 0;
	}

	/**
	 * Upgrades raw persisted settings to the runner's target schema version.
	 *
	 * Always returns sanitized settings on success. Never throws.
	 *
	 * @param mixed $raw Raw persisted option value.
	 */
	public function upgrade( mixed $raw ): SettingsUpgradeResult {
		$stored_version = self::parse_stored_version( $raw );

		if ( $stored_version > $this->target_version ) {
			return SettingsUpgradeResult::unsupported_future( $stored_version, $this->target_version );
		}

		try {
			$working = $this->apply_migrations( $raw, $stored_version );
		} catch ( \Throwable $exception ) {
			return SettingsUpgradeResult::failed( $exception->getMessage() );
		}

		$raw_array  = is_array( $raw ) ? $raw : array();
		$canonical  = Settings::sanitize( $working );
		$migrated   = $stored_version < $this->target_version;
		$normalized = $this->stored_shape_differs_from_canonical( $raw_array, $canonical );

		return SettingsUpgradeResult::success(
			$canonical,
			$migrated || $normalized
		);
	}

	/**
	 * Runs migrations only, without final sanitization.
	 *
	 * @internal Test seam for chaining proofs; production callers use {@see upgrade()}.
	 *
	 * @param mixed $raw Raw persisted option value.
	 * @return array<string, mixed>
	 *
	 * @throws \RuntimeException When the stored version is unsupported or a migration fails.
	 */
	public function migrate_only( mixed $raw ): array {
		$stored_version = self::parse_stored_version( $raw );

		if ( $stored_version > $this->target_version ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception messages built from integer schema versions.
			throw new \RuntimeException(
				'Unsupported settings schema version ' . $stored_version . ' (target ' . $this->target_version . ').'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $this->apply_migrations( $raw, $stored_version );
	}

	/**
	 * Real v0 → v1 migration.
	 *
	 * Version 0 stores were arrays with a `currencies` key only (no explicit
	 * `schema_version`). This step introduces schema version 1 without altering
	 * the currency rows — normalization is delegated to {@see Settings::sanitize()}.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 0.
	 * @return array<string, mixed>
	 */
	public static function migrate_0_to_1( array $data ): array {
		return array(
			'schema_version' => 1,
			'currencies'     => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
		);
	}

	/**
	 * Real v1 → v2 migration.
	 *
	 * Renames `rate` to `manual_rate`, adds automatic-rate fields, and global
	 * configuration defaults. Upgraded stores remain in manual mode.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 1.
	 * @return array<string, mixed>
	 */
	public static function migrate_1_to_2( array $data ): array {
		$currencies = array();

		if ( isset( $data['currencies'] ) && is_array( $data['currencies'] ) ) {
			foreach ( $data['currencies'] as $code => $config ) {
				if ( ! is_string( $code ) || ! is_array( $config ) ) {
					continue;
				}

				$row = $config;

				if ( ! array_key_exists( 'manual_rate', $row ) && array_key_exists( 'rate', $row ) ) {
					$row['manual_rate'] = $row['rate'];
				}

				unset( $row['rate'] );

				$currencies[ $code ] = $row;
			}
		}

		return array(
			'schema_version'       => 2,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => $currencies,
		);
	}

	/**
	 * Migration callable for v1 → v2.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_1_TO_2 = array( self::class, 'migrate_1_to_2' );

	/**
	 * Real v2 → v3 migration.
	 *
	 * Adds the Display switcher settings block with safe defaults. Existing
	 * currency and exchange-rate configuration is preserved unchanged.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 2.
	 * @return array<string, mixed>
	 */
	public static function migrate_2_to_3( array $data ): array {
		return array(
			'schema_version'       => 3,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
			'display'              => SwitcherSettings::default_array(),
		);
	}

	/**
	 * Migration callable for v2 → v3.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_2_TO_3 = array( self::class, 'migrate_2_to_3' );

	/**
	 * Real v3 → v4 migration.
	 *
	 * Adds checkout policy defaults. Existing currency, rate, and display
	 * configuration is preserved unchanged.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 3.
	 * @return array<string, mixed>
	 */
	public static function migrate_3_to_4( array $data ): array {
		return array(
			'schema_version'       => 4,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
			'display'              => is_array( $data['display'] ?? null ) ? $data['display'] : SwitcherSettings::default_array(),
			'checkout'             => CheckoutSettings::default_array(),
		);
	}

	/**
	 * Migration callable for v3 → v4.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_3_TO_4 = array( self::class, 'migrate_3_to_4' );

	/**
	 * Real v4 → v5 migration.
	 *
	 * Adds Geo Detection defaults. Existing configuration is preserved;
	 * geo remains disabled with no routing rules.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 4.
	 * @return array<string, mixed>
	 */
	public static function migrate_4_to_5( array $data ): array {
		return array(
			'schema_version'       => 5,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
			'display'              => is_array( $data['display'] ?? null ) ? $data['display'] : SwitcherSettings::default_array(),
			'checkout'             => is_array( $data['checkout'] ?? null ) ? $data['checkout'] : CheckoutSettings::default_array(),
			'geo'                  => GeoDetectionSettings::default_array(),
		);
	}

	/**
	 * Migration callable for v4 → v5.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_4_TO_5 = array( self::class, 'migrate_4_to_5' );

	/**
	 * Real v5 → v6 migration.
	 *
	 * Restructures the Display switcher block for the layered presentation
	 * system (ADR-0022): legacy `appearance.*` becomes first-class
	 * `design.theme|size|shape`, flat content toggles split into trigger and
	 * menu composition, and the preset, overrides, motion, responsive, and
	 * Custom CSS fields are initialized. Existing appearance must not drift:
	 * the preset is always `default` and theme/size/shape are copied verbatim.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 5.
	 * @return array<string, mixed>
	 */
	public static function migrate_5_to_6( array $data ): array {
		return array(
			'schema_version'       => 6,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
			'display'              => self::migrate_display_5_to_6(
				is_array( $data['display'] ?? null ) ? $data['display'] : array()
			),
			'checkout'             => is_array( $data['checkout'] ?? null ) ? $data['checkout'] : CheckoutSettings::default_array(),
			'geo'                  => is_array( $data['geo'] ?? null ) ? $data['geo'] : GeoDetectionSettings::default_array(),
		);
	}

	/**
	 * Migration callable for v5 → v6.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_5_TO_6 = array( self::class, 'migrate_5_to_6' );

	/**
	 * Real v6 → v7 migration.
	 *
	 * Adds optional switcher presentation icon settings with safe defaults.
	 * Existing content order and visibility remain unchanged; icons default off.
	 *
	 * @param array<string, mixed> $data Raw settings at schema version 6.
	 * @return array<string, mixed>
	 */
	public static function migrate_6_to_7( array $data ): array {
		return array(
			'schema_version'       => 7,
			'rate_mode'            => $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL,
			'rate_provider'        => $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL,
			'rate_max_age_hours'   => $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS,
			'currencies'           => is_array( $data['currencies'] ?? null ) ? $data['currencies'] : array(),
			'display'              => self::migrate_display_6_to_7(
				is_array( $data['display'] ?? null ) ? $data['display'] : array()
			),
			'checkout'             => is_array( $data['checkout'] ?? null ) ? $data['checkout'] : CheckoutSettings::default_array(),
			'geo'                  => is_array( $data['geo'] ?? null ) ? $data['geo'] : GeoDetectionSettings::default_array(),
		);
	}

	/**
	 * Migration callable for v6 → v7.
	 *
	 * @var callable(array<string, mixed>): array<string, mixed>
	 */
	public const MIGRATE_6_TO_7 = array( self::class, 'migrate_6_to_7' );

	/**
	 * Rewrites one schema-6 Display block into the schema-7 shape.
	 *
	 * @param array<string, mixed> $display Schema-6 Display block.
	 * @return array<string, mixed>
	 */
	private static function migrate_display_6_to_7( array $display ): array {
		$defaults = SwitcherSettings::default_array();
		$content  = is_array( $display['content'] ?? null ) ? $display['content'] : array();

		$upgraded = array_replace_recursive(
			$display,
			array(
				'content'      => array(
					'trigger' => array( 'show_icon' => false ),
					'menu'    => array( 'show_icon' => false ),
				),
				'presentation' => $defaults['presentation'],
			)
		);

		if ( is_array( $content['trigger'] ?? null ) ) {
			$upgraded['content']['trigger'] = array_merge( $content['trigger'], array( 'show_icon' => false ) );
		}

		if ( is_array( $content['menu'] ?? null ) ) {
			$upgraded['content']['menu'] = array_merge( $content['menu'], array( 'show_icon' => false ) );
		}

		return SwitcherSettings::from_array( $upgraded )->to_array();
	}

	/**
	 * Rewrites one schema-5 Display block into the schema-6 shape.
	 *
	 * Element ordering, the non-empty label guardrail, and enum validation are
	 * delegated to {@see SwitcherSettings}, so re-running the step over already
	 * migrated data is a no-op.
	 *
	 * @param array<string, mixed> $display Legacy Display block.
	 * @return array<string, mixed>
	 */
	private static function migrate_display_5_to_6( array $display ): array {
		$defaults   = SwitcherSettings::default_array();
		$appearance = is_array( $display['appearance'] ?? null ) ? $display['appearance'] : array();
		$content    = is_array( $display['content'] ?? null ) ? $display['content'] : array();

		$legacy_menu = is_array( $content['menu'] ?? null ) ? $content['menu'] : $content;
		$show_code   = self::legacy_toggle( $legacy_menu, 'show_code', true );
		$show_symbol = self::legacy_toggle( $legacy_menu, 'show_symbol', true );
		$show_name   = self::legacy_toggle( $legacy_menu, 'show_name', false );

		$design = is_array( $display['design'] ?? null ) ? $display['design'] : array();

		$upgraded = array(
			'enabled'    => $display['enabled'] ?? $defaults['enabled'],
			'placement'  => $display['placement'] ?? $defaults['placement'],
			'style'      => $display['style'] ?? $defaults['style'],
			'position'   => is_array( $display['position'] ?? null ) ? $display['position'] : $defaults['position'],
			'content'    => array(
				'trigger'      => array(
					'show_code'   => $show_code,
					'show_symbol' => $show_symbol,
					'show_name'   => false,
				),
				'menu'         => array(
					'show_code'   => $show_code,
					'show_symbol' => $show_symbol,
					'show_name'   => $show_name,
				),
				'show_chevron' => false,
			),
			'design'     => array(
				'preset'    => SwitcherSettings::PRESET_DEFAULT,
				'theme'     => $design['theme'] ?? $appearance['theme'] ?? $defaults['design']['theme'],
				'size'      => $design['size'] ?? $appearance['size'] ?? $defaults['design']['size'],
				'shape'     => $design['shape'] ?? $appearance['shape'] ?? $defaults['design']['shape'],
				'overrides' => array(),
				'motion'    => SwitcherSettings::MOTION_SUBTLE,
			),
			'behavior'   => is_array( $display['behavior'] ?? null ) ? $display['behavior'] : $defaults['behavior'],
			'visibility' => is_array( $display['visibility'] ?? null ) ? $display['visibility'] : $defaults['visibility'],
			'responsive' => $defaults['responsive'],
			'custom_css' => '',
		);

		return SwitcherSettings::from_array( $upgraded )->to_array();
	}

	/**
	 * Reads one legacy content toggle with its schema-5 default.
	 *
	 * @param array<string, mixed> $content Legacy content toggles.
	 * @param string               $key     Toggle key.
	 * @param bool                 $default Schema-5 default.
	 */
	private static function legacy_toggle( array $content, string $key, bool $default ): bool { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Mirrors the settings default vocabulary.
		if ( ! array_key_exists( $key, $content ) ) {
			return $default;
		}

		return (bool) $content[ $key ];
	}

	/**
	 * Applies migrations from the stored version up to the target version.
	 *
	 * @param mixed $raw            Raw persisted option value.
	 * @param int   $stored_version Parsed stored schema version.
	 * @return array<string, mixed>
	 *
	 * @throws \RuntimeException When a migration is missing or returns a non-array.
	 */
	private function apply_migrations( mixed $raw, int $stored_version ): array {
		$working = is_array( $raw ) ? $raw : array();

		for ( $target = $stored_version + 1; $target <= $this->target_version; $target++ ) {
			if ( ! isset( $this->migrations[ $target ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception messages built from integer schema versions.
				throw new \RuntimeException( 'Missing migration for schema version ' . $target . '.' );
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$next = ( $this->migrations[ $target ] )( $working );

			if ( ! is_array( $next ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception messages built from integer schema versions.
				throw new \RuntimeException( 'Migration to schema version ' . $target . ' did not return an array.' );
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$working = $next;
		}

		return $working;
	}

	/**
	 * Whether the persisted option shape differs from the canonical settings array.
	 *
	 * @param array<string, mixed> $raw       Persisted option value.
	 * @param array<string, mixed> $canonical Sanitized canonical settings.
	 */
	private function stored_shape_differs_from_canonical( array $raw, array $canonical ): bool {
		return $raw !== $canonical;
	}
}
