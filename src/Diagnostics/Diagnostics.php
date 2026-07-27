<?php
/**
 * Diagnostics sub-composition root — orchestration only in this milestone.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The only Diagnostics class {@see \UMC\Plugin} names outside this namespace.
 * Wires the detection stack and admin advisory surfaces.
 */
final class Diagnostics {

	/**
	 * Memoized conflict detector shared by advisory surfaces.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $detector;

	/**
	 * Builds the diagnostics service and its detector stack.
	 *
	 * @param ConflictDetector|null $detector Optional detector for tests.
	 */
	public function __construct( ?ConflictDetector $detector = null ) {
		$this->detector = $detector ?? new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);
	}

	/**
	 * Registers admin advisory surfaces. Detection still runs lazily at render
	 * time inside {@see ConflictDetector::findings()}.
	 */
	public function register(): void {
		$dismissal = new NoticeDismissal( $this->detector );
		$dismissal->register();

		( new ConflictNotice( $this->detector, $dismissal ) )->register();
		( new SiteHealthReport( $this->detector ) )->register();
	}

	/**
	 * The memoized conflict detector owned by this service.
	 */
	public function conflict_detector(): ConflictDetector {
		return $this->detector;
	}
}
