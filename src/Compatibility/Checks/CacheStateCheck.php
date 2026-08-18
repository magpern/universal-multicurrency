<?php
/**
 * External cache-state readiness compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\CacheState\CacheStateService;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;

/**
 * Surfaces the external cache-state readiness signal in the existing Cache
 * category. Enrollment gates severity only — the raw reconciliation state is
 * always carried in evidence. See ADR-0032.
 */
final class CacheStateCheck implements CompatibilityCheckInterface {

	/**
	 * Binds the check to the shared cache-state service.
	 *
	 * @param CacheStateService $service Cache-state read orchestrator.
	 */
	public function __construct( private CacheStateService $service ) {
	}

	/**
	 * Runs the check. Ignores shared inventory — this signal is entirely
	 * derived from the plugin's own cache-critical configuration.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory (unused).
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		unset( $inventory );

		$report   = $this->service->report();
		$evidence = array(
			'state_hash'              => $report->state_hash(),
			'acknowledged_hash'       => $report->acknowledged_hash(),
			'monitoring_enrolled'     => $report->monitoring_enrolled() ? 'yes' : 'no',
			'reconciliation_required' => $report->reconciliation_required() ? 'yes' : 'no',
		);

		if ( ! $report->monitoring_enrolled() ) {
			return array(
				new CompatibilityResult(
					'cache.state_not_enrolled',
					CompatibilityCategory::CACHE,
					CompatibilitySeverity::INFO,
					__( 'External cache state monitoring is not enrolled', 'universal-multicurrency' ),
					__( 'This installation has not enrolled in the external cache-state contract. If an external full-page cache reads this configuration, run `wp umc cache-state acknowledge <hash>` after reconciling it.', 'universal-multicurrency' ),
					CompatibilityDeterminism::DETERMINISTIC,
					$evidence
				),
			);
		}

		if ( ! $report->reconciliation_required() ) {
			return array(
				new CompatibilityResult(
					'cache.state_reconciled',
					CompatibilityCategory::CACHE,
					CompatibilitySeverity::INFO,
					__( 'External cache state is reconciled', 'universal-multicurrency' ),
					__( 'The current cache-critical configuration matches the last acknowledged external cache reconciliation.', 'universal-multicurrency' ),
					CompatibilityDeterminism::DETERMINISTIC,
					$evidence
				),
			);
		}

		return array(
			new CompatibilityResult(
				'cache.state_reconciliation_required',
				CompatibilityCategory::CACHE,
				CompatibilitySeverity::WARNING,
				__( 'External full-page cache reconciliation required', 'universal-multicurrency' ),
				__( 'Cache-critical configuration has changed since the external cache was last reconciled. Reconcile the external cache, then run `wp umc cache-state acknowledge <hash>` with the freshly re-read hash.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC,
				$evidence
			),
		);
	}
}
