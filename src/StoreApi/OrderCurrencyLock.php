<?php
/**
 * Store API order-scope currency lock.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\Integration\GatewayCompatibility;
use UMC\Order\OrderCurrencyContext;
use WC_Order;

/**
 * Makes the Store API's order routes report a stored order in its own currency.
 *
 * `OrderSchema` serializes stored order totals but builds their currency
 * identity from `get_woocommerce_currency()` — the shopper's session currency.
 * A customer browsing in EUR who opens the confirmation for an order paid in
 * SEK would see the stored SEK amounts labelled as EUR. The same applies to
 * paying for an existing order through the Store API, where gateway
 * availability must follow the order rather than the session.
 *
 * This is the REST counterpart of {@see \UMC\Order\OrderPayCurrencyLock}, which
 * hooks `template_redirect` and so never runs for an API request. Both delegate
 * to the same order currency context; neither converts anything.
 */
final class OrderCurrencyLock {

	/**
	 * Store API routes that address a single existing order.
	 */
	private const ORDER_ROUTE_PATTERN = '#^/wc/store/v\d+/(?:order|checkout)/(\d+)/?$#';

	/**
	 * Order-scope currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $context;

	/**
	 * Shared gateway compatibility service.
	 *
	 * @var GatewayCompatibility
	 */
	private GatewayCompatibility $gateway_compat;

	/**
	 * Whether this request currently holds a lock.
	 *
	 * @var bool
	 */
	private bool $locked = false;

	/**
	 * Binds the lock to the order context and gateway service.
	 *
	 * @param OrderCurrencyContext $context        Order-scope currency context.
	 * @param GatewayCompatibility $gateway_compat Shared gateway compatibility service.
	 */
	public function __construct( OrderCurrencyContext $context, GatewayCompatibility $gateway_compat ) {
		$this->context        = $context;
		$this->gateway_compat = $gateway_compat;
	}

	/**
	 * Registers the request brackets.
	 */
	public function register(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'maybe_lock' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'release' ), 10, 3 );
	}

	/**
	 * Enters the order currency context for an order-scoped Store API route.
	 *
	 * Returns its first argument untouched: this is a filter only because REST
	 * offers no action at this point in dispatch.
	 *
	 * @param mixed $response Response or error, passed through.
	 * @param mixed $handler  Route handler (unused).
	 * @param mixed $request  Request being dispatched.
	 * @return mixed
	 */
	public function maybe_lock( $response, $handler, $request ) {
		unset( $handler );

		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$order = $this->route_order( (string) $request->get_route() );

		if ( ! $order instanceof WC_Order ) {
			return $response;
		}

		/*
		 * The order is authoritative here, so the session-based gateway filter
		 * is removed rather than layered over: a gateway unsupported in the
		 * session currency but supported by the order's must not have been
		 * discarded before the order-currency rule runs. Both paths share one
		 * GatewayCompatibility instance, so this matches the exact callback.
		 *
		 * Swapped before the context is entered, so that anything reacting to
		 * the context-entered action already sees the fully established lock.
		 */
		remove_filter(
			'woocommerce_available_payment_gateways',
			array( $this->gateway_compat, 'filter_gateways' ),
			10
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			array( $this, 'filter_gateways_for_order' ),
			10,
			1
		);

		$this->locked = true;

		$this->context->enter( $order );

		return $response;
	}

	/**
	 * Leaves the order currency context once the route has produced a response.
	 *
	 * @param mixed $response Response or error, passed through.
	 * @param mixed $handler  Route handler (unused).
	 * @param mixed $request  Request being dispatched (unused).
	 * @return mixed
	 */
	public function release( $response, $handler, $request ) {
		unset( $handler, $request );

		if ( ! $this->locked ) {
			return $response;
		}

		$this->locked = false;

		remove_filter(
			'woocommerce_available_payment_gateways',
			array( $this, 'filter_gateways_for_order' ),
			10
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			array( $this->gateway_compat, 'filter_gateways' ),
			10,
			1
		);

		$this->context->exit();

		return $response;
	}

	/**
	 * Filters gateways for the locked order's currency.
	 *
	 * @param mixed $gateways Available gateways keyed by id.
	 * @return mixed
	 */
	public function filter_gateways_for_order( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			return $gateways;
		}

		$currency = $this->context->current_code();

		if ( ! $currency ) {
			return $gateways;
		}

		return $this->gateway_compat->filter_gateways_for_currency( $gateways, $currency );
	}

	/**
	 * Resolves the order a Store API route addresses, if any.
	 *
	 * @param string $route Route being dispatched.
	 */
	private function route_order( string $route ): ?WC_Order {
		$matches = array();

		if ( 1 !== preg_match( self::ORDER_ROUTE_PATTERN, $route, $matches ) ) {
			return null;
		}

		$order = wc_get_order( (int) $matches[1] );

		return $order instanceof WC_Order ? $order : null;
	}
}
