<?php
/**
 * Cache-state acknowledgement persistence.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CacheState;

/**
 * Sole gateway for the `umc_cache_state` option (ADR-0032 SS7). Mirrors the
 * `RateUpdateState` standalone-option pattern (ADR-0012): its own option,
 * its own schema version, its own sanitize/defaults, never mixed with
 * `umc_settings`.
 */
final class CacheStateStore {

	public const OPTION = 'umc_cache_state';

	public const SCHEMA_VERSION = 1;

	/**
	 * In-memory state, loaded lazily from the option.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data = null;

	/**
	 * Returns the default state shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema_version'    => self::SCHEMA_VERSION,
			'acknowledged_hash' => '',
			'acknowledged_at'   => 0,
		);
	}

	/**
	 * Sanitizes a persisted payload, falling back to defaults for anything malformed.
	 *
	 * @param mixed $raw Raw persisted value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $raw ): array {
		$clean = self::defaults();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		$hash = isset( $raw['acknowledged_hash'] ) ? (string) $raw['acknowledged_hash'] : '';

		if ( '' === $hash || 1 === preg_match( '/^[a-f0-9]{16}$/', $hash ) ) {
			$clean['acknowledged_hash'] = $hash;
		}

		$at = $raw['acknowledged_at'] ?? 0;

		if ( is_numeric( $at ) ) {
			$clean['acknowledged_at'] = max( 0, (int) $at );
		}

		return $clean;
	}

	/**
	 * Returns the current state, loading from the option when needed.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null === $this->data ) {
			$this->data = self::sanitize( get_option( self::OPTION, false ) );
		}

		return $this->data;
	}

	/**
	 * The persisted acknowledged hash, or '' when never acknowledged.
	 */
	public function acknowledged_hash(): string {
		return (string) $this->get()['acknowledged_hash'];
	}

	/**
	 * The persisted acknowledgement timestamp, or 0 when never acknowledged.
	 */
	public function acknowledged_at(): int {
		return (int) $this->get()['acknowledged_at'];
	}

	/**
	 * Whether the installation has ever completed a first acknowledgement.
	 */
	public function is_enrolled(): bool {
		return '' !== $this->acknowledged_hash();
	}

	/**
	 * Records a newly acknowledged hash. Callers must have already validated
	 * shape and freshness — this method only persists.
	 *
	 * @param string $hash Accepted 16-hex hash.
	 * @param int    $now  Acknowledgement timestamp.
	 */
	public function record( string $hash, int $now ): void {
		$this->data = self::sanitize(
			array(
				'schema_version'    => self::SCHEMA_VERSION,
				'acknowledged_hash' => $hash,
				'acknowledged_at'   => $now,
			)
		);

		update_option( self::OPTION, $this->data, false );
	}
}
