<?php
/**
 * Integration tests for WooCommerceCurrencyProvider.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Currency;

use UMC\Currency\WooCommerceCurrencyProvider;
use WP_UnitTestCase;

/**
 * Verifies WooCommerce-backed currency metadata lookup and search.
 */
final class WooCommerceCurrencyProviderTest extends WP_UnitTestCase {

	public function test_get_returns_usd_metadata_from_woocommerce(): void {
		$provider = new WooCommerceCurrencyProvider();
		$metadata = $provider->get( 'USD' );

		$this->assertNotNull( $metadata );
		$this->assertSame( 'USD', $metadata->code() );
		$this->assertNotSame( '', $metadata->name() );
		$this->assertStringContainsString( 'USD', $metadata->option_label() );
	}

	public function test_search_matches_partial_currency_names(): void {
		$provider = new WooCommerceCurrencyProvider();
		$matches  = $provider->search( 'dol' );

		$this->assertArrayHasKey( 'USD', $matches );
		$this->assertArrayHasKey( 'AUD', $matches );
		$this->assertArrayHasKey( 'CAD', $matches );
	}

	public function test_is_known_rejects_unknown_code(): void {
		$provider = new WooCommerceCurrencyProvider();

		$this->assertFalse( $provider->is_known( 'ZZZ' ) );
	}
}
