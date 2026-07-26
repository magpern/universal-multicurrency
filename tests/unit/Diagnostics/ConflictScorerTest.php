<?php
/**
 * Unit tests for the pure weight-sum scorer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\Detector;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;

/**
 * Covers the weight-sum scoring model: evidence combinations, boundaries,
 * ordering, monotonicity and input validation.
 */
final class ConflictScorerTest extends TestCase {

	private function scorer(): ConflictScorer {
		return new ConflictScorer();
	}

	/**
	 * A detector carrying one signature of each admissible kind, at each
	 * kind's default weight, id-prefixed so multiple can coexist in one test.
	 */
	private function full_detector( string $id = 'example' ): Detector {
		return new Detector(
			$id,
			'Example',
			array(
				new Signature( SignatureKind::PLUGIN_PATH, "{$id}/index.php", SignatureKind::default_weight( SignatureKind::PLUGIN_PATH ) ),
				new Signature( SignatureKind::CLASS_NAME, "Example_{$id}", SignatureKind::default_weight( SignatureKind::CLASS_NAME ) ),
				new Signature( SignatureKind::FUNCTION, "{$id}_function", SignatureKind::default_weight( SignatureKind::FUNCTION ) ),
				new Signature( SignatureKind::CONSTANT, strtoupper( "{$id}_VERSION" ), SignatureKind::default_weight( SignatureKind::CONSTANT ) ),
				new Signature( SignatureKind::SHORTCODE, $id, SignatureKind::default_weight( SignatureKind::SHORTCODE ) ),
				new Signature( SignatureKind::HOOK, "{$id}_hook", SignatureKind::default_weight( SignatureKind::HOOK ) ),
			)
		);
	}

	// ------------------------------------------------------------------
	// #1-6, #9: single-detector evidence combinations.
	// ------------------------------------------------------------------

	public function test_plugin_path_and_class_and_constant_reach_high(): void {
		$detector = $this->full_detector();
		$evidence = array(
			'plugin_path:example/index.php' => true,
			'class:Example_example'         => true,
			'constant:EXAMPLE_VERSION'      => true,
		);

		$findings = $this->scorer()->score( array( $detector ), $evidence );

		$this->assertCount( 1, $findings );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
		$this->assertSame( 100, $findings[0]->score(), 'The raw sum (60+40+25=125) must clamp to 100.' );
	}

	public function test_plugin_path_alone_reaches_high_exactly_on_the_boundary(): void {
		$detector = $this->full_detector();
		$evidence = array( 'plugin_path:example/index.php' => true );

		$findings = $this->scorer()->score( array( $detector ), $evidence );

		$this->assertCount( 1, $findings );
		$this->assertSame( 60, $findings[0]->score() );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
	}

	public function test_class_alone_reaches_medium(): void {
		$detector = $this->full_detector();
		$evidence = array( 'class:Example_example' => true );

		$findings = $this->scorer()->score( array( $detector ), $evidence );

		$this->assertCount( 1, $findings );
		$this->assertSame( 40, $findings[0]->score() );
		$this->assertSame( Confidence::MEDIUM, $findings[0]->confidence() );
	}

	public function test_empty_evidence_produces_no_findings(): void {
		$findings = $this->scorer()->score( array( $this->full_detector() ), array() );

		$this->assertSame( array(), $findings );
	}

	public function test_only_unrelated_evidence_keys_produce_no_findings(): void {
		$evidence = array(
			'class:SomeUnrelatedClass' => true,
			'hook:unrelated_hook'      => true,
		);

		$findings = $this->scorer()->score( array( $this->full_detector() ), $evidence );

		$this->assertSame( array(), $findings );
	}

	public function test_constant_alone_reaches_low(): void {
		$detector = $this->full_detector();
		$evidence = array( 'constant:EXAMPLE_VERSION' => true );

		$findings = $this->scorer()->score( array( $detector ), $evidence );

		$this->assertCount( 1, $findings );
		$this->assertSame( 25, $findings[0]->score() );
		$this->assertSame( Confidence::LOW, $findings[0]->confidence() );
	}

	public function test_a_detectors_label_never_influences_its_own_score(): void {
		// The label happens to contain the word this detector is meant to
		// catch elsewhere — proving scoring reads signatures, never text.
		$detector = new Detector(
			'unrelated',
			'Totally Unrelated Currency Plugin',
			array( new Signature( SignatureKind::HOOK, 'unrelated_hook', 10 ) )
		);

		$findings = $this->scorer()->score( array( $detector ), array( 'hook:some_other_hook' => true ) );

		$this->assertSame( array(), $findings );
	}

	// ------------------------------------------------------------------
	// #7: multiple detectors, deterministic ordering.
	// ------------------------------------------------------------------

	public function test_two_detectors_matching_produce_two_findings_score_desc_then_id_asc(): void {
		$zebra = new Detector( 'zebra', 'Zebra', array( new Signature( SignatureKind::PLUGIN_PATH, 'zebra/index.php', 60 ) ) );
		$alpha = new Detector( 'alpha', 'Alpha', array( new Signature( SignatureKind::PLUGIN_PATH, 'alpha/index.php', 60 ) ) );

		$evidence = array(
			'plugin_path:zebra/index.php' => true,
			'plugin_path:alpha/index.php' => true,
		);

		$findings = $this->scorer()->score( array( $zebra, $alpha ), $evidence );

		$this->assertCount( 2, $findings );
		// Equal score (60 each): tie-broken by id ascending, not input order.
		$this->assertSame( 'alpha', $findings[0]->id() );
		$this->assertSame( 'zebra', $findings[1]->id() );
	}

