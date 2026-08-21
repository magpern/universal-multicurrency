<?php
/**
 * Runtime product-price conversion filters.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;
use UMC\Pricing\ProductPriceResolutionService;
use WC_Product;

/**
 * Registers product price filters and delegates to fixed-price resolution or
 * {@see PriceConversionService}.
 */
final class PriceHooks {

	public const FILTER_PRIORITY = 10;

	/**
	 * Fixed-vs-converted product price resolver.
	 *
	 * @var ProductPriceResolutionService
	 */
	private ProductPriceResolutionService $resolver;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * WooCommerce sale state helper for cache identity.
	 *
	 * @var ProductSaleStateResolver|null
	 */
	private ?\UMC\Pricing\ProductSaleStateResolver $sale_state;

	/**
	 * Re-entrancy guard while resolving nested getters.
	 *
	 * @var bool
	 */
	private bool $resolving = false;

	/**
	 * Binds the resolver, currency context, and optional sale-state helper.
	 *
	 * @param ProductPriceResolutionService              $resolver   Fixed/converted resolution seam.
	 * @param CurrencyContext                            $context    Request-scoped currency facade.
	 * @param \UMC\Pricing\ProductSaleStateResolver|null $sale_state Sale cache tokens.
	 */
	public function __construct(
		ProductPriceResolutionService $resolver,
		CurrencyContext $context,
		?\UMC\Pricing\ProductSaleStateResolver $sale_state = null
	) {
		$this->resolver   = $resolver;
		$this->context    = $context;
		$this->sale_state = $sale_state ?? new \UMC\Pricing\ProductSaleStateResolver();
	}

