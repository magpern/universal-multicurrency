<?php
/**
 * M24 WP2 acceptance: fixed-price catalog seed/clear orchestration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Pricing;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceOperationResult;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\InvertingDisplayPriceConverter;
use UMC\Tests\Support\M20PricingTestCase;
use UMC\Tests\Support\SequentialRateProvider;

/**
 * @covers \UMC\Pricing\FixedPriceCatalogOperationsService
 */
final class FixedPriceCatalogOperationsServiceTest extends M20PricingTestCase {

	/**
	 * Registry built by {@see service()} for the most recent call.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Shared coverage/population resolver under test.
	 *
	 * @var FixedPriceCoverageReport
	 */
	private FixedPriceCoverageReport $coverage;

	public function set_up(): void {
		parent::set_up();

		$this->coverage = new FixedPriceCoverageReport( $this->repository );
	}

	/**
	 * Builds a service wired to the live Settings-backed rate provider and
	 * the sanctioned Converter seam (via {@see M20PricingTestCase::activate()}'s
	 * counting converter, itself wrapping the real PriceConversionService).
	 */
	private function service(): FixedPriceCatalogOperationsService {
		$settings       = new Settings();
		$this->registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates          = new ManualRateProvider( $settings, 'EUR' );

		return new FixedPriceCatalogOperationsService(
			$this->repository,
			$this->coverage,
			$rates,
			$this->counting_converter,
			$this->registry
		);
	}

	public function test_seed_converts_authored_regular_and_sale_using_resolved_rate(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100', '80' );

		$result = $this->service()->seed( array( $product ), 'SEK' );

		$this->assertFalse( $result->is_aborted() );
		$this->assertSame( array( $product->get_id() ), $result->succeeded() );
		$this->assertSame( '11.50', $result->rate_used() );

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '920.00', $document->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * M24 falsification O: seeding must read authored values, never
	 * get_price() or WooCommerce's current sale-active state. A product
	 * with an authored sale but no active sale schedule must still seed
	 * both amounts from the authored regular/sale fields.
	 */
	public function test_seed_uses_authored_values_not_get_price_or_sale_schedule(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );
		// Author a sale price but schedule it in the future — WC currently
		// considers the product NOT on sale, so get_price() === '100.00'.
		$product->set_sale_price( '80' );
		$product->set_date_on_sale_from( strtotime( '+1 year' ) );
		$product->save();

		$this->assertSame( '100', wc_get_product( $product->get_id() )->get_price() );

		$result = $this->service()->seed( array( wc_get_product( $product->get_id() ) ), 'SEK' );

		$document = $this->repository->get( $product->get_id() );
		$this->assertFalse( $result->is_aborted() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '920.00', $document->get_currency( 'SEK' )?->sale(), 'Authored sale must seed even while the WC sale schedule is inactive.' );
	}

	public function test_seed_skips_product_without_authored_regular_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();

		$result = $this->service()->seed( array( wc_get_product( $product->get_id() ) ), 'SEK' );

		$this->assertSame( array(), $result->succeeded() );
		$this->assertArrayHasKey( $product->get_id(), $result->skipped() );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * M24 falsification N: a variation's seed must never source from a
	 * sibling or the parent product.
	 */
	public function test_seed_is_variation_native_and_isolated(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$pair = $this->variable_product_pair( '50', '100' );

		$targets = array( wc_get_product( $pair['parent']->get_id() ) );
		$result  = $this->service()->seed( $targets, 'SEK' );

		$this->assertFalse( $result->is_aborted() );
		$this->assertSame( '575.00', $this->repository->get( $pair['low']->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '1150.00', $this->repository->get( $pair['high']->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertNull( $this->repository->get( $pair['parent']->get_id() )->get_currency( 'SEK' ), 'The variable parent itself must never receive a fixed-price document.' );
	}

	/**
	 * M24 falsification B: seed/clear must never target the base currency.
	 */
	public function test_seed_rejects_base_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->seed( array( $product ), 'EUR' );

		$this->assertTrue( $result->is_aborted() );
		$this->assertSame( FixedPriceOperationResult::ABORT_BASE_CURRENCY, $result->abort_reason() );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'EUR' ) );
	}

	public function test_seed_rejects_unknown_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->seed( array( $product ), 'XYZ' );

