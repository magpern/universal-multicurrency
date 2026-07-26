<?php
/**
 * Unit tests for ConflictDetector orchestration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\Detector;
use UMC\Diagnostics\EnvironmentProbe;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;
use UMC\Tests\Unit\Doubles\CountingEnvironmentProbe;
use UMC\Tests\Unit\Doubles\StaticDetectorRegistry;

/**
 * Covers one-pass probing, memoization, ordering, and fingerprint behaviour
 * using the EnvironmentProbe seam — no WordPress bootstrap required.
 */
final class ConflictDetectorTest extends TestCase {

	private function detector( EnvironmentProbe $probe, array $detectors ): ConflictDetector {
		return new ConflictDetector(
			new StaticDetectorRegistry( $detectors ),
			$probe,
			new ConflictScorer()
		);
	}

	private function full_detector( string $id = 'alpha' ): Detector {
		return new Detector(
			$id,
			'Alpha',
			array(
				new Signature( SignatureKind::PLUGIN_PATH, "{$id}/index.php", 60 ),
				new Signature( SignatureKind::CLASS_NAME, "Example_{$id}", 40 ),
			)
		);
	}

	public function test_findings_are_empty_when_evidence_is_empty(): void {
		$detector = $this->detector( new ArrayEnvironmentProbe( array() ), array( $this->full_detector() ) );

		$this->assertSame( array(), $detector->findings() );
		$this->assertFalse( $detector->has_conflict() );
		$this->assertSame( '', $detector->fingerprint() );
	}

	public function test_two_detectors_return_deterministic_score_then_id_order(): void {
		$high   = new Detector(
			'zebra',
			'Zebra',
			array( new Signature( SignatureKind::PLUGIN_PATH, 'zebra/index.php', 60 ) )
		);
		$medium = new Detector(
			'alpha',
			'Alpha',
			array( new Signature( SignatureKind::CLASS_NAME, 'AlphaClass', 40 ) )
		);

		$probe = new ArrayEnvironmentProbe(
			array(
				'plugin_path:zebra/index.php' => true,
				'class:AlphaClass'            => true,
			)
		);

		$detector = $this->detector( $probe, array( $medium, $high ) );
		$findings = $detector->findings();

		$this->assertCount( 2, $findings );
		$this->assertSame( 'zebra', $findings[0]->id() );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
		$this->assertSame( 'alpha', $findings[1]->id() );
		$this->assertSame( Confidence::MEDIUM, $findings[1]->confidence() );
	}

	public function test_findings_and_evidence_are_memoized(): void {
		$counting = new CountingEnvironmentProbe(
			new ArrayEnvironmentProbe(
				array(
					'plugin_path:alpha/index.php' => true,
				)
			)
		);

		$detector = $this->detector( $counting, array( $this->full_detector() ) );

		$first  = $detector->findings();
		$second = $detector->findings();

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $counting->calls() );
	}

	public function test_duplicate_signatures_across_detectors_are_probed_once(): void {
		$shared   = new Signature( SignatureKind::HOOK, 'shared_hook', 10 );
		$a        = new Detector( 'alpha', 'Alpha', array( $shared ) );
		$b        = new Detector( 'beta', 'Beta', array( $shared ) );
		$counting = new CountingEnvironmentProbe(
			new ArrayEnvironmentProbe(
				array(
					'hook:shared_hook' => true,
				)
			)
		);

		$detector = $this->detector( $counting, array( $a, $b ) );

		$this->assertCount( 2, $detector->findings() );
		$this->assertSame( 1, $counting->calls() );
		$this->assertCount( 1, $detector->evidence() );
	}

	public function test_fingerprint_is_stable_and_changes_when_conflicts_change(): void {
		$detector = $this->detector(
			new ArrayEnvironmentProbe(
				array(
					'plugin_path:alpha/index.php' => true,
				)
			),
			array( $this->full_detector( 'alpha' ) )
		);

		$first = $detector->fingerprint();

		$this->assertSame( 16, strlen( $first ) );
		$this->assertSame( $first, $detector->fingerprint() );

		$second = $this->detector(
			new ArrayEnvironmentProbe(
				array(
					'plugin_path:alpha/index.php' => true,
					'plugin_path:beta/index.php'  => true,
				)
			),
			array(
				$this->full_detector( 'alpha' ),
				$this->full_detector( 'beta' ),
			)
		);

		$this->assertNotSame( $first, $second->fingerprint() );
	}
}
