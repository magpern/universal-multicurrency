<?php
/**
 * Aggregate summary for a compatibility scan.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Overall status and count buckets for the Compatibility hero card.
 */
final class CompatibilitySummary {

	public const OVERALL_CONFLICT = 'conflict_detected';

	public const OVERALL_CONFIG_INCOMPLETE = 'configuration_incomplete';

	public const OVERALL_ATTENTION = 'attention_recommended';

	public const OVERALL_UNAVAILABLE = 'some_checks_unavailable';

	public const OVERALL_ALL_PASSED = 'all_checks_passed';

	/**
	 * Overall status slug.
	 *
	 * @var string
	 */
	private string $overall;

	/**
	 * Count of passed results.
	 *
	 * @var int
	 */
	private int $passed;

	/**
	 * Count of informational results.
	 *
	 * @var int
	 */
	private int $informational;

	/**
	 * Count of warning results.
	 *
	 * @var int
	 */
	private int $warnings;

	/**
	 * Count of conflict results.
	 *
	 * @var int
	 */
	private int $conflicts;

	/**
	 * Count of unavailable results.
	 *
	 * @var int
	 */
	private int $unavailable;

	/**
	 * Creates a summary.
	 *
	 * @param string $overall        Overall status slug.
	 * @param int    $passed         Passed count.
	 * @param int    $informational  Informational count.
	 * @param int    $warnings       Warning count.
	 * @param int    $conflicts      Conflict count.
	 * @param int    $unavailable    Unavailable count.
	 */
	public function __construct(
		string $overall,
		int $passed,
		int $informational,
		int $warnings,
		int $conflicts,
		int $unavailable
	) {
		$this->overall       = $overall;
		$this->passed        = $passed;
		$this->informational = $informational;
		$this->warnings      = $warnings;
		$this->conflicts     = $conflicts;
		$this->unavailable   = $unavailable;
	}

	/**
	 * Overall status slug.
	 */
	public function overall(): string {
		return $this->overall;
	}

	/**
	 * Passed count.
	 */
	public function passed(): int {
		return $this->passed;
	}

	/**
	 * Informational count.
	 */
	public function informational(): int {
		return $this->informational;
	}

	/**
	 * Warning count.
	 */
	public function warnings(): int {
		return $this->warnings;
	}

	/**
	 * Conflict count.
	 */
	public function conflicts(): int {
		return $this->conflicts;
	}

	/**
	 * Unavailable count.
	 */
	public function unavailable(): int {
		return $this->unavailable;
	}
}
