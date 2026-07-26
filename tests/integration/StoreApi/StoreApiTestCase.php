<?php
/**
 * Shared harness for Store API integration tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\StoreApi;

use UMC\Cart\CartRecalculation;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\CouponConversion;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\GatewayCompatibility;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Integration\ShippingConversion;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderCurrencyFormatting;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\StoreApi\CartExtensionData;
use UMC\StoreApi\CheckoutSnapshotAdapter;
use UMC\StoreApi\OrderCurrencyLock;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Base class for tests that drive real `/wc/store/v1` routes.
 *
 * Store API requests only behave like Store API requests when
 * `$_SERVER['REQUEST_URI']` says so: both `WC::is_rest_api_request()` and
 * `WC::is_store_api_request()` read it directly and bail on an empty value,
 * while `rest_do_request()` sets nothing. Without the simulation this harness
 * performs, a test would silently exercise the storefront code path and prove
 * nothing about Store API behaviour. The request URI is therefore established
 * in {@see self::set_up()}, before any currency context is built, so the
 * memoized convertible-request decision is made under the right conditions.
 */
abstract class StoreApiTestCase extends WP_UnitTestCase {

	/**
	 * Route namespace prefix shared by every Store API request.
	 */
	protected const STORE_API_ROOT = '/wc/store/v1';

	/**
	 * Default request URI used between explicit route calls.
	 */
	private const DEFAULT_REQUEST_URI = '/wp-json/wc/store/v1/cart';

	/**
	 * Plugin version stamped into snapshots written under test.
	 *
	 * Reads the constant at call time rather than copying it into a class
	 * constant, which would resolve at class-compile time and could see a
	 * stale value depending on bootstrap order.
	 */
	protected function plugin_version(): string {
		return (string) UMC_VERSION;
	}

	/**
	 * Every hook the plugin graph registers, cleared before and after each test.
	 *
	 * The real graph is already registered by `Plugin::init()` at bootstrap;
	 * tests replace it wholesale so the active currency is deterministic.
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
		'option_woocommerce_currency_pos',
		'woocommerce_cart_loaded_from_session',
		'woocommerce_coupon_get_amount',
		'woocommerce_coupon_get_minimum_amount',
		'woocommerce_coupon_get_maximum_amount',
		'woocommerce_package_rates',
		'woocommerce_cart_shipping_packages',
		'woocommerce_available_payment_gateways',
		'woocommerce_checkout_create_order',
		'woocommerce_store_api_checkout_update_order_meta',
		'woocommerce_store_api_cart_update_order_from_request',
		'rest_request_before_callbacks',
		'rest_request_after_callbacks',
		// Plugin-provided filters, through which a test states how the host is
		// configured. Rebuilding the graph re-states that configuration, so these
		// are cleared alongside the hooks the plugin itself registers.
		'umc_is_request_convertible',
		'umc_currency_signature',
		'umc_gateway_supported_currencies',
		'umc_convert_shipping_rate',
		'umc_coupon_amount_is_base',
		'umc_order_snapshot_meta',
	);

	/**
	 * Plugin-provided actions tests subscribe to in order to observe behaviour.
	 *
	 * Unlike the hooks above these are cleared only between tests: a subscriber
	 * registered before a currency switch must still be listening after it.
	 *
	 * @var array<int, string>
	 */
	private const OBSERVED_ACTIONS = array(
		'umc_order_snapshot_created',
		'umc_order_snapshot_refreshed',
		'umc_order_currency_context_entered',
		'umc_order_currency_context_exited',
		'umc_cart_recalculated',
		'umc_gateway_hidden',
	);

	/**
	 * Currency context backing the currently booted graph.
	 *
	 * @var CurrencyContext
	 */
	protected CurrencyContext $context;

	/**
	 * Base currency code the graph was booted with.
	 *
	 * @var string
	 */
	protected string $base_code = 'EUR';

	/**
	 * Base currency decimals the graph was booted with.
	 *
	 * @var int
	 */
	protected int $base_decimals = 2;

	/**
	 * Gateway compatibility instance shared by the storefront filter and locks.
	 *
	 * @var GatewayCompatibility
	 */
	protected GatewayCompatibility $gateway_compat;

	/**
	 * Order-scope currency context backing the currently booted graph.
	 *
	 * @var OrderCurrencyContext
	 */
	protected OrderCurrencyContext $order_context;

