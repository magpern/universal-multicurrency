<?php
/**
 * Historical order currency formatting filters.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Order;

/**
 * Overrides WooCommerce formatting filters when an order currency context is active.
 *
 * Two modes:
 *
 * 1. Context mode: When the order context is active, forces woocommerce_currency,
 *    woocommerce_currency_symbol, wc_get_price_decimals and wc_price_args to use
 *    the order's currency. These filters register at priority 20, after the M2
 *    session formatter (`Integration\CurrencyFormatting`, default priority 10), so
 *    while the context is active this formatter deterministically overrides the M2
 *    result for the order render. The two are mutually exclusive by construction:
 *    M2 only rewrites formatting on a convertible, non-base storefront request,
 *    and these callbacks only rewrite it while an order context is on the stack.
 *
 * 2. Currency-arg belt: When no context is active but wc_price_args contains an
 *    explicit 'currency' arg, resolves decimals for that currency. Covers admin
 *    (non-convertible) and fallback paths where the context wasn't entered.
 *
 * Decimals and formatting are resolved ONLY, never converted. Totals remain stored values.
 */
final class OrderCurrencyFormatting {

	/**
	 * Order currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $context;

	/**
	 * Formatting resolver (for currency-arg belt decimals).
	 *
	 * @var HistoricalFormattingResolver
	 */
	private HistoricalFormattingResolver $resolver;

	/**
	 * Binds the formatter to the context and resolver.
	 *
	 * @param OrderCurrencyContext         $context  Order currency context.
	 * @param HistoricalFormattingResolver $resolver Formatting resolver.
	 */
	public function __construct(
		OrderCurrencyContext $context,
		HistoricalFormattingResolver $resolver
	) {
		$this->context  = $context;
		$this->resolver = $resolver;
	}

	/**
	 * Registers the formatting filters.
	 */
	public function register(): void {
		add_filter( 'woocommerce_currency', array( $this, 'filter_currency' ), 20 );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'filter_symbol' ), 20, 2 );
		add_filter( 'wc_get_price_decimals', array( $this, 'filter_decimals' ), 20 );
		add_filter( 'wc_price_args', array( $this, 'filter_price_args' ), 20 );
	}

	/**
	 * Reports the order currency code (if context is active).
	 *
	 * @param string $code Store currency code.
	 */
	public function filter_currency( $code ) {
		if ( ! $this->context->is_active() ) {
			return $code;
		}

		$current = $this->context->current_code();
		return $current ? $current : $code;
	}

	/**
	 * Reports the order currency's symbol (if context is active).
	 *
	 * @param string $symbol Symbol WooCommerce resolved.
	 * @param string $code   Currency code being resolved.
	 */
	public function filter_symbol( $symbol, $code ) {
		if ( ! $this->context->is_active() ) {
			return $symbol;
		}

		$formatting = $this->context->current_formatting();
		if ( ! $formatting ) {
			return $symbol;
		}

		// Only override if the requested code matches the current order code.
		if ( strtoupper( (string) $code ) !== $formatting->code() ) {
			return $symbol;
		}

		$custom = $formatting->symbol();
		return '' !== $custom ? $custom : $symbol;
	}

	/**
	 * Reports the order currency's decimals (if context is active).
	 *
	 * @param int $decimals Store decimals.
	 */
	public function filter_decimals( $decimals ) {
		if ( ! $this->context->is_active() ) {
			return $decimals;
		}

		$formatting = $this->context->current_formatting();
		return $formatting ? $formatting->decimals() : $decimals;
	}

	/**
	 * Sets price format args to the order currency (both modes).
	 *
	 * Mode 1 (context): use order formatting (decimals, position, separators).
	 * Mode 2 (belt): if explicit 'currency' arg is present, resolve its decimals.
	 *
	 * @param array<string, mixed> $args wc_price() arguments.
	 */
	public function filter_price_args( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		// Mode 1: context active.
		if ( $this->context->is_active() ) {
			$formatting = $this->context->current_formatting();
			if ( $formatting ) {
				$args['decimals']     = $formatting->decimals();
				$args['price_format'] = $this->price_format( $formatting->position() );
			}
			return $args;
		}

		// Mode 2: explicit currency arg (belt).
		if ( empty( $args['currency'] ) ) {
			return $args;
		}

		$currency_code = (string) $args['currency'];
		if ( '' === $currency_code ) {
			return $args;
		}

		// Resolve decimals for this explicit currency.
		$decimals = $this->resolve_currency_decimals( $currency_code );
		if ( null !== $decimals ) {
			$args['decimals'] = $decimals;
		}

		return $args;
	}

	/**
	 * Resolves decimals for a currency code using the fallback chain.
	 *
	 * @param string $code Currency code.
	 */
	private function resolve_currency_decimals( string $code ): ?int {
		// Use the resolver's fallback chain.
		// Since we don't have a snapshot here, build a minimal one.
		$snapshot = new OrderCurrencySnapshot(
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null, // No stored decimals.
			false,
			true, // Legacy snapshot (forces fallback to config/ISO).
			false,
			false,
			false
		);

		$formatting = $this->resolver->resolve( $snapshot, $code );
		return $formatting->decimals();
	}

	/**
	 * Maps a symbol position to a WooCommerce price format string.
	 *
	 * @param string $position One of: 'left', 'left_space', 'right', 'right_space'.
	 */
	private function price_format( string $position ): string {
		switch ( $position ) {
			case 'right':
				return '%2$s%1$s';
			case 'left_space':
				return '%1$s&nbsp;%2$s';
			case 'right_space':
				return '%2$s&nbsp;%1$s';
			case 'left':
			default:
				return '%1$s%2$s';
		}
	}
}
