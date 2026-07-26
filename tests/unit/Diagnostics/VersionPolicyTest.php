<?php
/**
 * Unit tests for VersionPolicy::evaluate().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\VersionPolicy;

/**
 * Covers below/at/supported/above/unparseable classification.
 */
final class VersionPolicyTest extends TestCase {

	private function policy(): VersionPolicy {
		return new VersionPolicy();
	}

	/**
	 * @dataProvider evaluation_cases
	 */
	public function test_evaluate_classifies_correctly( string $running, string $floor, string $tested, string $expected ): void {
		$this->assertSame( $expected, $this->policy()->evaluate( $running, $floor, $tested ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function evaluation_cases(): array {
		return array(
			'below the floor'       => array( '8.0', '8.1', '8.4', VersionPolicy::BELOW_FLOOR ),
			'exactly at the floor'  => array( '8.1', '8.1', '8.4', VersionPolicy::AT_FLOOR ),
			'comfortably supported' => array( '8.3', '8.1', '8.4', VersionPolicy::SUPPORTED ),
			'exactly at tested'     => array( '8.4', '8.1', '8.4', VersionPolicy::SUPPORTED ),
			'above tested'          => array( '8.5', '8.1', '8.4', VersionPolicy::ABOVE_TESTED ),
			'unparseable running'   => array( 'not-a-version', '8.1', '8.4', VersionPolicy::UNPARSEABLE ),
			'empty running'         => array( '', '8.1', '8.4', VersionPolicy::UNPARSEABLE ),
			'unparseable floor'     => array( '8.3', 'not-a-version', '8.4', VersionPolicy::UNPARSEABLE ),
			'unparseable tested'    => array( '8.3', '8.1', 'not-a-version', VersionPolicy::UNPARSEABLE ),
		);
	}

	public function test_evaluate_never_reports_unparseable_input_as_supported(): void {
		$this->assertNotSame( VersionPolicy::SUPPORTED, $this->policy()->evaluate( 'garbage', '8.1', '8.4' ) );
	}

	public function test_all_lists_every_possible_status(): void {
		$this->assertSame(
			array(
				VersionPolicy::BELOW_FLOOR,
				VersionPolicy::AT_FLOOR,
				VersionPolicy::SUPPORTED,
				VersionPolicy::ABOVE_TESTED,
				VersionPolicy::UNPARSEABLE,
			),
			VersionPolicy::ALL
		);
	}

	public function test_evaluate_accepts_a_patch_level_running_version(): void {
		$this->assertSame( VersionPolicy::SUPPORTED, $this->policy()->evaluate( '8.2.5', '8.1', '8.4' ) );
	}

	public function test_evaluate_never_throws_on_malformed_input(): void {
		$this->assertSame( VersionPolicy::UNPARSEABLE, $this->policy()->evaluate( '<script>', '8.1', '8.4' ) );
		$this->assertSame( VersionPolicy::UNPARSEABLE, $this->policy()->evaluate( '8.1.2.3.4', '8.1', '8.4' ) );
	}
}
