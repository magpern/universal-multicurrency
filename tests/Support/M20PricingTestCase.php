<?php
/**
 * Shared harness for M20 fixed-pricing integration acceptance tests.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

use UMC\Cart\CartRecalculation;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Order\LineItemPriceProvenance;
use UMC\Order\OrderSnapshot;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\Pricing\ProductPriceProvenanceRegistry;
use UMC\Pricing\ProductPriceResolutionService;
use UMC\Pricing\ProductSaleStateResolver;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;

/**
 * Boots the M20 pricing graph and exposes product/cart/order fixtures.
 */
abstract class M20PricingTestCase extends WP_UnitTestCase {

	/**
	 * Hooks registered by the pricing graph.
	 *
	 * @var array<int, string>
	 */
	protected const MANAGED_HOOKS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_product_variation_get_price',
		'woocommerce_product_variation_get_regular_price',
		'woocommerce_product_variation_get_sale_price',
		'woocommerce_variation_prices_price',
		'woocommerce_variation_prices_regular_price',
		'woocommerce_variation_prices_sale_price',
		'woocommerce_get_variation_prices_hash',
		'woocommerce_currency',
		'woocommerce_currency_symbol',
		'wc_get_price_decimals',
		'wc_price_args',
		'woocommerce_cart_loaded_from_session',
		'woocommerce_checkout_create_order_line_item',
	);

	/**
	 * Fixed price repository shared by tests.
	 *
	 * @var FixedPriceRepository
	 */
	protected FixedPriceRepository $repository;

	/**
	 * Request-scoped provenance registry from the active graph.
	 *
	 * @var ProductPriceProvenanceRegistry
	 */
	protected ProductPriceProvenanceRegistry $provenance;

	/**
	 * Optional counting converter for instrumentation tests.
	 *
	 * @var CountingDisplayPriceConverter|null
	 */
	protected ?CountingDisplayPriceConverter $counting_converter = null;

	/**
	 * Active currency context.
	 *
	 * @var CurrencyContext
	 */
	protected CurrencyContext $context;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_price_num_decimals', 2 );
		update_option( 'woocommerce_calc_taxes', 'no' );

		$this->repository = new FixedPriceRepository( 'EUR' );

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();
	}

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	/**
	 * Boots the pricing graph for the given currencies and active code.
	 *
	 * @param array<string, array<string, mixed>> $currencies Settings currencies.
	 * @param string                              $active     Active currency code.
	 * @param bool                                $with_cart  Whether to register cart recalculation.
	 */
	protected function activate( array $currencies, string $active, bool $with_cart = false ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings      = new Settings();
		$registry      = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates         = new ManualRateProvider( $settings, 'EUR' );
		$this->context = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		$delegate                 = new PriceConversionService( $this->context );
		$this->counting_converter = new CountingDisplayPriceConverter( $delegate );
		$provenance_registry      = new ProductPriceProvenanceRegistry();
		$resolution               = new ProductPriceResolutionService(
			$this->repository,
			new ProductSaleStateResolver(),
			$this->counting_converter,
			$this->context,
			$registry,
			$provenance_registry
		);

		( new PriceHooks( $resolution, $this->context ) )->register();
		( new LineItemPriceProvenance( $provenance_registry ) )->register();
		( new CurrencyFormatting( $this->context ) )->register();

		if ( $with_cart ) {
			( new CartRecalculation( $this->context ) )->register();
			( new OrderSnapshot( $this->context, $settings, (string) UMC_VERSION, new CheckoutTransitionStateRepository() ) )->register();
		}

		$this->provenance = $provenance_registry;

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	/**
	 * Creates and publishes a simple product.
	 *
	 * @param string $regular Regular base price.
	 * @param string $sale    Optional sale base price.
	 */
	protected function simple_product( string $regular, string $sale = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Simple product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( $regular );

		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}

		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * Creates a variable product with two priced variations.
	 *
	 * @param string $low  Lower variation regular price.
	 * @param string $high Higher variation regular price.
	 * @return array{parent:WC_Product_Variable,low:WC_Product_Variation,high:WC_Product_Variation}
	 */
	protected function variable_product_pair( string $low, string $high ): array {
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Variable product' );
		$parent->set_status( 'publish' );
		$parent->save();

		$low_var = new WC_Product_Variation();
		$low_var->set_parent_id( $parent->get_id() );
		$low_var->set_regular_price( $low );
		$low_var->save();

		$high_var = new WC_Product_Variation();
		$high_var->set_parent_id( $parent->get_id() );
		$high_var->set_regular_price( $high );
		$high_var->save();

		WC_Product_Variable::sync( $parent->get_id() );
		wc_delete_product_transients( $parent->get_id() );

		return array(
			'parent' => wc_get_product( $parent->get_id() ),
			'low'    => wc_get_product( $low_var->get_id() ),
			'high'   => wc_get_product( $high_var->get_id() ),
		);
	}

	/**
	 * Persists a fixed-price document on a product or variation.
	 *
	 * @param int                $product_id Product or variation ID.
	 * @param FixedPriceDocument $document   Document to save.
	 */
	protected function save_fixed( int $product_id, FixedPriceDocument $document ): void {
		$this->repository->save( $product_id, $document );
		wc_delete_product_transients( $product_id );
	}

	/**
	 * Injects raw fixed-price meta, bypassing admin validation.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $json       Raw JSON document.
	 */
	protected function inject_raw_fixed_meta( int $product_id, string $json ): void {
		update_post_meta( $product_id, FixedPriceDocument::META_KEY, $json );
		$this->repository->clear_cache();
		wc_delete_product_transients( $product_id );
	}

	/**
	 * Adds a product to the cart and calculates totals.
	 *
	 * @param int $product_id Product or variation ID.
	 * @param int $quantity   Quantity.
	 */
	protected function add_to_cart( int $product_id, int $quantity = 1 ): void {
		WC()->cart->add_to_cart( $product_id, $quantity );
		WC()->cart->calculate_totals();
	}

	/**
	 * Creates order line items from the current cart and fires checkout hooks.
	 */
	protected function create_order_from_cart(): WC_Order {
		$order = wc_create_order();
		$order->set_currency( get_woocommerce_currency() );
		$order->set_created_via( 'checkout' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$item = new WC_Order_Item_Product();
			$item->set_props(
				array(
					'product_id'   => $cart_item['product_id'],
					'variation_id' => $cart_item['variation_id'] ?? 0,
					'quantity'     => $cart_item['quantity'],
					'subtotal'     => $cart_item['line_subtotal'],
					'total'        => $cart_item['line_total'],
				)
			);

			/** @var WC_Product $product */
			$product = $cart_item['data'];
			$item->set_name( $product->get_name() );

			do_action( 'woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $cart_item, $order );
			$order->add_item( $item );
		}

		$order->set_total( WC()->cart->get_total( 'edit' ) );
		do_action( 'woocommerce_checkout_create_order', $order, array() );
		do_action( 'woocommerce_checkout_update_order_meta', $order );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Reads line-item provenance meta from an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array{source:string,currency:string}>
	 */
	protected function line_provenance( WC_Order $order ): array {
		$rows = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$rows[] = array(
				'source'   => (string) $item->get_meta( LineItemPriceProvenance::META_SOURCE ),
				'currency' => (string) $item->get_meta( LineItemPriceProvenance::META_CURRENCY ),
			);
		}

		return $rows;
	}

	/**
	 * Resets the counting converter call counter when present.
	 */
	protected function reset_conversion_counter(): void {
		$this->counting_converter?->reset();
	}

	/**
	 * Returns conversion call count from the active graph.
	 */
	protected function conversion_calls(): int {
		return $this->counting_converter?->convert_calls() ?? 0;
	}

	/**
	 * Asserts order snapshot schema remains at version 4.
	 *
	 * @param WC_Order $order Order under test.
	 */
	protected function assert_snapshot_schema_unchanged( WC_Order $order ): void {
		$this->assertSame( '4', (string) $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION ) );
	}
}
