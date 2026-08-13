<?php
/**
 * Unit tests for RefundValueResolver.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Reporting;

use PHPUnit\Framework\TestCase;
use UMC\Reporting\RefundValueResolver;
use WC_Order;

/**
 * Verifies the single refund authority delegates to WooCommerce totals.
 */
final class RefundValueResolverTest extends TestCase {

	public function test_no_refund_returns_zero(): void {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_total_refunded' )->willReturn( 0.0 );

		$this->assertSame( 0.0, ( new RefundValueResolver() )->refunded_value( $order ) );
	}

	public function test_partial_refund_returns_wc_total_refunded(): void {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_total_refunded' )->willReturn( 25.5 );

		$this->assertSame( 25.5, ( new RefundValueResolver() )->refunded_value( $order ) );
	}

	public function test_multiple_partial_refunds_use_cumulative_wc_total(): void {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_total_refunded' )->willReturn( 80.0 );

		$this->assertSame( 80.0, ( new RefundValueResolver() )->refunded_value( $order ) );
	}
}
