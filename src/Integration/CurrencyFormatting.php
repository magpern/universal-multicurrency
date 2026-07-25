<?php
/**
 * Currency identity and price formatting for the active currency.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Makes WooCommerce report the active currency's code, symbol, decimals and
 * symbol position while a convertible request is being served.
 *
 * Gated identically to {@see PriceHooks}: only frontend convertible requests
 * with a non-base active currency are affected; everything else sees the store
 * defaults. Per-currency thousand/decimal separators are not part of the
 * Milestone 2 settings schema, so those are intentionally left to WooCommerce.
 */
final class CurrencyFormatting {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the formatting filters to the context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the currency identity + formatting filters.
	 */
	public function register(): void {
		add_filter( 'woocommerce_currency', array( $this, 'filter_currency' ) );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'filter_symbol' ), 10, 2 );
		add_filter( 'wc_get_price_decimals', array( $this, 'filter_decimals' ) );
		add_filter( 'wc_price_args', array( $this, 'filter_price_args' ) );
	}

	/**
	 * Reports the active currency code.
	 *
	 * @param string $code Store currency code.
	 */
	public function filter_currency( $code ) {
		return $this->should_convert() ? $this->context->get_active_code() : $code;
	}

	/**
	 * Reports the active currency's custom symbol, when one is configured.
	 *
	 * @param string $symbol Symbol WooCommerce resolved for $code.
	 * @param string $code   Currency code being resolved.
	 * @return string
	 */
	public function filter_symbol( $symbol, $code ) {
		if ( ! $this->should_convert() ) {
			return $symbol;
		}

		$active = $this->context->get_active_currency();

		if ( strtoupper( (string) $code ) !== $active->code() ) {
			return $symbol;
		}

		$custom = $active->symbol();

		return '' !== $custom ? $custom : $symbol;
	}

	/**
	 * Reports the active currency's decimals.
	 *
	 * @param int $decimals Store decimals.
	 * @return int
	 */
	public function filter_decimals( $decimals ) {
		return $this->should_convert() ? $this->context->get_active_currency()->decimals() : $decimals;
	}

	/**
	 * Sets the price format to the active currency's symbol position.
	 *
	 * @param array<string, mixed> $args wc_price() arguments.
	 * @return array<string, mixed>
	 */
	public function filter_price_args( $args ) {
		if ( ! is_array( $args ) || ! $this->should_convert() ) {
			return $args;
		}

		$args['price_format'] = $this->price_format( $this->context->get_active_currency()->position() );

		return $args;
	}

	/**
	 * Whether the current request+currency should be reformatted.
	 */
	private function should_convert(): bool {
		return $this->context->is_convertible_request() && ! $this->context->is_base_active();
	}

	/**
	 * Maps a symbol position to a WooCommerce price format string.
	 *
	 * @param string $position One of the Currency positions.
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
