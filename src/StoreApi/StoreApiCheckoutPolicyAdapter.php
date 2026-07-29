<?php
/**
 * Store API checkout policy adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\Checkout\CheckoutPolicyCoordinator;
use UMC\Checkout\CheckoutSurface;
use UMC\CurrencyContext;

/**
 * Applies checkout currency policy on Store API checkout surfaces.
 */
final class StoreApiCheckoutPolicyAdapter {

	/**
	 * Checkout route pattern excluding order-owned checkout routes.
	 */
	private const CHECKOUT_ROUTE_PATTERN = '#^/wc/store/v\d+/checkout/?$#';

	/**
	 * Shared checkout policy coordinator.
	 *
	 * @var CheckoutPolicyCoordinator
	 */
	private CheckoutPolicyCoordinator $coordinator;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Whether the current request targets a checkout route.
	 *
	 * @var bool|null
	 */
	private ?bool $checkout_route = null;

	/**
	 * Binds the adapter to its collaborators.
	 *
	 * @param CheckoutPolicyCoordinator $coordinator Shared checkout coordinator.
	 * @param CurrencyContext           $context     Request-scoped currency facade.
	 */
	public function __construct( CheckoutPolicyCoordinator $coordinator, CurrencyContext $context ) {
		$this->coordinator = $coordinator;
		$this->context     = $context;
	}

	/**
	 * Registers Store API checkout hooks.
	 */
	public function register(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'maybe_apply_on_checkout_route' ), 5, 3 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_apply_on_checkout_cart' ), 25 );
	}

	/**
	 * Applies policy before Store API checkout callbacks run.
	 *
	 * @param mixed            $response Current response.
	 * @param mixed            $handler  Route handler.
	 * @param \WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function maybe_apply_on_checkout_route( $response, $handler, $request ) {
		unset( $response, $handler );

		if ( ! $request instanceof \WP_REST_Request || ! $this->is_checkout_route( $request->get_route() ) ) {
			return $response;
		}

		$this->apply_policy();

		return $response;
	}

	/**
	 * Applies policy when the cart loads on a checkout route.
	 */
	public function maybe_apply_on_checkout_cart(): void {
		if ( ! $this->is_current_checkout_route() ) {
			return;
		}

		$this->apply_policy();
	}

	/**
	 * Applies checkout policy for the Store API checkout surface.
	 */
	private function apply_policy(): void {
		if ( ! $this->context->is_convertible_request() ) {
			return;
		}

		$this->coordinator->apply( CheckoutSurface::STORE_API_CHECKOUT );
	}

	/**
	 * Whether the route is a checkout route excluding order-owned routes.
	 *
	 * @param string $route REST route path.
	 */
	private function is_checkout_route( string $route ): bool {
		return 1 === preg_match( self::CHECKOUT_ROUTE_PATTERN, $route );
	}

	/**
	 * Whether the current request targets a checkout route.
	 */
	private function is_current_checkout_route(): bool {
		if ( null !== $this->checkout_route ) {
			return $this->checkout_route;
		}

		$route = $this->parsed_rest_route();

		$this->checkout_route = is_string( $route ) && $this->is_checkout_route( $route );

		return $this->checkout_route;
	}

	/**
	 * Returns the parsed REST route for the current request.
	 */
	private function parsed_rest_route(): ?string {
		if ( ! isset( $GLOBALS['wp'] ) || ! $GLOBALS['wp'] instanceof \WP ) {
			return null;
		}

		$route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		return is_string( $route ) && '' !== $route ? $route : null;
	}
}
