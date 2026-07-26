<?php
/**
 * Diagnostics sub-composition root — orchestration only in this milestone.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The only Diagnostics class {@see \UMC\Plugin} will name in a later commit.
 * This milestone wires the detection stack only; admin surfaces register in
 * subsequent commits.
 */
final class Diagnostics {

	private ConflictDetector $detector;

	public function __construct( ?ConflictDetector $detector = null ) {
		$this->detector = $detector ?? new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);
	}

	/**
	 * Reserved for admin hook registration in a later milestone.
	 */
	public function register(): void {
	}

	/**
	 * The memoized conflict detector owned by this service.
	 */
	public function conflict_detector(): ConflictDetector {
		return $this->detector;
	}
}