	/**
	 * Currencies the graph was booted with.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $booted_currencies = array();

	/**
	 * Request URI present before the test replaced it.
	 *
	 * @var string|null
	 */
	private ?string $original_request_uri = null;

	public function set_up(): void {
		parent::set_up();

		$this->original_request_uri = $this->current_request_uri();

		// Establish the Store API request identity before anything can memoize a
		// convertible-request decision. See the class docblock.
		$this->set_request_uri( self::DEFAULT_REQUEST_URI );

		// Cart routes require a nonce for update requests; WooCommerce provides
		// this filter specifically so API endpoints can be exercised in tests.
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		$this->reset_cart();
	}

	public function tear_down(): void {
		// WooCommerce stores notices in the session and its NoticeHandler turns
		// session error notices into Store API errors, so a notice one test
		// leaves behind would fail the next one's requests.
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		$this->clear_managed_hooks();

		foreach ( self::OBSERVED_ACTIONS as $hook ) {
			remove_all_actions( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );

		// Restore rather than unset: WordPress shutdown work (the cron spawner)
		// reads REQUEST_URI and warns when it is missing entirely.
		$this->set_request_uri( $this->original_request_uri );

		parent::tear_down();
	}

	/**
	 * Builds and registers a fresh plugin graph mirroring `Plugin::init()`.
	 *
	 * @param array<string, array<string, mixed>> $currencies Configured currencies.
	 * @param string|null                         $active     Currency to select via cookie.
	 * @param string                              $base       Store base currency code.
	 * @param int                                 $decimals   Store base decimals.
	 */
	protected function boot_plugin( array $currencies, ?string $active = null, string $base = 'EUR', int $decimals = 2 ): void {
		$this->clear_managed_hooks();

		$this->base_code         = $base;
		$this->base_decimals     = $decimals;
		$this->booted_currencies = $currencies;

		update_option( 'woocommerce_currency', $base );
		update_option( 'woocommerce_price_num_decimals', $decimals );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( $base, $decimals ) );
		$rates    = new ManualRateProvider( $settings, $base );

		$this->context = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service       = new PriceConversionService( $this->context );

		( new PriceHooks( $service, $this->context ) )->register();
		( new CurrencyFormatting( $this->context ) )->register();
		( new CartRecalculation( $this->context ) )->register();
		( new CouponConversion( $service, $this->context ) )->register();
		( new ShippingConversion( $service, $this->context ) )->register();

		// One shared instance, as Plugin::init() wires it: the order lock removes
		// this exact callback so its order-currency rule sees the original set.
		$this->gateway_compat = new GatewayCompatibility( $this->context );
		$this->gateway_compat->register();
		( new OrderSnapshot( $this->context, $settings, $this->plugin_version() ) )->register();

		$reader              = new OrderSnapshotReader();
		$resolver            = new HistoricalFormattingResolver( $registry );
		$this->order_context = new OrderCurrencyContext( $reader, $resolver );

		( new OrderCurrencyFormatting( $this->order_context, $resolver ) )->register();

		$this->register_store_api_services( $this->context, $this->order_context, $settings );

		if ( null === $active ) {
			unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		} else {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		}
	}

	/**
	 * Registers the Store API adapters, mirroring `Plugin::init()`.
	 *
	 * @param CurrencyContext      $context       Live currency context.
	 * @param OrderCurrencyContext $order_context Order-scope currency context.
	 * @param Settings             $settings      Settings store.
	 */
	protected function register_store_api_services( CurrencyContext $context, OrderCurrencyContext $order_context, Settings $settings ): void {
		$snapshot = new OrderSnapshot( $context, $settings, $this->plugin_version() );

		( new CheckoutSnapshotAdapter( $snapshot ) )->register();
		( new OrderCurrencyLock( $order_context, $this->gateway_compat ) )->register();
		( new CartExtensionData( $context ) )->register();
	}

	/**
	 * Selects a different currency the way a new request would see it.
	 *
	 * Rebuilding the graph is deliberate: {@see CurrencyContext} memoizes the
	 * active currency and rate for its lifetime, exactly as it does in
	 * production where each request builds its own instance.
	 *
	 * @param string $code Currency code to select.
	 */
	protected function switch_currency( string $code ): void {
		$this->boot_plugin( $this->booted_currencies, $code, $this->base_code, $this->base_decimals );
		$this->rehydrate_cart();
	}

