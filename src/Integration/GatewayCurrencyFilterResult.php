<?php
/**
 * Gateway currency filter result pair.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

/**
 * Filtered gateway map plus immutable evaluation snapshot.
 */
final class GatewayCurrencyFilterResult {

	/**
	 * Gateways remaining after currency filtering.
	 *
	 * @var array<string, object>
	 */
	private array $filtered_gateways;

	/**
	 * Structured evaluation snapshot.
	 *
	 * @var GatewayCurrencyEvaluation
	 */
	private GatewayCurrencyEvaluation $evaluation;

	/**
	 * Creates a filter result.
	 *
	 * @param array<string, object>   $filtered_gateways Filtered gateway map.
	 * @param GatewayCurrencyEvaluation $evaluation      Evaluation snapshot.
	 */
	public function __construct( array $filtered_gateways, GatewayCurrencyEvaluation $evaluation ) {
		$this->filtered_gateways = $filtered_gateways;
		$this->evaluation        = $evaluation;
	}

	/**
	 * Gateways remaining after currency filtering.
	 *
	 * @return array<string, object>
	 */
	public function filtered_gateways(): array {
		return $this->filtered_gateways;
	}

	/**
	 * Structured evaluation snapshot.
	 */
	public function evaluation(): GatewayCurrencyEvaluation {
		return $this->evaluation;
	}
}