	/**
	 * Registers all price filters at the characterized priority.
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_product_get_price' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'filter_product_get_regular_price' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'filter_product_get_sale_price' ), self::FILTER_PRIORITY, 2 );

		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_variation_get_price' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'filter_variation_get_regular_price' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'filter_variation_get_sale_price' ), self::FILTER_PRIORITY, 2 );

		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_prices_price' ), self::FILTER_PRIORITY, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'filter_variation_prices_regular_price' ), self::FILTER_PRIORITY, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_variation_prices_sale_price' ), self::FILTER_PRIORITY, 3 );

		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'append_identity_to_hash' ), self::FILTER_PRIORITY, 3 );
	}

	/**
	 * Filters the simple product active price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Product instance.
	 */
	public function filter_product_get_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_PRICE );
	}

	/**
	 * Filters the simple product regular price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Product instance.
	 */
	public function filter_product_get_regular_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_REGULAR );
	}

	/**
	 * Filters the simple product sale price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Product instance.
	 */
	public function filter_product_get_sale_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_SALE );
	}

	/**
	 * Filters the variation active price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Variation product instance.
	 */
	public function filter_variation_get_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_PRICE );
	}

	/**
	 * Filters the variation regular price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Variation product instance.
	 */
	public function filter_variation_get_regular_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_REGULAR );
	}

	/**
	 * Filters the variation sale price getter.
	 *
	 * @param mixed $price   Base-authored price.
	 * @param mixed $product Variation product instance.
	 */
	public function filter_variation_get_sale_price( $price, $product ) {
		return $this->filter_product_price( $price, $product, ProductPriceResolutionService::FIELD_SALE );
	}

	/**
	 * Filters cached variation active prices.
	 *
	 * @param mixed $price            Base-authored price.
	 * @param mixed $variation        Variation product.
	 * @param mixed $variable_product Variable parent product.
	 */
	public function filter_variation_prices_price( $price, $variation, $variable_product ) {
		unset( $variable_product );

		return $this->filter_variation_prices( $price, $variation, ProductPriceResolutionService::FIELD_PRICE );
	}

	/**
	 * Filters cached variation regular prices.
	 *
	 * @param mixed $price            Base-authored price.
	 * @param mixed $variation        Variation product.
	 * @param mixed $variable_product Variable parent product.
	 */
	public function filter_variation_prices_regular_price( $price, $variation, $variable_product ) {
		unset( $variable_product );

		return $this->filter_variation_prices( $price, $variation, ProductPriceResolutionService::FIELD_REGULAR );
	}

	/**
	 * Filters cached variation sale prices.
	 *
	 * @param mixed $price            Base-authored price.
	 * @param mixed $variation        Variation product.
	 * @param mixed $variable_product Variable parent product.
	 */
	public function filter_variation_prices_sale_price( $price, $variation, $variable_product ) {
		unset( $variable_product );

		return $this->filter_variation_prices( $price, $variation, ProductPriceResolutionService::FIELD_SALE );
	}

	/**
	 * Resolves one product price getter through the fixed/converted seam.
	 *
	 * @param mixed  $price   Base-authored price.
	 * @param mixed  $product Product instance.
	 * @param string $field   Resolution field.
	 */
	public function filter_product_price( $price, $product, string $field ) {
		if ( ! $product instanceof WC_Product ) {
			return $price;
		}

		return $this->resolve( $price, $product, $field );
	}

	/**
	 * Resolves one cached variation price through the fixed/converted seam.
	 *
	 * Intentionally bypasses {@see $resolving}: WooCommerce may build the
	 * variation-price table while a parent `get_price()` resolve is in progress
	 * (`WC_Product_Variable::is_on_sale()` always calls `get_variation_prices()`).
	 * Blocking here would cache base amounts under the foreign-currency hash
	 * (ADR-0033). Values passed to these filters are already `'edit'`-context
	 * base amounts, so resolving them here cannot double-convert.
	 *
	 * @param mixed  $price     Base-authored price.
	 * @param mixed  $variation Variation product.
	 * @param string $field     Resolution field.
	 */
	public function filter_variation_prices( $price, $variation, string $field ) {
		if ( ! $variation instanceof WC_Product ) {
			return $price;
		}

		if ( ! $this->should_convert( $variation ) ) {
			return $price;
		}

		return $this->resolver->resolve( $price, $variation, $field )->amount();
	}

	/**
	 * Converts or fixes one product price field for the active currency.
	 *
	 * @param mixed      $price   Base-authored price.
	 * @param WC_Product $product Product or variation.
	 * @param string     $field   Resolution field.
	 */
	private function resolve( mixed $price, WC_Product $product, string $field ) {
		if ( $this->resolving || ! $this->should_convert( $product ) ) {
			return $price;
		}

		$this->resolving = true;
		$resolution      = $this->resolver->resolve( $price, $product, $field );
		$this->resolving = false;

		return $resolution->amount();
	}

	/**
	 * Adds currency, rate, fixed-price, and sale-state identity to variation cache hash.
	 *
	 * @param array<int|string, mixed> $hash        Hash components.
	 * @param mixed                    $product     Variable product.
	 * @param mixed                    $for_display Whether prices are for display.
	 * @return array<int|string, mixed>
	 */
	public function append_identity_to_hash( $hash, $product = null, $for_display = false ) {
		unset( $for_display );

		if ( ! is_array( $hash ) || ! $this->context->is_convertible_request() || $this->context->is_base_active() ) {
			return $hash;
		}

		$hash[] = $this->context->get_active_code();
		$hash[] = $this->context->get_rate();
		$hash[] = 'umc_fixed_v1';

		if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
			$hash[] = $this->variable_product_fixed_fingerprint( $product );
		}

		return $hash;
	}

	/**
	 * Builds a composite fixed-price fingerprint for all variations.
	 *
	 * @param WC_Product $product Variable product.
	 */
	private function variable_product_fixed_fingerprint( WC_Product $product ): string {
		$parts = array();

		foreach ( $product->get_children() as $child_id ) {
			$child_id = (int) $child_id;
			$child    = wc_get_product( $child_id );

			if ( ! $child instanceof WC_Product ) {
				continue;
			}

			$parts[] = $child_id . ':' . $this->resolver->fixed_price_fingerprint( $child ) . ':' . $this->sale_state->cache_token( $child );
		}

		sort( $parts, SORT_STRING );

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Whether product prices should convert for this request.
	 *
	 * @param WC_Product $product Product being priced.
	 */
	private function should_convert( WC_Product $product ): bool {
		if ( ! $this->context->is_convertible_request() || $this->context->is_base_active() ) {
			return false;
		}

		/**
		 * Whether product prices should convert in the current request context.
		 *
		 * @since 0.18.0
		 *
		 * @param bool       $should  Default true when convertible and non-base active.
		 * @param WC_Product $product Product being priced.
		 */
		$should = (bool) apply_filters( 'umc_should_convert_product_price', true, $product );

		return $should;
	}
}
