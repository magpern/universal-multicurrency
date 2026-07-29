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
