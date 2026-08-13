<?php
/**
 * Integration tests for M20 fixed product pricing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Order\LineItemPriceProvenance;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\Pricing\ProductPriceResolution;
use UMC\Settings;
use UMC\Tests\Support\ProductPricingTestGraph;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * @covers \UMC\Pricing\ProductPriceResolutionService
 * @covers \UMC\Integration\PriceHooks
 */
final class FixedProductPricingTest extends WP_UnitTestCase {

	/**
	 * Fixed price repository under test.
	 *
	 * @var FixedPriceRepository
	 */
	private FixedPriceRepository $repository;

	public function set_up(): void {
		parent::set_up();
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_price_num_decimals', 2 );
		$this->repository = new FixedPriceRepository( 'EUR' );
	}

	public function tear_down(): void {
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 */
	private function activate( array $currencies, string $active ): void {
		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new \UMC\Rates\ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		ProductPricingTestGraph::register( $context, $registry, $this->repository );
		( new CurrencyFormatting( $context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	private function simple_product( string $regular, string $sale = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_regular_price( $regular );
		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	public function test_simple_fixed_regular_price_is_authoritative(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);
		$product = wc_get_product( $product->get_id() );

		$this->assertSame( '1100.00', $product->get_regular_price() );
		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_fixed_price_unaffected_by_rate_change(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$this->activate( array( 'SEK' => array( 'rate' => '20' ) ), 'SEK' );
		$product = wc_get_product( $product->get_id() );

		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_converted_fallback_without_fixed_meta(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );

		$this->assertSame( '1150.00', $product->get_price() );
	}

	public function test_fixed_sale_follows_woocommerce_sale_state(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100', '80' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1100',
						'sale'    => '880',
					),
				),
				'EUR'
			)
		);
		$product = wc_get_product( $product->get_id() );

		$this->assertTrue( $product->is_on_sale() );
		$this->assertSame( '880.00', $product->get_price() );
		$this->assertSame( '1100.00', $product->get_regular_price() );
	}

	public function test_base_currency_entry_in_meta_is_ignored(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		update_post_meta(
			$product->get_id(),
			FixedPriceDocument::META_KEY,
			wp_json_encode(
				array(
					'currencies' => array(
						'EUR' => array( 'regular' => '1' ),
						'SEK' => array( 'regular' => '1100' ),
					),
				)
			)
		);
		$this->repository->clear_cache();
		$product = wc_get_product( $product->get_id() );

		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_disabled_currency_fixed_price_restored_on_re_enable(): void {
		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);
		$product = wc_get_product( $product->get_id() );
		$this->assertSame( '1100.00', $product->get_price() );

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => false,
				),
			),
			'EUR'
		);
		$stored = $this->repository->get( $product->get_id() )->get_currency( 'SEK' );
		$this->assertSame( '1100.00', $stored?->regular() );

		$this->activate(
			array(
				'SEK' => array(
					'rate'    => '11.50',
					'enabled' => true,
				),
			),
			'SEK'
		);
		$product = wc_get_product( $product->get_id() );
		$this->assertSame( '1100.00', $product->get_price() );
	}

	public function test_fixed_sale_inactive_when_product_not_on_sale(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1100',
						'sale'    => '880',
					),
				),
				'EUR'
			)
		);
		$product = wc_get_product( $product->get_id() );

		$this->assertFalse( $product->is_on_sale() );
		$this->assertSame( '1100.00', $product->get_price() );
	}
}
