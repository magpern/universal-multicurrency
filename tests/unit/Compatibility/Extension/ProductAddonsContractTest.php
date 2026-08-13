<?php
/**
 * Product Add-Ons hook contract tests (E1).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;
use UMC\Compatibility\Extension\ExtensionEvidenceTier;
use UMC\Compatibility\Extension\ExtensionCompatibilityRegistry;

/**
 * Validates Product Add-Ons uses documented public hook contract.
 */
final class ProductAddonsContractTest extends TestCase {

	public function test_product_addons_registry_is_characterized_e2(): void {
		$definition = null;
		foreach ( ExtensionCompatibilityRegistry::definitions() as $item ) {
			if ( 'woocommerce_product_addons' === ( $item['id'] ?? '' ) ) {
				$definition = $item;
				break;
			}
		}

		$this->assertNotNull( $definition );
		$this->assertSame( ExtensionCompatibilityStatus::CHARACTERIZED, $definition['status'] );
		$this->assertSame( ExtensionEvidenceTier::E2, $definition['evidence_tier'] );
		$this->assertContains( 'flat_fee_addon', (array) ( $definition['surfaces'] ?? array() ) );
	}
}
