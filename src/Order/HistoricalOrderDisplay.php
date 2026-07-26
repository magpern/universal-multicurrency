<?php
/**
 * Order currency context bracketing for historical order display.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

/**
 * Registers narrow enter/exit bracket hooks around order rendering zones.
 *
 * Enters the order context immediately before WooCommerce renders order data
 * (via before_* hooks at priority 1), exits immediately after (via after_* hooks
 * at priority 999). Uses FILO (first-in/last-out) semantics to support nested
 * renders (e.g., admin order list with multiple orders).
 *
 * Covered zones:
 * - Order details table (thank-you, My-Account order-detail view)
 * - Transactional emails (live send, preview, resend)
 * - My-Account order list (per-row column render via owned callback)
 *
 * Context cannot leak because:
 * 1. Priorities are strict FILO (1 enter, 999 exit).
 * 2. Template hooks are always paired (verified by structural guard).
 * 3. Non-template renders use run() with try/finally (admin, order-pay).
 * 4. While the context is on the stack, OrderCurrencyFormatting (priority 20)
 *    overrides the M2 session formatter (priority 10) for the render.
 */
final class HistoricalOrderDisplay {

	/**
	 * Order currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $context;

	/**
	 * Binds the display handler to the context.
	 *
	 * @param OrderCurrencyContext $context Order currency context.
	 */
	public function __construct( OrderCurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the display bracketing hooks.
	 */
	public function register(): void {
		// Order-details table (thank-you + view-order).
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'enter_order_details' ), 1 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'exit_order_details' ), 999 );

		// Transactional emails (live, preview, resend).
		add_action( 'woocommerce_email_before_order_table', array( $this, 'enter_email_order' ), 1, 4 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'exit_email_order' ), 999, 4 );

		// Email resend (admin action before/after).
		add_action( 'woocommerce_before_resend_order_emails', array( $this, 'enter_resend' ), 1, 2 );
		add_action( 'woocommerce_after_resend_order_email', array( $this, 'exit_resend' ), 999, 2 );

		// My-Account order list (per-row column).
		add_action( 'woocommerce_my_account_my_orders_column_order-total', array( $this, 'render_order_total' ), 10 );
	}

	/**
	 * Enters context before the order-details table.
	 *
	 * @param mixed $order Order object.
	 */
	public function enter_order_details( $order ): void {
		$this->context->enter( $order );
	}

	/**
	 * Exits context after the order-details table.
	 */
	public function exit_order_details(): void {
		$this->context->exit();
	}

	/**
	 * Enters context before the email order table.
	 *
	 * @param mixed $order       Order object.
	 * @param mixed $sent_to_admin (unused).
	 * @param mixed $plain_text  (unused).
	 * @param mixed $email       (unused).
	 */
	public function enter_email_order( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		unset( $sent_to_admin, $plain_text, $email );
		$this->context->enter( $order );
	}

	/**
	 * Exits context after the email order table.
	 *
	 * @param mixed $order       Order object.
	 * @param mixed $sent_to_admin (unused).
	 * @param mixed $plain_text  (unused).
	 * @param mixed $email       (unused).
	 */
	public function exit_email_order( $order = null, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		unset( $order, $sent_to_admin, $plain_text, $email );
		$this->context->exit();
	}

	/**
	 * Enters context before email resend.
	 *
	 * @param mixed $order Order object.
	 * @param mixed $type  Email type.
	 */
	public function enter_resend( $order, $type = '' ): void {
		unset( $type );
		$this->context->enter( $order );
	}

	/**
	 * Exits context after email resend.
	 *
	 * @param mixed $order Order object.
	 * @param mixed $type  Email type.
	 */
	public function exit_resend( $order = null, $type = '' ): void {
		unset( $order, $type );
		$this->context->exit();
	}

	/**
	 * Renders the My-Account order list total cell within the order context.
	 *
	 * Called by woocommerce_my_account_my_orders_column_order-total and renders
	 * the order total within a run() context to guarantee restoration.
	 *
	 * @param mixed $order Order object.
	 */
	public function render_order_total( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$this->context->run(
			$order,
			static function () use ( $order ) {
				echo wp_kses_post( $order->get_formatted_order_total() );
			}
		);
	}
}
