<?php
/**
 * Extension compatibility record unit tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionCharacterizedSubLabel;
use UMC\Compatibility\Extension\ExtensionCompatibilityRecord;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;
use UMC\Compatibility\Extension\ExtensionEvidenceTier;

/**
 * Unit tests for ExtensionCompatibilityRecord validation.
 */
final class ExtensionCompatibilityRecordTest extends TestCase {

	public function test_integrated_requires_e3(): void {
		$this->expectException( \InvalidArgumentException::class );
		new ExtensionCompatibilityRecord(
			'test',
			'Test',
			ExtensionCompatibilityStatus::INTEGRATED,
			ExtensionEvidenceTier::E2,
			'1.0.0',
			'2.0.0',
			true,
			true,
			true
		);
	}

	public function test_characterized_merchant_line_e2(): void {
		$record = new ExtensionCompatibilityRecord(
			'woocommerce_subscriptions',
			'WooCommerce Subscriptions',
			ExtensionCompatibilityStatus::CHARACTERIZED,
			ExtensionEvidenceTier::E2,
			'',
			'9.0.0',
			true,
			true,
			true
		);

		$this->assertSame(
			ExtensionCharacterizedSubLabel::SIMULATED_HOOKS,
			$record->merchant_status_line()
		);
	}

	public function test_untested_version_detection(): void {
		$record = new ExtensionCompatibilityRecord(
			'test',
			'Test',
			ExtensionCompatibilityStatus::CHARACTERIZED,
			ExtensionEvidenceTier::E2,
			'8.0.0',
			'9.2.0',
			true,
			true,
			true
		);

		$this->assertTrue( $record->is_untested_version() );
	}
}
