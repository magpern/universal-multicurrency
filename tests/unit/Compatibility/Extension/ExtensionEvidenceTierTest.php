<?php
/**
 * Extension evidence tier unit tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionCharacterizedSubLabel;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;
use UMC\Compatibility\Extension\ExtensionEvidenceTier;

/**
 * Unit tests for extension evidence tier ceilings.
 */
final class ExtensionEvidenceTierTest extends TestCase {

	public function test_max_status_by_tier(): void {
		$this->assertSame(
			ExtensionCompatibilityStatus::NOT_EVALUATED,
			ExtensionEvidenceTier::max_status( ExtensionEvidenceTier::E0 )
		);
		$this->assertSame(
			ExtensionCompatibilityStatus::CHARACTERIZED,
			ExtensionEvidenceTier::max_status( ExtensionEvidenceTier::E1 )
		);
		$this->assertSame(
			ExtensionCompatibilityStatus::CHARACTERIZED,
			ExtensionEvidenceTier::max_status( ExtensionEvidenceTier::E2 )
		);
		$this->assertSame(
			ExtensionCompatibilityStatus::INTEGRATED,
			ExtensionEvidenceTier::max_status( ExtensionEvidenceTier::E3 )
		);
	}

	public function test_merchant_sub_labels(): void {
		$this->assertSame(
			ExtensionCharacterizedSubLabel::CONTRACT_TESTS,
			ExtensionEvidenceTier::merchant_sub_label( ExtensionEvidenceTier::E1 )
		);
		$this->assertSame(
			ExtensionCharacterizedSubLabel::SIMULATED_HOOKS,
			ExtensionEvidenceTier::merchant_sub_label( ExtensionEvidenceTier::E2 )
		);
	}
}