	/**
	 * Reloads the cart from its session, as the next request would.
	 *
	 * WooCommerce guards session loading with `did_action()`, so within a single
	 * PHP process only the first cart load reads the session and fires
	 * `woocommerce_cart_loaded_from_session`. Production never hits that guard,
	 * because every request is a new process. Driving `WC_Cart_Session` directly
	 * restores the per-request behaviour the recalculation hook depends on.
	 */
	protected function rehydrate_cart(): void {
		WC()->cart = new \WC_Cart();

		( new \WC_Cart_Session( WC()->cart ) )->get_cart_from_session();
	}

	/**
	 * Dispatches a real Store API request through the REST server.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Route below `/wc/store/v1`, e.g. `/cart`.
	 * @param array<string, mixed> $body   Request body parameters.
	 * @param array<string, mixed> $query  Query-string parameters.
	 */
	protected function store_api_request( string $method, string $path, array $body = array(), array $query = array() ): WP_REST_Response {
		$route = self::STORE_API_ROOT . $path;

		$previous_uri = $this->current_request_uri();
		$this->set_request_uri( '/wp-json' . $route );

		try {
			$this->reset_rest_server();

			$request = new WP_REST_Request( $method, $route );

			if ( array() !== $query ) {
				$request->set_query_params( $query );
			}

			if ( array() !== $body ) {
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body( (string) wp_json_encode( $body ) );
			}

			return rest_do_request( $request );
		} finally {
			$this->set_request_uri( $previous_uri );
		}
	}

	/**
	 * Discards the REST server so the next request builds fresh route objects.
	 *
	 * WooCommerce instantiates each Store API route once, when `rest_api_init`
	 * fires, and the checkout route remembers the order it last worked on
	 * (`$this->order = $this->order ?? $this->get_draft_order()`). One PHP
	 * process serving many requests would therefore carry an order between them,
	 * which never happens in production. Rebuilding the server per request also
	 * means a checkout retry genuinely reloads its draft from the session, which
	 * is the path being tested.
	 */
	private function reset_rest_server(): void {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Dispatches a request against another REST namespace, for gate assertions.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Full route, e.g. `/wc/v3/products`.
	 */
	protected function rest_request( string $method, string $route ): WP_REST_Response {
		$previous_uri = $this->current_request_uri();
		$this->set_request_uri( '/wp-json' . $route );

		try {
			return rest_do_request( new WP_REST_Request( $method, $route ) );
		} finally {
			$this->set_request_uri( $previous_uri );
		}
	}

	/**
	 * Runs a callback as a storefront (non-REST) request.
	 *
	 * Used by parity tests to drive the classic flow through the same fixtures.
	 *
	 * @param callable $callback Work to perform without a REST request identity.
	 *
	 * @return mixed Whatever the callback returns.
	 */
	protected function as_storefront_request( callable $callback ) {
		return $this->with_request_uri( null, $callback );
	}

	/**
	 * Runs a callback with a specific request URI in place.
	 *
	 * @param string|null $uri      Request URI to present, or null for none.
	 * @param callable    $callback Work to perform.
	 *
	 * @return mixed Whatever the callback returns.
	 */
	protected function with_request_uri( ?string $uri, callable $callback ) {
		$previous_uri = $this->current_request_uri();
		$this->set_request_uri( $uri );

		try {
			return $callback();
		} finally {
			$this->set_request_uri( $previous_uri );
		}
	}

	/**
	 * Builds a currency context from current settings without registering hooks.
	 *
	 * Each call returns a new instance: the context memoizes its active currency,
	 * rate and convertible-request decision for its lifetime, so assertions about
	 * differing request conditions need their own instance, exactly as separate
	 * requests would have in production.
	 */
	protected function new_context(): CurrencyContext {
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( $this->base_code, $this->base_decimals ) );

		return new CurrencyContext(
			$registry,
			new ManualRateProvider( $settings, $this->base_code ),
			new CurrencyResolver()
		);
	}

	/**
	 * Evaluates the conversion gate as it would be for a given request URI.
	 *
	 * @param string|null $uri Request URI to present, or null for a page view.
	 */
	protected function converts_under_uri( ?string $uri ): bool {
		return (bool) $this->with_request_uri(
			$uri,
			function (): bool {
				return $this->new_context()->is_convertible_request();
			}
		);
	}

