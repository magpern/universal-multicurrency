<?php

/**
 * Order-pay endpoint currency lock.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Integration\GatewayCompatibility;

/**
 * Locks the order currency for the entire order-pay request.
 *
 * On the order-pay endpoint (`wc-api=order-pay` or checkout?pay_for_order),
 * loads the order, verifies it, and enters the order currency context for the
 * request. All formatting, gateway filtering, and order display use the
 * *order's currency*, ignoring the storefront session currency.
 *
 * Incompatible gateways are hidden; if no gateway supports the order currency
 * and it is no longer configured, the order remains payable via the ISO fallback.
 *
 * No conversion occurs; totals remain stored values in the order currency.
 */
final class OrderPayCurrencyLock {

	/**
	 * Order currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $context;

	/**
	 * Gateway compatibility checker.
	 *
	 * @var GatewayCompatibility
	 */
	private GatewayCompatibility $gateway_compat;

	/**
	 * Currency registry (for is_base check).
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Binds the lock to its dependencies.
	 *
	 * @param OrderCurrencyContext $context         Order currency context.
	 * @param GatewayCompatibility $gateway_compat  Gateway compatibility checker.
	 * @param CurrencyRegistry     $registry        Currency registry.
	 */
	public function __construct(
		OrderCurrencyContext $context,
		GatewayCompatibility $gateway_compat,
		CurrencyRegistry $registry
	) {
		$this->context        = $context;
		$this->gateway_compat = $gateway_compat;
		$this->registry       = $registry;
	}

	/**
	 * Registers the order-pay hook.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_lock_order_pay' ), 10 );
	}

	/**
	 * Enters the order currency context if on the order-pay endpoint.
	 *
	 * Called on template_redirect, early enough that gateways are not yet fetched.
	 */
	public function maybe_lock_order_pay(): void {
		// Detect order-pay endpoint.
		$order_id = $this->get_order_id_from_request();
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Verify the customer has permission to pay this order.
		if ( ! $this->customer_can_pay_order( $order ) ) {
			return;
		}

		// Enter the order currency context for the request.
		$this->context->enter( $order );

		// Register gateway filtering for this order's currency.
		add_filter(
			'woocommerce_available_payment_gateways',
			array( $this, 'filter_gateways_for_order' ),
			15, // After M3's 10, before other filters.
			1
		);

		/**
		 * Fires when an order-pay currency lock is established.
		 *
		 * @since 0.4.0
		 *
		 * @param string    $currency Order currency code.
		 * @param \WC_Order $order    Order being paid.
		 */
		do_action( 'umc_order_pay_locked_currency', $order->get_currency(), $order );
	}

	/**
	 * Filters gateways for the currently-locked order currency.
	 *
	 * @param mixed $gateways Available gateways keyed by id.
	 * @return mixed Filtered gateways.
	 */
	public function filter_gateways_for_order( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			return $gateways;
		}

		$currency = $this->context->current_code();
		if ( ! $currency ) {
			return $gateways;
		}

		// Use the gateway compatibility engine with the explicit order currency.
		return $this->gateway_compat->filter_gateways_for_currency( $gateways, $currency );
	}

	/**
	 * Gets the order ID from the request if on the order-pay endpoint.
	 *
	 * @return int|null Order ID, or null if not on order-pay.
	 */
	private function get_order_id_from_request(): ?int {
		// Check for the `order-pay` query variable (used by checkout shortcode).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only order ID lookup.
		if ( ! empty( $_GET['order-pay'] ) ) {
			$order_id = (int) wp_unslash( $_GET['order-pay'] );
			if ( $order_id > 0 ) {
				return $order_id;
			}
		}

		// Check for `pay_for_order` (legacy form).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only order ID lookup.
		if ( ! empty( $_GET['pay_for_order'] ) ) {
			$order_id = (int) wp_unslash( $_GET['order-pay'] );
			if ( $order_id > 0 ) {
				return $order_id;
			}
		}

		return null;
	}

	/**
	 * Checks whether the current customer can pay this order.
	 *
	 * @param \WC_Order $order Order to check.
	 */
	private function customer_can_pay_order( \WC_Order $order ): bool {
		// Order must be awaiting payment.
		if ( ! $order->needs_payment() ) {
			return false;
		}

		// Check order key (customer verification).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only order-key lookup.
		$order_key = ! empty( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( '' === $order_key || $order->get_order_key() !== $order_key ) {
			return false;
		}

		// For logged-in customers, verify they own the order.
		$user_id = get_current_user_id();
		if ( $user_id > 0 && (int) $order->get_customer_id() !== $user_id ) {
			return false;
		}

		return true;
	}
}
