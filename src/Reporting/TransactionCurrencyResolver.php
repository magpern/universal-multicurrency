<?php
/**
 * Resolves transaction currency for reporting.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\Order\OrderCurrencySnapshot;
use WC_Order;

/**
 * Resolves transaction currency for reporting.
 */
final class TransactionCurrencyResolver {

	/**
	 * Resolves the transaction currency for one order.
	 *
	 * @param WC_Order              $order    WooCommerce order.
	 * @param OrderCurrencySnapshot $snapshot Persisted currency snapshot.
	 * @return array{currency: string|null, unresolvable: bool}
	 */
	public function resolve( WC_Order $order, OrderCurrencySnapshot $snapshot ): array {
		if ( $snapshot->has_snapshot() && null !== $snapshot->transaction_currency() && '' !== $snapshot->transaction_currency() ) {
			return array(
				'currency'     => $snapshot->transaction_currency(),
				'unresolvable' => false,
			);
		}

		if ( $snapshot->is_legacy() || ! $snapshot->has_snapshot() ) {
			$wc_currency = strtoupper( (string) $order->get_currency() );
			if ( '' !== $wc_currency && 1 === preg_match( '/^[A-Z]{3}$/', $wc_currency ) ) {
				return array(
					'currency'     => $wc_currency,
					'unresolvable' => false,
				);
			}
		}

		return array(
			'currency'     => null,
			'unresolvable' => true,
		);
	}
}
