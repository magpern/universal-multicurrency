<?php
/**
 * Evidence tier and status ceiling guards.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionCharacterizedSubLabel;
use UMC\Compatibility\Extension\ExtensionCompatibilityRegistry;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;
use UMC\Compatibility\Extension\ExtensionEvidenceTier;

/**
 * Ensures registry definitions never overclaim Integrated without E3.
 */
final class ExtensionEvidenceTest extends TestCase {

	public function test_integrated_status_requires_e3_tier(): void {
		$integrated = 0;
		foreach ( ExtensionCompatibilityRegistry::definitions() as $definition ) {
			$status = (string) ( $definition['status'] ?? '' );
			$tier   = (string) ( $definition['evidence_tier'] ?? '' );

			if ( ExtensionCompatibilityStatus::INTEGRATED === $status ) {
				++$integrated;
				$this->assertSame(
					ExtensionEvidenceTier::E3,
					$tier,
					sprintf(
						'Extension %s claims Integrated but evidence tier is %s.',
						(string) ( $definition['id'] ?? 'unknown' ),
						$tier
					)
				);
			}
		}

		$this->assertSame( 0, $integrated, 'M19 registry must not claim Integrated without E3 evidence.' );
	}

	public function test_e1_and_e2_never_produce_integrated_merchant_label(): void {
		foreach ( array( ExtensionEvidenceTier::E1, ExtensionEvidenceTier::E2 ) as $tier ) {
			$line = ExtensionEvidenceTier::merchant_status_line(
				ExtensionCompatibilityStatus::CHARACTERIZED,
				$tier
			);
			$this->assertNotSame(
				ExtensionCharacterizedSubLabel::REAL_EXTENSION,
				$line
			);
			$this->assertContains(
				$line,
				ExtensionCharacterizedSubLabel::CHARACTERIZED_LABELS
			);
		}
	}

	public function test_e3_integrated_merchant_label(): void {
		$line = ExtensionEvidenceTier::merchant_status_line(
			ExtensionCompatibilityStatus::INTEGRATED,
			ExtensionEvidenceTier::E3
		);
		$this->assertSame( ExtensionCharacterizedSubLabel::REAL_EXTENSION, $line );
	}

	public function test_characterized_definitions_have_evidence_tests(): void {
		foreach ( ExtensionCompatibilityRegistry::definitions() as $definition ) {
			$status = (string) ( $definition['status'] ?? '' );
			if ( ExtensionCompatibilityStatus::CHARACTERIZED !== $status ) {
				continue;
			}

			$tests = (array) ( $definition['evidence_tests'] ?? array() );
			$this->assertNotEmpty(
				$tests,
				sprintf(
					'Characterized extension %s must cite evidence test classes.',
					(string) ( $definition['id'] ?? 'unknown' )
				)
			);
		}
	}
}
