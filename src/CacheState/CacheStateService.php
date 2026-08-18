<?php
/**
 * Cache-state read/acknowledge orchestration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CacheState;

use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;

/**
 * Composes the live cache-critical factors with the persisted acknowledgement
 * state. See ADR-0032 and docs/architecture/external-cache-state-readiness.md.
 */
final class CacheStateService {

	/**
	 * Binds the service to its collaborators.
	 *
	 * @param CurrencyRegistry               $registry Currency registry (base + selectable codes).
	 * @param GeoDetectionSettingsRepository $geo      Geo settings repository.
	 * @param Settings                       $settings Merchant settings store.
	 * @param CacheStateStore                $store    Acknowledgement persistence gateway.
	 */
	public function __construct(
		private CurrencyRegistry $registry,
		private GeoDetectionSettingsRepository $geo,
		private Settings $settings,
		private CacheStateStore $store
	) {
	}

	/**
	 * Builds the live four-factor state from current configuration.
	 */
	public function current_factors(): CacheStateFactors {
		return new CacheStateFactors(
			$this->registry->get_base_code(),
			$this->registry->get_selectable_codes(),
			$this->geo->get()->is_enabled()
		);
	}

	/**
	 * The flat, read-only report combining live and persisted state.
	 */
	public function report(): CacheStateReport {
		return new CacheStateReport(
			$this->current_factors(),
			$this->store->acknowledged_hash(),
			$this->store->acknowledged_at(),
			$this->rates_last_updated_at()
		);
	}

	/**
	 * Validates and records an acknowledgement of the current hash.
	 *
	 * Accepts only the current, freshly re-evaluated hash. Any malformed,
	 * unknown, or stale value is rejected with no write. Never touches
	 * `umc_settings`, currencies, rates, or geo.
	 *
	 * @param string $hash Candidate hash to acknowledge.
	 */
	public function acknowledge( string $hash ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{16}$/', $hash ) ) {
			return false;
		}

		$current = $this->current_factors()->hash();

		if ( ! hash_equals( $current, $hash ) ) {
			return false;
		}

		$this->store->record( $hash, time() );

		return true;
	}

	/**
	 * The maximum `rate_updated_at` among currently selectable, non-base
	 * currencies. Disabled, unselectable, and base-currency rates never
	 * contribute. Excluded from state_hash — informational only.
	 */
	private function rates_last_updated_at(): int {
		$base       = $this->registry->get_base_code();
		$selectable = array_flip( $this->registry->get_selectable_codes() );
		$latest     = 0;

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			$code = strtoupper( (string) $code );

			if ( $code === $base || ! isset( $selectable[ $code ] ) || ! is_array( $config ) ) {
				continue;
			}

			$updated = (int) ( $config['rate_updated_at'] ?? 0 );
			$latest  = max( $latest, $updated );
		}

		return $latest;
	}
}
