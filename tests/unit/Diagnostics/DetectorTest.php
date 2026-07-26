<?php
/**
 * Unit tests for the Detector value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Detector;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;

/**
 * Validates construction, its invariants, max_score() and the defensive
 * copy returned by signatures().
 */
final class DetectorTest extends TestCase {

	/**
	 * Builds a detector with sensible defaults for the given overrides.
	 *
	 * @param string                     $id         Detector id.
	 * @param string                     $label      Detector label.
	 * @param array<int, Signature>|null $signatures Signatures, or null for a single default signature.
	 */
	private function detector( string $id = 'example', string $label = 'Example', ?array $signatures = null ): Detector {
		return new Detector( $id, $label, $signatures ?? array( new Signature( SignatureKind::CLASS_NAME, 'Example', 40 ) ) );
	}

	public function test_valid_construction_exposes_its_parts(): void {
		$signature = new Signature( SignatureKind::CLASS_NAME, 'Example', 40 );
		$detector  = new Detector( 'example', 'Example Detector', array( $signature ) );

		$this->assertSame( 'example', $detector->id() );
		$this->assertSame( 'Example Detector', $detector->label() );
		$this->assertSame( array( $signature ), $detector->signatures() );
	}

	public function test_rejects_an_id_that_does_not_match_the_pattern(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'E' );
	}

	public function test_rejects_an_empty_label(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'example', '   ' );
	}

	public function test_rejects_a_label_over_the_length_limit(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'example', str_repeat( 'a', 121 ) );
	}

	public function test_rejects_zero_signatures(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'example', 'Example', array() );
	}

	public function test_rejects_more_than_the_maximum_signatures(): void {
		$signatures = array();

		for ( $i = 0; $i < Detector::MAX_SIGNATURES + 1; $i++ ) {
			$signatures[] = new Signature( SignatureKind::HOOK, "example_hook_{$i}", 10 );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'example', 'Example', $signatures );
	}

	public function test_accepts_exactly_the_maximum_signatures(): void {
		$signatures = array();

		for ( $i = 0; $i < Detector::MAX_SIGNATURES; $i++ ) {
			$signatures[] = new Signature( SignatureKind::HOOK, "example_hook_{$i}", 10 );
		}

		$detector = $this->detector( 'example', 'Example', $signatures );

		$this->assertCount( Detector::MAX_SIGNATURES, $detector->signatures() );
	}

	public function test_rejects_a_non_signature_element(): void {
		$this->expectException( InvalidArgumentException::class );
		// @phpstan-ignore-next-line intentionally malformed for the test.
		$this->detector( 'example', 'Example', array( 'not a signature' ) );
	}

	public function test_rejects_duplicate_signatures(): void {
		$one = new Signature( SignatureKind::HOOK, 'example_hook', 10 );
		$two = new Signature( SignatureKind::HOOK, 'example_hook', 10 );

		$this->expectException( InvalidArgumentException::class );
		$this->detector( 'example', 'Example', array( $one, $two ) );
	}

	public function test_max_score_sums_and_clamps_to_one_hundred(): void {
		$detector = $this->detector(
			'example',
			'Example',
			array(
				new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 60 ),
				new Signature( SignatureKind::CLASS_NAME, 'Example', 40 ),
			)
		);

		$this->assertSame( 100, $detector->max_score() );

		$overflowing = $this->detector(
			'example',
			'Example',
			array(
				new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 60 ),
				new Signature( SignatureKind::CLASS_NAME, 'Example', 40 ),
				new Signature( SignatureKind::FUNCTION, 'example_function', 30 ),
			)
		);

		$this->assertSame( 100, $overflowing->max_score() );
	}

	public function test_signatures_returns_a_defensive_copy(): void {
		$signature = new Signature( SignatureKind::CLASS_NAME, 'Example', 40 );
		$detector  = new Detector( 'example', 'Example', array( $signature ) );

		$copy   = $detector->signatures();
		$copy[] = new Signature( SignatureKind::HOOK, 'example_hook', 10 );

		$this->assertCount( 1, $detector->signatures(), 'Mutating the returned array must not affect the detector.' );
	}
}
