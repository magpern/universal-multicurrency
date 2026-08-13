<?php
/**
 * Fixed-vs-converted product price resolution.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\Integration\DisplayPriceConverter;
use UMC\Integration\PriceConversionService;
use WC_Product;

/**
 * Single seam deciding fixed foreign prices vs FX conversion.
 */
final class ProductPriceResolutionService {

	public const FIELD_REGULAR = 'regular';
	public const FIELD_SALE    = 'sale';
	public const FIELD_PRICE   = 'price';

	/**
	 * Binds fixed-price resolution dependencies.
	 *
	 * @param FixedPriceRepository           $repository   Fixed price meta access.
	 * @param ProductSaleStateResolver       $sale_state   WC sale activation.
	 * @param DisplayPriceConverter          $converter    FX conversion seam.
	 * @param CurrencyContext                $context      Active currency facade.
	 * @param CurrencyRegistry               $registry     Currency enablement.
	 * @param ProductPriceProvenanceRegistry $provenance   Checkout provenance map.
	 */
	public function __construct(
		private FixedPriceRepository $repository,
		private ProductSaleStateResolver $sale_state,
		private DisplayPriceConverter $converter,
		private CurrencyContext $context,
		private CurrencyRegistry $registry,
		private ProductPriceProvenanceRegistry $provenance
	) {
	}

	/**
	 * Resolves one WooCommerce product price getter value.
	 *
	 * @param mixed      $base_value Base-authored amount from WooCommerce.
	 * @param WC_Product $product    Product or variation.
	 * @param string     $field      {@see FIELD_REGULAR}, {@see FIELD_SALE}, or {@see FIELD_PRICE}.
	 */
	public function resolve( mixed $base_value, WC_Product $product, string $field ): ProductPriceResolution {
		$currency_code = $this->context->get_active_code();

		if ( $this->registry->is_base( $currency_code ) ) {
			return new ProductPriceResolution( $base_value, ProductPriceResolution::SOURCE_CONVERTED, $currency_code, $field );
		}

		$document = $this->repository->get( (int) $product->get_id() );
		/**
		 * Whether fixed foreign prices may override conversion for this product.
		 *
		 * @since 0.19.0
		 *
		 * @param bool       $use_fixed Default true when fixed prices are enabled.
		 * @param WC_Product $product   Product or variation being priced.
		 */
		$use_fixed = (bool) apply_filters( 'umc_use_fixed_product_price', true, $product );
		$fixed     = ( $use_fixed && $this->currency_enabled( $currency_code ) )
			? $document->get_currency( $currency_code )
			: null;
		$on_sale   = $this->sale_state->is_on_sale( $product );

		$amount = match ( $field ) {
			self::FIELD_REGULAR => $this->resolve_regular( $base_value, $fixed ),
			self::FIELD_SALE    => $this->resolve_sale( $base_value, $fixed, $on_sale ),
			self::FIELD_PRICE   => $this->resolve_active_price( $base_value, $fixed, $on_sale ),
			default             => $this->converter->convert( $base_value ),
		};

		$used_fixed = $this->used_fixed_for_field( $field, $fixed, $on_sale );
		$source     = $used_fixed ? ProductPriceResolution::SOURCE_FIXED : ProductPriceResolution::SOURCE_CONVERTED;

		if ( self::FIELD_PRICE === $field ) {
			$this->provenance->record( (int) $product->get_id(), $source, $currency_code );
		}

		return new ProductPriceResolution( $amount, $source, $currency_code, $field );
	}

	/**
	 * Fingerprint token for variation price caching.
	 *
	 * @param WC_Product $product Variable parent or variation.
	 */
	public function fixed_price_fingerprint( WC_Product $product ): string {
		return $this->repository->get( (int) $product->get_id() )->fingerprint();
	}

	/**
	 * Resolves the regular price field.
	 *
	 * @param mixed                   $base_value Base regular price.
	 * @param FixedCurrencyPrice|null $fixed      Fixed prices for active currency.
	 */
	private function resolve_regular( mixed $base_value, ?FixedCurrencyPrice $fixed ): mixed {
		if ( null !== $fixed && '' !== $fixed->regular() ) {
			return $fixed->regular();
		}

		return $this->converter->convert( $base_value );
	}

	/**
	 * Resolves the sale price field.
	 *
	 * @param mixed                   $base_value Base sale price.
	 * @param FixedCurrencyPrice|null $fixed      Fixed prices for active currency.
	 * @param bool                    $on_sale    WC sale state.
	 */
	private function resolve_sale( mixed $base_value, ?FixedCurrencyPrice $fixed, bool $on_sale ): mixed {
		if ( ! $on_sale ) {
			return $base_value;
		}

		if ( null !== $fixed && '' !== $fixed->sale() ) {
			return $fixed->sale();
		}

		return $this->converter->convert( $base_value );
	}

	/**
	 * Resolves the active price field.
	 *
	 * @param mixed                   $base_value Base active price from WC.
	 * @param FixedCurrencyPrice|null $fixed      Fixed prices for active currency.
	 * @param bool                    $on_sale    WC sale state.
	 */
	private function resolve_active_price( mixed $base_value, ?FixedCurrencyPrice $fixed, bool $on_sale ): mixed {
		if ( $on_sale ) {
			if ( null !== $fixed && '' !== $fixed->sale() ) {
				return $fixed->sale();
			}

			return $this->converter->convert( $base_value );
		}

		if ( null !== $fixed && '' !== $fixed->regular() ) {
			return $fixed->regular();
		}

		return $this->converter->convert( $base_value );
	}

	/**
	 * Whether the resolved field came from fixed pricing.
	 *
	 * @param string                  $field   Price field being resolved.
	 * @param FixedCurrencyPrice|null $fixed   Fixed prices for active currency.
	 * @param bool                    $on_sale WC sale state.
	 */
	private function used_fixed_for_field( string $field, ?FixedCurrencyPrice $fixed, bool $on_sale ): bool {
		if ( null === $fixed ) {
			return false;
		}

		return match ( $field ) {
			self::FIELD_REGULAR => '' !== $fixed->regular(),
			self::FIELD_SALE    => $on_sale && '' !== $fixed->sale(),
			self::FIELD_PRICE   => $on_sale ? '' !== $fixed->sale() : '' !== $fixed->regular(),
			default             => false,
		};
	}

	/**
	 * Whether the currency is enabled in UMC settings.
	 *
	 * @param string $code Currency code.
	 */
	private function currency_enabled( string $code ): bool {
		$currency = $this->registry->get_currency( $code );

		return null !== $currency && $currency->is_enabled();
	}
}
