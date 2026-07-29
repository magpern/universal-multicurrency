<?php
/**
 * Compatibility scan orchestrator.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Runs grouped checks once per request and memoizes the aggregate scan.
 */
final class CompatibilityScanner {

	/**
	 * Shared request inventory.
	 *
	 * @var CompatibilityInventory
	 */
	private CompatibilityInventory $inventory;

	/**
	 * Registered checks in execution order.
	 *
	 * @var array<int, CompatibilityCheckInterface>
	 */
	private array $checks;

	/**
	 * Optional report builder callback.
	 *
	 * @var callable(CompatibilityScan):string|null
	 */
	private $report_builder;

	/**
	 * Memoized scan.
	 *
	 * @var CompatibilityScan|null
	 */
	private ?CompatibilityScan $scan = null;

	/**
	 * Creates a scanner.
	 *
	 * @param CompatibilityInventory                  $inventory      Shared inventory.
	 * @param array<int, CompatibilityCheckInterface> $checks         Checks to run.
	 * @param callable(CompatibilityScan):string|null $report_builder Optional report builder.
	 */
	public function __construct(
		CompatibilityInventory $inventory,
		array $checks,
		?callable $report_builder = null
	) {
		$this->inventory      = $inventory;
		$this->checks         = $checks;
		$this->report_builder = $report_builder;
	}

	/**
	 * Runs or returns the memoized scan.
	 */
	public function scan(): CompatibilityScan {
		if ( null !== $this->scan ) {
			return $this->scan;
		}

		$results = array();

		foreach ( $this->checks as $check ) {
			$chunk = $check->run( $this->inventory );
			foreach ( $chunk as $result ) {
				$results[] = $result;
			}
		}

		usort( $results, array( CompatibilityResult::class, 'compare' ) );

		$summary    = SummaryCalculator::calculate( $results );
		$pre_report = new CompatibilityScan( $results, $summary );
		$report     = is_callable( $this->report_builder )
			? (string) ( $this->report_builder )( $pre_report )
			: '';

		$this->scan = new CompatibilityScan( $results, $summary, $report );

		return $this->scan;
	}
}
