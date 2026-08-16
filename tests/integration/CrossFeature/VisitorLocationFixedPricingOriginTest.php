<?php
/**
 * M26 WP3 full-system acceptance: Visitor Location currency selection
 * combined with an authoritative fixed price, through a real cart/checkout
 * write, read back through the reporting-layer classifiers.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CrossFeature;

use UMC\CurrencySwitcher;
use UMC\Order\LineItemPriceProvenance;
use UMC\Order\OrderSnapshotReader;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\ProductPriceResolution;
use UMC\Reporting\ReportingOriginClassifier;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * No single milestone's suite exercises Visitor-Location-selected currency
 * together with an M20 fixed price flowing through a real M3 cart/checkout
 * write and back out through the M21 reporting-layer classifiers in one
 * pass. This proves that combination end-to-end through production code,
 * not synthetic order fixtures.
 *
 * @covers \UMC\Order\OrderSnapshot
 * @covers \UMC\Order\LineItemPriceProvenance
 * @covers \UMC\Reporting\ReportingOriginClassifier
 */
final class VisitorLocationFixedPricingOriginTest extends M20PricingTestCase {

	public function test_visitor_location_currency_on_fixed_priced_product_reports_correct_source_and_origin(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );

		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '999.00' ) ), 'EUR' )
		);

		// Visitor Location resolved SEK for this shopper -- not a manual pick.
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, CurrencySwitcher::ORIGIN_VISITOR_LOCATION );

		$this->add_to_cart( $product->get_id() );
		$order = $this->create_order_from_cart();

		// Pricing layer: the line was priced from the authoritative fixed
		// document, not an FX conversion of the EUR base price.
		$provenance = $this->line_provenance( $order );
		$this->assertCount( 1, $provenance );
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $provenance[0]['source'] );
		$this->assertSame( 'SEK', $provenance[0]['currency'] );

		// Reporting layer: the order snapshot correctly attributes the
		// currency decision to Visitor Location, not a manual customer pick.
		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_VISITOR_LOCATION,
			( new ReportingOriginClassifier() )->classify( $snapshot )
		);
	}

	public function test_manual_selection_on_the_same_fixed_priced_product_reports_customer_origin(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK', true );

		$product = $this->simple_product( '100' );
		$this->save_fixed(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '999.00' ) ), 'EUR' )
		);

		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, CurrencySwitcher::ORIGIN_CUSTOMER );

		$this->add_to_cart( $product->get_id() );
		$order = $this->create_order_from_cart();

		$snapshot = ( new OrderSnapshotReader() )->read( $order );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_CUSTOMER,
			( new ReportingOriginClassifier() )->classify( $snapshot )
		);
		$this->assertSame( ProductPriceResolution::SOURCE_FIXED, $this->line_provenance( $order )[0]['source'] );
	}
}
