<?php
/**
 * Classic checkout policy adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\Checkout\CheckoutPolicyCoordinator;
use UMC\Checkout\CheckoutSurface;
use UMC\CurrencyContext;

/**
 * Applies checkout currency policy on classic checkout surfaces.
 */
final class ClassicCheckoutPolicyAdapter {

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
	 * Registers classic checkout hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_before_checkout_form', array( $this, 'apply_policy' ), 5 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'apply_policy' ), 5 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'apply_policy' ), 5 );
	}

	/**
	 * Applies checkout policy when the classic checkout surface is active.
	 */
	public function apply_policy(): void {
		if ( ! $this->context->is_convertible_request() ) {
			return;
		}

		$this->coordinator->apply( CheckoutSurface::CLASSIC_CHECKOUT );
	}
}
