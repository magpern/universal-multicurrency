<?php
/**
 * Flat, read-only cache-state report.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CacheState;

/**
 * Immutable value object shaping the machine-readable cache-state contract
 * consumed by the CLI, Site Health, and the Compatibility check. Pure PHP,
 * no WordPress.
 */
final class CacheStateReport {

	/**
	 * Builds the report.
	 *
	 * @param CacheStateFactors $factors                 Live cache-critical factors.
	 * @param string            $acknowledged_hash       Persisted acknowledged hash, or ''.
	 * @param int               $acknowledged_at         Persisted acknowledgement timestamp, or 0.
	 * @param int               $rates_last_updated_at   Max rate_updated_at over selectable non-base currencies, or 0.
	 */
	public function __construct(
		private CacheStateFactors $factors,
		private string $acknowledged_hash,
		private int $acknowledged_at,
		private int $rates_last_updated_at
	) {
	}

	/**
	 * The live, derived state hash.
	 */
	public function state_hash(): string {
		return $this->factors->hash();
	}

	/**
	 * The persisted acknowledged hash, or '' when never acknowledged.
	 */
	public function acknowledged_hash(): string {
		return $this->acknowledged_hash;
	}

	/**
	 * Whether the installation has ever completed a first acknowledgement.
	 */
	public function monitoring_enrolled(): bool {
		return '' !== $this->acknowledged_hash;
	}

	/**
	 * Raw, unconditional machine state: does the current configuration match
	 * what was last acknowledged? Never coerced by enrollment status.
	 */
	public function reconciliation_required(): bool {
		return $this->factors->hash() !== $this->acknowledged_hash;
	}

	/**
	 * The underlying factor set.
	 */
	public function factors(): CacheStateFactors {
		return $this->factors;
	}

	/**
	 * Flat, stable-typed contract array. Additive-only within one
	 * `contract_version` — see ADR-0032 SS6.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'contract_version'        => CacheStateFactors::CONTRACT_VERSION,
			'state_hash'               => $this->state_hash(),
			'acknowledged_hash'        => $this->acknowledged_hash,
			'monitoring_enrolled'      => $this->monitoring_enrolled(),
			'reconciliation_required'  => $this->reconciliation_required(),
			'base_currency'            => $this->factors->base_currency(),
			'currencies'               => $this->factors->currencies(),
			'geo_enabled'              => $this->factors->geo_enabled(),
			'acknowledged_at'          => $this->acknowledged_at > 0 ? gmdate( 'c', $this->acknowledged_at ) : '',
			'rates_last_updated_at'    => $this->rates_last_updated_at > 0 ? gmdate( 'c', $this->rates_last_updated_at ) : '',
		);
	}
}
