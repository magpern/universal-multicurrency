<?php
/**
 * Lightweight historical facts for one order.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\Order\OrderCurrencySnapshot;

/**
 * Lightweight historical facts for one order.
 */
final class OrderReportRecord {

	/**
	 * Captures reporting facts for one order.
	 *
	 * @param int                                       $order_id              WooCommerce order ID.
	 * @param OrderCurrencySnapshot                     $snapshot              Persisted currency snapshot.
	 * @param string|null                               $transaction_currency  Resolved transaction currency.
	 * @param bool                                      $unresolvable_currency Whether currency is unresolvable.
	 * @param float                                     $order_value           Gross order value.
	 * @param float                                     $refunded_value        Refunded value.
	 * @param string                                    $reporting_origin      Classified currency origin.
	 * @param bool|null                                 $fallback_occurred     Checkout fallback flag.
	 * @param string|null                               $shopper_currency      Shopper currency at checkout.
	 * @param string|null                               $checkout_mode         Checkout mode at placement.
	 * @param list<array{source: string, total: float}> $line_sources          Product-line pricing sources.
	 */
	public function __construct(
		private int $order_id,
		private OrderCurrencySnapshot $snapshot,
		private ?string $transaction_currency,
		private bool $unresolvable_currency,
		private float $order_value,
		private float $refunded_value,
		private string $reporting_origin,
		private ?bool $fallback_occurred,
		private ?string $shopper_currency,
		private ?string $checkout_mode,
		private array $line_sources
	) {
	}

	/**
	 * WooCommerce order ID.
	 */
	public function order_id(): int {
		return $this->order_id;
	}

	/**
	 * Persisted currency snapshot.
	 */
	public function snapshot(): OrderCurrencySnapshot {
		return $this->snapshot;
	}

	/**
	 * Resolved transaction currency.
	 */
	public function transaction_currency(): ?string {
		return $this->transaction_currency;
	}

	/**
	 * Whether transaction currency could not be resolved.
	 */
	public function unresolvable_currency(): bool {
		return $this->unresolvable_currency;
	}

	/**
	 * Gross order value.
	 */
	public function order_value(): float {
		return $this->order_value;
	}

	/**
	 * Refunded value.
	 */
	public function refunded_value(): float {
		return $this->refunded_value;
	}

	/**
	 * Order value minus refunds.
	 */
	public function net_order_value(): float {
		return max( 0.0, $this->order_value - $this->refunded_value );
	}

	/**
	 * Classified currency origin.
	 */
	public function reporting_origin(): string {
		return $this->reporting_origin;
	}

	/**
	 * Whether checkout fallback occurred.
	 */
	public function fallback_occurred(): ?bool {
		return $this->fallback_occurred;
	}

	/**
	 * Shopper currency at checkout.
	 */
	public function shopper_currency(): ?string {
		return $this->shopper_currency;
	}

	/**
	 * Checkout mode at order placement.
	 */
	public function checkout_mode(): ?string {
		return $this->checkout_mode;
	}

	/**
	 * Product-line pricing sources and totals.
	 *
	 * @return list<array{source: string, total: float}>
	 */
	public function line_sources(): array {
		return $this->line_sources;
	}
}
