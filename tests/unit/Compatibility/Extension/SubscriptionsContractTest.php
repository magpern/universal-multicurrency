<?php
/**
 * Subscriptions safe-invariant contract tests (E1).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionCompatibilityContext;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;
use UMC\Compatibility\Extension\ExtensionEvidenceTier;
use UMC\Compatibility\Extension\ExtensionCompatibilityRegistry;

/**
 * Documents the browsing-currency isolation invariant for renewals.
 */
final class SubscriptionsContractTest extends TestCase {

	protected function tearDown(): void {
		ExtensionCompatibilityContext::reset();
		ExtensionCompatibilityRegistry::reset();
		parent::tearDown();
	}

	public function test_renewal_context_suppresses_browsing_conversion(): void {
		ExtensionCompatibilityContext::enter_renewal_context( 'USD' );

		$this->assertTrue( ExtensionCompatibilityContext::should_suppress_browsing_conversion() );
		$this->assertSame( 'USD', ExtensionCompatibilityContext::renewal_currency() );

		ExtensionCompatibilityContext::exit_renewal_context();

		$this->assertFalse( ExtensionCompatibilityContext::should_suppress_browsing_conversion() );
	}

	public function test_subscriptions_registry_is_characterized_e2(): void {
		$definition = null;
		foreach ( ExtensionCompatibilityRegistry::definitions() as $item ) {
			if ( 'woocommerce_subscriptions' === ( $item['id'] ?? '' ) ) {
				$definition = $item;
				break;
			}
		}

		$this->assertNotNull( $definition );
		$this->assertSame( ExtensionCompatibilityStatus::CHARACTERIZED, $definition['status'] );
		$this->assertSame( ExtensionEvidenceTier::E2, $definition['evidence_tier'] );
	}

	public function test_e2_scope_documents_renewal_rate_policy_pending(): void {
		$definition = null;
		foreach ( ExtensionCompatibilityRegistry::definitions() as $item ) {
			if ( 'woocommerce_subscriptions' === ( $item['id'] ?? '' ) ) {
				$definition = $item;
				break;
			}
		}

		$this->assertNotNull( $definition );
		$limitations = implode( ' ', (array) ( $definition['limitations'] ?? array() ) );
		$this->assertStringContainsString( 'E3', $limitations );
		$this->assertStringContainsString( 'browsing-currency isolation', $limitations );
	}
}
