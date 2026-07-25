<?php

/**
 * Resolve formatting for historical order currencies.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Support\IsoCurrencyDecimals;

/**
 * Resolves the formatting (decimals, symbol, position) for a historical order's
 * currency using the fallback chain:
 *
 * 1. Stored decimals from the snapshot (M4+)
 * 2. Current currency configuration (symbol, position)
 * 3. ISO-4217 decimal map for disabled/removed currencies
 * 4. WooCommerce default of 2 for unknown codes
 *
 * Symbol and position always resolve from the current configuration (they are
 * presentation-only and may change via localization or merchant preferences).
 * Decimals are resolved from storage first to preserve historical precision.
 *
 * This class does NOT depend on session, rates, or the active currency context.
 */
final class HistoricalFormattingResolver {

	/**
	 * Currency registry (for live config lookup).
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Binds the resolver to the registry.
	 *
	 * @param CurrencyRegistry $registry Currency registry.
	 */
	public function __construct( CurrencyRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Resolves formatting for an order's currency from the snapshot.
	 *
	 * @param OrderCurrencySnapshot $snapshot      Snapshot read from the order.
	 * @param string                $order_currency The order's stored currency code.
	 * @return ResolvedOrderCurrencyFormatting
	 */
	public function resolve(
		OrderCurrencySnapshot $snapshot,
		string $order_currency
	): ResolvedOrderCurrencyFormatting {
		$code = strtoupper( trim( $order_currency ) );

		// Resolve decimals: stored → current config → ISO map → 2.
		$decimals = $this->resolve_decimals( $snapshot, $code );

		// Resolve symbol and position from current configuration, or use defaults.
		$currency = $this->registry->get_currency( $code );
		$symbol   = $currency ? $currency->symbol() : '';
		$position = $currency ? $currency->position() : Currency::DEFAULT_POSITION;

		return new ResolvedOrderCurrencyFormatting(
			$code,
			$decimals,
			$symbol,
			$position
		);
	}

	/**
	 * Resolves decimal places using the fallback chain.
	 *
	 * @param OrderCurrencySnapshot $snapshot Snapshot read from the order.
	 * @param string                $code     Currency code.
	 */
	private function resolve_decimals(
		OrderCurrencySnapshot $snapshot,
		string $code
	): int {
		// 1. Stored decimals (M4+).
		$stored = $snapshot->stored_decimals();
		if ( null !== $stored ) {
			return $stored;
		}

		// 2. Current configuration.
		$currency = $this->registry->get_currency( $code );
		if ( $currency ) {
			return $currency->decimals();
		}

		// 3. ISO-4217 map.
		$iso_decimals = IsoCurrencyDecimals::decimals( $code );
		if ( $iso_decimals !== 2 ) {
			return $iso_decimals;
		}

		// 4. WooCommerce default.
		return 2;
	}
}