	/**
	 * Evaluates the conversion gate for a fully-formed REST request.
	 *
	 * A real REST request carries both a request URI and the route WordPress
	 * parsed out of it. `rest_do_request()` sets neither, so a test that wants
	 * to exercise route-based detection has to supply both.
	 *
	 * @param string $uri   Request URI to present.
	 * @param string $route Parsed REST route, e.g. `/wc/store/v1/cart`.
	 */
	protected function converts_under_route( string $uri, string $route ): bool {
		$previous = $GLOBALS['wp']->query_vars['rest_route'] ?? null;

		$GLOBALS['wp']->query_vars['rest_route'] = $route;

		try {
			return $this->converts_under_uri( $uri );
		} finally {
			if ( null === $previous ) {
				unset( $GLOBALS['wp']->query_vars['rest_route'] );
			} else {
				$GLOBALS['wp']->query_vars['rest_route'] = $previous;
			}
		}
	}

	/**
	 * Reads the current request URI.
	 */
	private function current_request_uri(): ?string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Test harness reading back a URI it set itself.
		return isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
	}

	/**
	 * Sets, or with null removes, the current request URI.
	 *
	 * @param string|null $uri Request URI to present.
	 */
	private function set_request_uri( ?string $uri ): void {
		if ( null === $uri ) {
			unset( $_SERVER['REQUEST_URI'] );

			return;
		}

		$_SERVER['REQUEST_URI'] = $uri;
	}

	/**
	 * Converts a decimal amount to the Store API's minor-unit string encoding.
	 *
	 * @param float|int|string $amount   Amount in major units.
	 * @param int              $decimals Currency minor unit.
	 */
	protected function minor_units( $amount, int $decimals ): string {
		return (string) (int) round( (float) $amount * ( 10 ** $decimals ) );
	}

	/**
	 * Resets WooCommerce session, customer and cart to an empty state.
	 */
	protected function reset_cart(): void {
		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		// WC()->session outlives an individual test, so per-shopper state is
		// cleared explicitly. Without this a currency persisted by one test
		// silently satisfies (or breaks) the next one's assertions, and the
		// Store API's draft order id would make a later checkout reuse — and
		// report on — an order a previous test already completed.
		WC()->session->set( CurrencyContext::SESSION_KEY, null );
		WC()->session->set( CartRecalculation::SESSION_KEY, null );
		WC()->session->set( 'store_api_draft_order', null );
		WC()->session->set( 'chosen_payment_method', null );

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();
	}

	/**
	 * Removes every plugin-managed hook, including the bootstrap graph.
	 */
	protected function clear_managed_hooks(): void {
		foreach ( static::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
		}
	}

	/**
	 * Creates a published simple product.
	 *
	 * @param string $regular Regular price.
	 * @param string $sale    Optional sale price.
	 */
	protected function simple_product( string $regular, string $sale = '' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( $regular );

		if ( '' !== $sale ) {
			$product->set_sale_price( $sale );
		}

		$product->set_status( 'publish' );
		$product->save();

		return wc_get_product( $product->get_id() );
	}

	/**
	 * Creates a published variable product with two priced variations.
	 *
	 * @param string $low  Price of the cheaper variation.
	 * @param string $high Price of the dearer variation.
	 */
	protected function variable_product( string $low, string $high ): WC_Product_Variable {
		$product = new WC_Product_Variable();
		$product->set_name( 'Variable Product' );
		$product->set_status( 'publish' );
		$product->save();

		foreach ( array( $low, $high ) as $price ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_regular_price( $price );
			$variation->set_status( 'publish' );
			$variation->save();
		}

		\WC_Product_Variable::sync( $product->get_id() );

		return wc_get_product( $product->get_id() );
	}

	/**
	 * Creates a published coupon.
	 *
	 * @param string               $code   Coupon code.
	 * @param string               $type   Discount type.
	 * @param string               $amount Coupon amount.
	 * @param array<string, mixed> $props  Extra properties to set.
	 */
	protected function make_coupon( string $code, string $type, string $amount, array $props = array() ): \WC_Coupon {
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( (float) $amount );

		foreach ( $props as $setter => $value ) {
			$coupon->{"set_{$setter}"}( $value );
		}

		$coupon->save();

		return new \WC_Coupon( $code );
	}

	/**
	 * Asserts a Store API response succeeded and returns its data as an array.
	 *
	 * @param WP_REST_Response $response Response under test.
	 *
	 * @return array<string, mixed>
	 */
	protected function response_data( WP_REST_Response $response ): array {
		$this->assertLessThan(
			300,
			$response->get_status(),
			'Store API request failed: ' . wp_json_encode( $response->get_data() )
		);

		return (array) json_decode( (string) wp_json_encode( $response->get_data() ), true );
	}
}
