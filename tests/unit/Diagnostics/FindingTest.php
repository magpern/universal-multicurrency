<?php
/**
 * Unit tests for the Finding value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\Finding;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;

/**
 * Validates construction and the to_array() projection.
 */
final class FindingTest extends TestCase {

	public function test_valid_construction_exposes_its_parts(): void {
		$signature = new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 60 );
		$finding   = new Finding( 'example', 'Example', 60, Confidence::HIGH, array( $signature ) );

		$this->assertSame( 'example', $finding->id() );
		$this->assertSame( 'Example', $finding->label() );
		$this->assertSame( 60, $finding->score() );
		$this->assertSame( Confidence::HIGH, $finding->confidence() );
		$this->assertSame( array( $signature ), $finding->matched() );
	}

	public function test_rejects_an_empty_id(): void {
		$this->expectException( InvalidArgumentException::class );
		new Finding( '', 'Example', 60, Confidence::HIGH, array() );
	}

	public function test_rejects_an_empty_label(): void {
		$this->expectException( InvalidArgumentException::class );
		new Finding( 'example', '', 60, Confidence::HIGH, array() );
	}

	public function test_rejects_a_negative_score(): void {
		$this->expectException( InvalidArgumentException::class );
		new Finding( 'example', 'Example', -1, Confidence::HIGH, array() );
	}

	public function test_rejects_a_score_over_one_hundred(): void {
		$this->expectException( InvalidArgumentException::class );
		new Finding( 'example', 'Example', 101, Confidence::HIGH, array() );
	}

	public function test_rejects_an_unknown_confidence_level(): void {
		$this->expectException( InvalidArgumentException::class );
		new Finding( 'example', 'Example', 60, 'critical', array() );
	}

	public function test_rejects_a_non_signature_element_in_matched(): void {
		$this->expectException( InvalidArgumentException::class );
		// @phpstan-ignore-next-line intentionally malformed for the test.
		new Finding( 'example', 'Example', 60, Confidence::HIGH, array( 'not a signature' ) );
	}

	public function test_to_array_projects_matched_signatures_as_plain_arrays(): void {
		$signature = new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 60 );
		$finding   = new Finding( 'example', 'Example', 60, Confidence::HIGH, array( $signature ) );

		$this->assertSame(
			array(
				'id'         => 'example',
				'label'      => 'Example',
				'score'      => 60,
				'confidence' => Confidence::HIGH,
				'matched'    => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'example/index.php',
						'weight' => 60,
					),
				),
			),
			$finding->to_array()
		);
	}
}
