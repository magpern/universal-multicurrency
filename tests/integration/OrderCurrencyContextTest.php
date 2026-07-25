<?php
/**
 * Integration tests for order currency context stack.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderSnapshotReader;
use UMC\Settings;
use WC_Order;
use WP_UnitTestCase;

/**
 * Verifies order context stack lifecycle and LIFO properties.
 */
final class OrderCurrencyContextTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * Initially, the context is inactive.
	 */
	public function test_context_initially_inactive(): void {
		$context = $this->create_context();

		$this->assertFalse( $context->is_active() );
		$this->assertSame( 0, $context->depth() );
		$this->assertNull( $context->current_code() );
		$this->assertNull( $context->current_formatting() );
	}

	/**
	 * Push and pop work correctly.
	 */
	public function test_enter_and_exit(): void {
		$context = $this->create_context();
		$order   = $this->create_order_with_currency( 'EUR' );

		$context->enter( $order );

		$this->assertTrue( $context->is_active() );
		$this->assertSame( 1, $context->depth() );
		$this->assertSame( 'EUR', $context->current_code() );

		$context->exit();

		$this->assertFalse( $context->is_active() );
		$this->assertSame( 0, $context->depth() );
	}

	/**
	 * Stack is LIFO (nested pushes restore correctly).
	 */
	public function test_stack_is_lifo(): void {
		$context = $this->create_context();
		$order_a = $this->create_order_with_currency( 'EUR' );
		$order_b = $this->create_order_with_currency( 'JPY' );

		$context->enter( $order_a );
		$this->assertSame( 'EUR', $context->current_code() );

		$context->enter( $order_b );
		$this->assertSame( 'JPY', $context->current_code() );
		$this->assertSame( 2, $context->depth() );

		$context->exit();
		$this->assertSame( 'EUR', $context->current_code() );
		$this->assertSame( 1, $context->depth() );

		$context->exit();
		$this->assertFalse( $context->is_active() );
	}

	/**
	 * Exit on an empty stack is a no-op (guarded).
	 */
	public function test_exit_on_empty_stack_is_no_op(): void {
		$context = $this->create_context();

		$context->exit(); // No exception.
		$this->assertSame( 0, $context->depth() );
	}

	/**
	 * Run restores on normal return.
	 */
	public function test_run_restores_on_normal_return(): void {
		$context = $this->create_context();
		$order   = $this->create_order_with_currency( 'EUR' );
		$called  = false;

		$result = $context->run(
			$order,
			static function () use ( &$called ) {
				$called = true;
				return 'test_result';
			}
		);

		$this->assertTrue( $called );
		$this->assertSame( 'test_result', $result );
		$this->assertFalse( $context->is_active() );
	}

	/**
	 * Run restores on thrown exception (try/finally).
	 */
	public function test_run_restores_on_exception(): void {
		$context = $this->create_context();
		$order   = $this->create_order_with_currency( 'EUR' );

		$caught = null;

		try {
			$context->run(
				$order,
				static function () {
					throw new \Exception( 'test_error' );
				}
			);
		} catch ( \Exception $e ) {
			$caught = $e;
		}

		$this->assertNotNull( $caught );
		$this->assertSame( 'test_error', $caught->getMessage() );
		// Even though an exception was thrown, the context was restored.
		$this->assertFalse( $context->is_active() );
	}

	/**
	 * Non-order arguments to enter() are safely ignored.
	 */
	public function test_enter_with_non_order_is_ignored(): void {
		$context = $this->create_context();

		$context->enter( 'not an order' );

		$this->assertFalse( $context->is_active() );
	}

	/**
	 * Creates a context with real dependencies.
	 */
	private function create_context(): OrderCurrencyContext {
		update_option( 'woocommerce_currency', 'EUR' );

		$registry = new CurrencyRegistry( new Settings(), new Currency( 'EUR', 2 ) );
		$reader   = new OrderSnapshotReader();
		$resolver = new HistoricalFormattingResolver( $registry );

		return new OrderCurrencyContext( $reader, $resolver );
	}

	/**
	 * Creates a WC_Order with a specific currency.
	 *
	 * @param string $currency Order currency code.
	 */
	private function create_order_with_currency( string $currency ): WC_Order {
		$order = new WC_Order();
		$order->set_currency( $currency );
		$order->save();

		return $order;
	}
}