		$this->assertTrue( $result->is_aborted() );
		$this->assertSame( FixedPriceOperationResult::ABORT_UNKNOWN_CURRENCY, $result->abort_reason() );
	}

	public function test_seed_aborts_before_any_write_when_no_rate_available(): void {
		// SEK is configured (so it is a known, non-base currency) but has no
		// manual rate authored, so ManualRateProvider::get_rate() resolves null.
		$this->activate( array( 'SEK' => array( 'rate' => '' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->seed( array( $product ), 'SEK' );

		$this->assertTrue( $result->is_aborted() );
		$this->assertSame( FixedPriceOperationResult::ABORT_NO_RATE, $result->abort_reason() );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * M24 falsification S: one seed invocation must use exactly one FX rate
	 * across every product it processes, even when the underlying provider
	 * would return a different rate on each call.
	 */
	public function test_seed_uses_exactly_one_rate_snapshot_across_multiple_products(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );

		$changing_rates = new SequentialRateProvider( array( '11.50', '20.00', '99.00' ) );
		$service        = new FixedPriceCatalogOperationsService(
			$this->repository,
			$this->coverage,
			$changing_rates,
			$this->counting_converter,
			$registry
		);

		$product_a = $this->simple_product( '100' );
		$product_b = $this->simple_product( '200' );
		$product_c = $this->simple_product( '300' );

		$result = $service->seed( array( $product_a, $product_b, $product_c ), 'SEK' );

		$this->assertSame( '11.50', $result->rate_used() );
		$this->assertSame( 1, $changing_rates->call_count(), 'get_rate() must be called exactly once per operation.' );
		$this->assertSame( '1150.00', $this->repository->get( $product_a->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '2300.00', $this->repository->get( $product_b->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '3450.00', $this->repository->get( $product_c->get_id() )->get_currency( 'SEK' )?->regular() );
	}

	public function test_dry_run_seed_performs_zero_writes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->seed( array( $product ), 'SEK', false );

		$this->assertFalse( $result->is_aborted() );
		$this->assertSame( array( $product->get_id() ), $result->succeeded() );
		$this->assertSame( '11.50', $result->rate_used() );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ), 'Dry-run must not persist.' );
	}

	public function test_seed_output_has_document_parity_with_manual_authoring(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100', '80' );

		$this->service()->seed( array( $product ), 'SEK' );
		$seeded_json = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		// The equivalent manual document, saved directly through the same
		// repository/document authority ProductFixedPricesPanel uses.
		$manual_document = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1150',
					'sale'    => '920',
				),
			),
			'EUR'
		);
		$this->repository->save( $product->get_id() + 1000000, $manual_document );
		$manual_json = (string) get_post_meta( $product->get_id() + 1000000, FixedPriceDocument::META_KEY, true );

		$this->assertSame( $manual_json, $seeded_json );
	}

	/**
	 * ADR-0030 M24 hardening regression: prior to
	 * {@see \UMC\Pricing\FixedPriceDocumentMerger}'s extraction, seed()
	 * never validated the final converted pair via
	 * {@see \UMC\Pricing\FixedPriceValidator::sale_less_than_regular()} —
	 * an FX conversion that happened to invert sale/regular at the target
	 * currency's precision (a decimal-rounding edge case `seed()` never
	 * produces in practice, and no pre-existing M24 test exercises) would
	 * have been silently persisted as an invalid fixed price. Engineered
	 * here via {@see InvertingDisplayPriceConverter}, a test double that
	 * deterministically inverts the converted pair regardless of the real
	 * native amounts, since a real rounding edge case is not practical to
	 * reproduce on demand.
	 */
	public function test_seed_rejects_and_does_not_persist_an_inverted_pair_produced_by_fx_conversion(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		$service = new FixedPriceCatalogOperationsService(
			$this->repository,
			$this->coverage,
			$rates,
			new InvertingDisplayPriceConverter(),
			$registry
		);

		// A perfectly ordinary, valid native pair (sale < regular) — the
		// inversion below comes entirely from the engineered converter, not
		// from bad input.
		$product = $this->simple_product( '100', '80' );

		$result = $service->seed( array( $product ), 'SEK' );

		$this->assertFalse( $result->is_aborted() );
		$this->assertNull(
			$this->repository->get( $product->get_id() )->get_currency( 'SEK' ),
			'A converted pair that inverts sale/regular at the target currency\'s precision must be rejected ' .
			'and reverted — never silently persisted — per ADR-0030\'s deliberate M24 hardening via ' .
			'FixedPriceDocumentMerger.'
		);
	}

	public function test_clear_removes_only_target_currency_and_preserves_others(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'GBP' => array( 'rate' => '0.85' ),
			),
			'EUR'
		);
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array( 'regular' => '1150' ),
					'GBP' => array( 'regular' => '85' ),
				),
				'EUR'
			)
		);

		$result = $this->service()->clear( array( wc_get_product( $product->get_id() ) ), 'SEK' );

		$this->assertFalse( $result->is_aborted() );
		$this->assertSame( array( $product->get_id() ), $result->succeeded() );

		$document = $this->repository->get( $product->get_id() );
		$this->assertNull( $document->get_currency( 'SEK' ) );
		$this->assertSame( '85.00', $document->get_currency( 'GBP' )?->regular() );
	}

	public function test_clear_skips_product_with_no_fixed_price_for_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->clear( array( $product ), 'SEK' );

		$this->assertSame( array(), $result->succeeded() );
		$this->assertArrayHasKey( $product->get_id(), $result->skipped() );
	}

	public function test_clear_rejects_base_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$result = $this->service()->clear( array( $product ), 'EUR' );

		$this->assertTrue( $result->is_aborted() );
		$this->assertSame( FixedPriceOperationResult::ABORT_BASE_CURRENCY, $result->abort_reason() );
	}

	/**
	 * M24 falsification G: repeated seed/clear with identical inputs is
	 * idempotent.
	 */
	public function test_seed_and_clear_are_idempotent(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$service = $this->service();
		$service->seed( array( wc_get_product( $product->get_id() ) ), 'SEK' );
		$first = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		$service->seed( array( wc_get_product( $product->get_id() ) ), 'SEK' );
		$second = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		$this->assertSame( $first, $second );

		$service->clear( array( wc_get_product( $product->get_id() ) ), 'SEK' );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );

		// Clearing again must be a harmless skip, not an error.
		$result = $service->clear( array( wc_get_product( $product->get_id() ) ), 'SEK' );
		$this->assertArrayHasKey( $product->get_id(), $result->skipped() );
	}
}