	public function test_findings_are_ordered_by_score_descending_before_id(): void {
		$low  = new Detector( 'aaa-low', 'Low', array( new Signature( SignatureKind::HOOK, 'low_hook', 10 ) ) );
		$high = new Detector( 'zzz-high', 'High', array( new Signature( SignatureKind::PLUGIN_PATH, 'high/index.php', 60 ) ) );

		$evidence = array(
			'hook:low_hook'              => true,
			'plugin_path:high/index.php' => true,
		);

		$findings = $this->scorer()->score( array( $low, $high ), $evidence );

		$this->assertCount( 2, $findings );
		$this->assertSame( 'zzz-high', $findings[0]->id(), 'Higher score must sort first regardless of id.' );
		$this->assertSame( 'aaa-low', $findings[1]->id() );
	}

	// ------------------------------------------------------------------
	// #8: score boundaries, both sides.
	// ------------------------------------------------------------------

	/**
	 * @dataProvider boundary_weight_combinations
	 */
	public function test_score_boundaries_on_both_sides( array $weights, int $expected_score, string $expected_confidence ): void {
		$signatures = array();
		$evidence   = array();

		foreach ( $weights as $i => $weight ) {
			$kind                            = SignatureKind::HOOK;
			$needle                          = "boundary_hook_{$i}";
			$signatures[]                    = new Signature( $kind, $needle, $weight );
			$evidence[ "{$kind}:{$needle}" ] = true;
		}

		$detector = new Detector( 'boundary', 'Boundary', $signatures );
		$findings = $this->scorer()->score( array( $detector ), $evidence, Confidence::NONE === $expected_confidence ? Confidence::LOW : $expected_confidence );

		if ( Confidence::NONE === $expected_confidence ) {
			$this->assertSame( array(), $findings, 'A score below the LOW threshold must never be reported.' );

			return;
		}

		$this->assertCount( 1, $findings );
		$this->assertSame( $expected_score, $findings[0]->score() );
		$this->assertSame( $expected_confidence, $findings[0]->confidence() );
	}

	/**
	 * @return array<string, array{0: array<int, int>, 1: int, 2: string}>
	 */
	public static function boundary_weight_combinations(): array {
		return array(
			'9 is none'    => array( array( 9 ), 9, Confidence::NONE ),
			'10 is low'    => array( array( 10 ), 10, Confidence::LOW ),
			'29 is low'    => array( array( 29 ), 29, Confidence::LOW ),
			'30 is medium' => array( array( 30 ), 30, Confidence::MEDIUM ),
			'59 is medium' => array( array( 59 ), 59, Confidence::MEDIUM ),
			'60 is high'   => array( array( 60 ), 60, Confidence::HIGH ),
		);
	}

	public function test_score_clamps_at_one_hundred(): void {
		$signatures = array(
			new Signature( SignatureKind::HOOK, 'clamp_hook_a', 60 ),
			new Signature( SignatureKind::HOOK, 'clamp_hook_b', 60 ),
		);
		$evidence   = array(
			'hook:clamp_hook_a' => true,
			'hook:clamp_hook_b' => true,
		);

		$findings = $this->scorer()->score( array( new Detector( 'clamp', 'Clamp', $signatures ) ), $evidence );

		$this->assertCount( 1, $findings );
		$this->assertSame( 100, $findings[0]->score() );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
	}

	// ------------------------------------------------------------------
	// #11: monotonicity.
	// ------------------------------------------------------------------

	public function test_adding_a_matched_signature_never_lowers_the_confidence_level(): void {
		$detector = $this->full_detector();
		$all_keys = array(
			'plugin_path:example/index.php',
			'class:Example_example',
			'function:example_function',
			'constant:EXAMPLE_VERSION',
			'shortcode:example',
			'hook:example_hook',
		);

		$previous_rank = -1;

		foreach ( $all_keys as $count => $key ) {
			$evidence = array_fill_keys( array_slice( $all_keys, 0, $count + 1 ), true );

			$findings = $this->scorer()->score( array( $detector ), $evidence );
			$rank     = array() === $findings ? Confidence::RANK[ Confidence::NONE ] : Confidence::RANK[ $findings[0]->confidence() ];

			$this->assertGreaterThanOrEqual( $previous_rank, $rank, 'Adding matched evidence must never lower the confidence rank.' );
			$previous_rank = $rank;
		}
	}

	// ------------------------------------------------------------------
	// Minimum-confidence filtering and input validation.
	// ------------------------------------------------------------------

	public function test_minimum_confidence_filters_out_lower_findings(): void {
		$detector = $this->full_detector();
		$evidence = array( 'constant:EXAMPLE_VERSION' => true ); // LOW.

		$this->assertCount( 1, $this->scorer()->score( array( $detector ), $evidence, Confidence::LOW ) );
		$this->assertSame( array(), $this->scorer()->score( array( $detector ), $evidence, Confidence::MEDIUM ) );
	}

	public function test_rejects_an_unknown_minimum_confidence(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->scorer()->score( array( $this->full_detector() ), array(), 'critical' );
	}

	public function test_rejects_a_non_detector_element(): void {
		$this->expectException( InvalidArgumentException::class );
		// @phpstan-ignore-next-line intentionally malformed for the test.
		$this->scorer()->score( array( 'not a detector' ), array() );
	}
}
