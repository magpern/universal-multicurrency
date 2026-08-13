<?php
/**
 * Runtime product-price conversion filters.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Registers the view-context price filters and delegates amounts to the
 * {@see PriceConversionService}.
 *
 * Each callback short-circuits when the request is not convertible or the base
 * currency is active, and a re-entrancy guard prevents recursion. There is no
 * broad exception handling: the service is exception-safe on valid display
 * input, and unrelated programming errors must surface rather than be swallowed.
 */
final class PriceHooks {

	/**
	 * Product price getter filters (simple + variation), all `($value, $product)`.
	 */
	private const PRICE_FILTERS = array(
		'woocommerce_product_get_price',
		'woocommerce_product_get_regular_price',
		'woocommerce_product_get_sale_price',
		'woocommerce_product_variation_get_price',
		'woocommerce_product_variation_get_regular_price',
		'woocommerce_product_variation_get_sale_price',
		'woocommerce_variation_prices_price',
		'woocommerce_variation_prices_regular_price',
		'woocommerce_variation_prices_sale_price',
	);

	/**
	 * Conversion seam.
	 *
	 * @var PriceConversionService
	 */
	private PriceConversionService $service;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Re-entrancy guard.
	 *
	 * @var bool
	 */
	private bool $converting = false;

	/**
	 * Binds the hooks to the seam and the context.
	 *
	 * @param PriceConversionService $service Conversion seam.
	 * @param CurrencyContext        $context Request-scoped currency facade.
	 */
	public function __construct( PriceConversionService $service, CurrencyContext $context ) {
		$this->service = $service;
		$this->context = $context;
	}

	/**
	 * Registers all price filters.
	 *
	 * Attached unconditionally (on every request type) so the variation-price
	 * cache hash — which embeds attached-callback signatures — stays stable;
	 * each callback decides per request whether to convert.
	 */
	public function register(): void {
		foreach ( self::PRICE_FILTERS as $filter ) {
			add_filter( $filter, array( $this, 'convert_price' ), 10, 1 );
		}

		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'append_currency_to_hash' ), 10, 1 );
	}

	/**
	 * Converts a single price value in the active currency.
	 *
	 * @param mixed $value Base-currency price (may be '' for an unset sale price).
	 * @return mixed Converted value, or the input unchanged when not converting.
	 */
	public function convert_price( $value ) {
		if ( $this->converting || ! $this->should_convert() ) {
			return $value;
		}

		$this->converting = true;
		$converted        = $this->service->convert( $value );
		$this->converting = false;

		return $converted;
	}

	/**
	 * Adds the active currency + rate to the variation-price cache hash so
	 * cached prices never cross currencies (and self-invalidate on rate edits).
	 *
	 * @param array<int|string, mixed> $hash Hash components.
	 * @return array<int|string, mixed>
	 */
	public function append_currency_to_hash( $hash ) {
		if ( ! is_array( $hash ) || ! $this->should_convert() ) {
			return $hash;
		}

		$hash[] = $this->context->get_active_code();
		$hash[] = $this->context->get_rate();

		return $hash;
	}

	/**
	 * Whether the current request+currency should convert prices.
	 */
	private function should_convert(): bool {
		if ( ! $this->context->is_convertible_request() || $this->context->is_base_active() ) {
			return false;
		}

		/**
		 * Whether product prices should convert in the current request context.
		 *
		 * Extension adapters (e.g. Subscriptions renewals) may return false to
		 * prevent browsing currency from altering subscription-owned amounts.
		 *
		 * @since 0.18.0
		 *
		 * @param bool $should Default true when convertible and non-base active.
		 */
		return (bool) apply_filters( 'umc_should_convert_product_price', true );
	}
}
