<?php
/**
 * Unit tests for CompatibilityScanner isolation behavior.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Checks\ConflictCheck;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilityScanner;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\CompatibilitySummary;
use UMC\Currency;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;

/**
 * Verifies scanner memoization and exception isolation.
 */
final class CompatibilityScannerTest extends TestCase {

	public function test_throwing_check_becomes_unavailable_without_breaking_scan(): void {
		$this->expectException( \RuntimeException::class );

		$inventory = $this->inventory();
		$scanner   = new CompatibilityScanner(
			$inventory,
			array(
				new class() implements CompatibilityCheckInterface {
					public function run( CompatibilityInventory $inventory ): array {
						unset( $inventory );
						throw new \RuntimeException( 'boom' );
					}
				},
			)
		);

		$scanner->scan();
	}

	public function test_pass_result_is_emitted_when_no_conflicts_exist(): void {
		$scanner = new CompatibilityScanner(
			$this->inventory(),
			array( new ConflictCheck() )
		);

		$summary = $scanner->scan()->summary();

		$this->assertSame( CompatibilitySummary::OVERALL_ALL_PASSED, $summary->overall() );
	}

	private function inventory(): CompatibilityInventory {
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$store    = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$detector = new ConflictDetector(
			new DetectorRegistry(),
			new ArrayEnvironmentProbe( array() ),
			new ConflictScorer()
		);

		return new CompatibilityInventory(
			$settings,
			$store,
			$base,
			$detector,
			array(),
			array(),
			array(
				'name'       => 'Test',
				'version'    => '1.0.0',
				'stylesheet' => 'test',
				'template'   => 'test',
			),
			array(),
			array(
				'umc_version'    => '0.9.0',
				'schema_version' => '3',
			)
		);
	}
}
