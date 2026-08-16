<?php
/**
 * M26 WP3 full-system acceptance: an M25 CSV-authored fixed price and an
 * M24 catalog-wide seed/clear operation share the same repository and
 * merge authority -- this proves neither clobbers the other across
 * currencies, and that clear() stays scoped to its own currency.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CrossFeature;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocumentMerger;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * ADR-0030 documents that CSV import and the M24 catalog service share one
 * mutation authority (`FixedPriceDocumentMerger`/`FixedPriceRepository`) by
 * design. No existing suite proves what happens when both act on the same
 * product for different currencies in one session.
 *
 * @covers \UMC\Pricing\FixedPriceDocumentMerger
 * @covers \UMC\Pricing\FixedPriceCatalogOperationsService
 */
final class CsvImportCatalogOperationsInteractionTest extends M20PricingTestCase {

	public function test_csv_authored_price_survives_a_catalog_seed_for_a_different_currency(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'DKK' => array( 'rate' => '7.45' ),
			),
			'EUR'
		);

		$product = $this->simple_product( '100' );

		// Simulate a WooCommerce CSV import authoring a SEK fixed price --
		// the exact same seam FixedPriceCsvIntegration's import hook uses.
		( new FixedPriceDocumentMerger( $this->repository ) )->merge_and_save(
			$product->get_id(),
			array( 'SEK' => array( 'regular' => '1150.00' ) ),
			'EUR'
		);

		// M24 catalog operation seeds a *different* currency (DKK) in bulk.
		$this->catalog_service()->seed( array( wc_get_product( $product->get_id() ) ), 'DKK' );

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular(), 'The catalog seed for DKK must not disturb the CSV-authored SEK price.' );
		$this->assertSame( '745.00', $document->get_currency( 'DKK' )?->regular(), 'The catalog seed must still correctly author DKK from the FX rate.' );
	}

	public function test_catalog_clear_is_scoped_to_its_own_currency_and_leaves_csv_authored_currencies_intact(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'DKK' => array( 'rate' => '7.45' ),
			),
			'EUR'
		);

		$product = $this->simple_product( '100' );

		( new FixedPriceDocumentMerger( $this->repository ) )->merge_and_save(
			$product->get_id(),
			array(
				'SEK' => array( 'regular' => '1150.00' ),
				'DKK' => array( 'regular' => '800.00' ),
			),
			'EUR'
		);

		$result = $this->catalog_service()->clear( array( wc_get_product( $product->get_id() ) ), 'SEK' );

		$this->assertFalse( $result->is_aborted() );
		$document = $this->repository->get( $product->get_id() );
		$this->assertNull( $document->get_currency( 'SEK' ), 'clear() must remove the targeted currency.' );
		$this->assertSame( '800.00', $document->get_currency( 'DKK' )?->regular(), 'clear() for SEK must never touch the CSV-authored DKK currency.' );
	}

	private function catalog_service(): FixedPriceCatalogOperationsService {
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		return new FixedPriceCatalogOperationsService(
			$this->repository,
			new FixedPriceCoverageReport( $this->repository ),
			$rates,
			$this->counting_converter,
			$registry
		);
	}
}
