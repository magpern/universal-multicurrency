<?php
/**
 * Unit tests for the Signature value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;

/**
 * Validates construction and rejects malformed kinds, needles and weights.
 */
final class SignatureTest extends TestCase {

	public function test_valid_construction_exposes_its_parts(): void {
		$signature = new Signature( SignatureKind::CLASS_NAME, 'Example_Class', 40 );

		$this->assertSame( SignatureKind::CLASS_NAME, $signature->kind() );
		$this->assertSame( 'Example_Class', $signature->needle() );
		$this->assertSame( 40, $signature->weight() );
	}

	public function test_key_combines_kind_and_needle(): void {
		$signature = new Signature( SignatureKind::CONSTANT, 'EXAMPLE_VERSION', 25 );

		$this->assertSame( 'constant:EXAMPLE_VERSION', $signature->key() );
	}

	public function test_rejects_an_unknown_kind(): void {
		$this->expectException( InvalidArgumentException::class );
		new Signature( 'option', 'example_option', 25 );
	}

	public function test_rejects_an_empty_needle(): void {
		$this->expectException( InvalidArgumentException::class );
		new Signature( SignatureKind::CLASS_NAME, '', 40 );
	}

	/**
	 * @dataProvider malformed_needles
	 */
	public function test_rejects_a_needle_that_does_not_match_its_kind_pattern( string $kind, string $needle ): void {
		$this->expectException( InvalidArgumentException::class );
		new Signature( $kind, $needle, SignatureKind::default_weight( $kind ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function malformed_needles(): array {
		return array(
			'plugin_path without a slash'   => array( SignatureKind::PLUGIN_PATH, 'index.php' ),
			'class with a leading digit'    => array( SignatureKind::CLASS_NAME, '1Example' ),
			'function with a leading digit' => array( SignatureKind::FUNCTION, '1example' ),
			'hook too short'                => array( SignatureKind::HOOK, 'a' ),
			'shortcode too short'           => array( SignatureKind::SHORTCODE, 'a' ),
		);
	}

	public function test_rejects_a_weight_below_the_minimum(): void {
		$this->expectException( InvalidArgumentException::class );
		new Signature( SignatureKind::HOOK, 'example_hook', 0 );
	}

	public function test_rejects_a_weight_above_the_maximum(): void {
		$this->expectException( InvalidArgumentException::class );
		new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 61 );
	}

	public function test_accepts_the_minimum_weight(): void {
		$signature = new Signature( SignatureKind::HOOK, 'example_hook', SignatureKind::MIN_WEIGHT );

		$this->assertSame( SignatureKind::MIN_WEIGHT, $signature->weight() );
	}

	public function test_accepts_the_maximum_weight(): void {
		$signature = new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', SignatureKind::MAX_WEIGHT );

		$this->assertSame( SignatureKind::MAX_WEIGHT, $signature->weight() );
	}
}
