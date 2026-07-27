<?php
/**
 * Unit tests for the closed evidence-kind set.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\SignatureKind;

/**
 * Covers the admissible kinds, their default weights and needle patterns.
 */
final class SignatureKindTest extends TestCase {

	public function test_all_lists_exactly_the_six_admissible_kinds(): void {
		$this->assertSame(
			array( 'plugin_path', 'class', 'function', 'constant', 'shortcode', 'hook' ),
			SignatureKind::ALL
		);
	}

	/**
	 * @dataProvider valid_kinds
	 */
	public function test_is_valid_accepts_every_admissible_kind( string $kind ): void {
		$this->assertTrue( SignatureKind::is_valid( $kind ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function valid_kinds(): array {
		return array(
			'plugin_path' => array( 'plugin_path' ),
			'class'       => array( 'class' ),
			'function'    => array( 'function' ),
			'constant'    => array( 'constant' ),
			'shortcode'   => array( 'shortcode' ),
			'hook'        => array( 'hook' ),
		);
	}

	public function test_is_valid_rejects_an_unknown_kind(): void {
		$this->assertFalse( SignatureKind::is_valid( 'option' ) );
		$this->assertFalse( SignatureKind::is_valid( 'cookie' ) );
		$this->assertFalse( SignatureKind::is_valid( 'session' ) );
		$this->assertFalse( SignatureKind::is_valid( '' ) );
	}

	/**
	 * @dataProvider valid_kinds
	 */
	public function test_default_weight_matches_the_documented_schedule( string $kind ): void {
		$expected = array(
			'plugin_path' => 60,
			'class'       => 40,
			'function'    => 30,
			'constant'    => 25,
			'shortcode'   => 15,
			'hook'        => 10,
		);

		$this->assertSame( $expected[ $kind ], SignatureKind::default_weight( $kind ) );
	}

	public function test_default_weight_rejects_an_unknown_kind(): void {
		$this->expectException( InvalidArgumentException::class );
		SignatureKind::default_weight( 'option' );
	}

	public function test_max_weight_equals_the_highest_default_weight(): void {
		$this->assertSame( SignatureKind::MAX_WEIGHT, max( SignatureKind::DEFAULT_WEIGHTS ) );
	}

	public function test_needle_pattern_rejects_an_unknown_kind(): void {
		$this->expectException( InvalidArgumentException::class );
		SignatureKind::needle_pattern( 'option' );
	}

	/**
	 * @dataProvider needle_pattern_cases
	 */
	public function test_needle_pattern_accepts_and_rejects_as_expected( string $kind, string $needle, bool $expected ): void {
		$matches = 1 === preg_match( SignatureKind::needle_pattern( $kind ), $needle );

		$this->assertSame( $expected, $matches );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public static function needle_pattern_cases(): array {
		return array(
			'plugin_path valid'        => array( 'plugin_path', 'woocommerce-currency-switcher/index.php', true ),
			'plugin_path no slash'     => array( 'plugin_path', 'index.php', false ),
			'plugin_path no extension' => array( 'plugin_path', 'woocommerce-currency-switcher/index', false ),
			'class valid'              => array( 'class', 'WOOCS', true ),
			'class namespaced'         => array( 'class', 'Vendor\\Plugin\\Main', true ),
			'class leading digit'      => array( 'class', '1WOOCS', false ),
			'function valid'           => array( 'function', 'woocs_get_current_currency', true ),
			'function leading digit'   => array( 'function', '1woocs', false ),
			'constant valid'           => array( 'constant', 'WOOCS_VERSION', true ),
			'hook valid'               => array( 'hook', 'woocs_currency_rate', true ),
			'hook too short'           => array( 'hook', 'a', false ),
			'shortcode valid'          => array( 'shortcode', 'woocs', true ),
			'shortcode too short'      => array( 'shortcode', 'a', false ),
		);
	}
}
