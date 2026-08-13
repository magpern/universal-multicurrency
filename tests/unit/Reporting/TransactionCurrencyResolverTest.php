<?php
/**
 * Unit tests for TransactionCurrencyResolver.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Reporting;

use PHPUnit\Framework\TestCase;
use UMC\Order\OrderCurrencySnapshot;
use UMC\Reporting\TransactionCurrencyResolver;
use WC_Order;

/**
 * Verifies frozen transaction-currency precedence for reporting.
 */
final class TransactionCurrencyResolverTest extends TestCase {

	/**
	 * Resolver under test.
	 *
	 * @var TransactionCurrencyResolver
	 */
	private TransactionCurrencyResolver $resolver;

	protected function setUp(): void {
		parent::setUp();

		$this->resolver = new TransactionCurrencyResolver();
	}

	public function test_valid_snapshot_transaction_currency_wins(): void {
		$order    = $this->order_with_currency( 'EUR' );
		$snapshot = $this->snapshot( 'SEK', false, true );

		$result = $this->resolver->resolve( $order, $snapshot );

		$this->assertSame(
			array(
				'currency'     => 'SEK',
				'unresolvable' => false,
			),
			$result
		);
	}

	public function test_legacy_order_falls_back_to_wc_currency(): void {
		$order    = $this->order_with_currency( 'USD' );
		$snapshot = $this->snapshot( null, true, false );

		$result = $this->resolver->resolve( $order, $snapshot );

		$this->assertSame(
			array(
				'currency'     => 'USD',
				'unresolvable' => false,
			),
			$result
		);
	}

	public function test_missing_snapshot_falls_back_to_wc_currency(): void {
		$order    = $this->order_with_currency( 'JPY' );
		$snapshot = $this->snapshot( null, false, false, false );

		$result = $this->resolver->resolve( $order, $snapshot );

		$this->assertSame(
			array(
				'currency'     => 'JPY',
				'unresolvable' => false,
			),
			$result
		);
	}

	public function test_invalid_wc_currency_is_unresolvable(): void {
		$order    = $this->order_with_currency( 'INVALID' );
		$snapshot = $this->snapshot( null, true, false );

		$result = $this->resolver->resolve( $order, $snapshot );

		$this->assertSame(
			array(
				'currency'     => null,
				'unresolvable' => true,
			),
			$result
		);
	}

	public function test_empty_snapshot_and_wc_currency_is_unresolvable(): void {
		$order    = $this->order_with_currency( '' );
		$snapshot = $this->snapshot( '', false, false, false );

		$result = $this->resolver->resolve( $order, $snapshot );

		$this->assertTrue( $result['unresolvable'] );
		$this->assertNull( $result['currency'] );
	}

	private function order_with_currency( string $currency ): WC_Order {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_currency' )->willReturn( $currency );

		return $order;
	}

	private function snapshot(
		?string $transaction_currency,
		bool $is_legacy,
		bool $has_snapshot,
		bool $include_transaction = true
	): OrderCurrencySnapshot {
		return new OrderCurrencySnapshot(
			$include_transaction ? 5 : null,
			'EUR',
			$include_transaction ? $transaction_currency : null,
			'11.50',
			1_700_000_000,
			'manual',
			'0.20.0',
			'SEK:11.50',
			2,
			$has_snapshot,
			$is_legacy,
			false,
			false,
			false
		);
	}
}
