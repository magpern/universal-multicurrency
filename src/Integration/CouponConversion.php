<?php
/**
 * Coupon amount conversion.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Integration;

use UMC\CurrencyContext;
use WC_Coupon;

/**
 * Converts fixed-value coupon amounts and spend thresholds base→active.
 *
 * Fixed cart / fixed product coupon amounts are authored in the store base
 * currency; they are converted **once** here so the discount subtracted from the
 * (already-converted) cart matches the customer's currency. Percentage coupons
 * are left untouched — they operate on already-converted cart totals natively.
 * Minimum / maximum spend thresholds are monetary for every coupon type, so they
 * are always converted, keeping the comparison against the converted subtotal
 * apples-to-apples.
 *
 * Conversion runs through {@see PriceConversionService::convert_amount()} only;
 * this class never touches the {@see \UMC\Converter} directly and never converts
 * a product price (that stays M2's single responsibility), so a coupon amount is
 * never converted twice.
 */
final class CouponConversion {

	/**
	 * Discount types whose amount is a fixed monetary value in the base currency.
	 */
	private const FIXED_TYPES = array( 'fixed_cart', 'fixed_product' );

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
	 * Binds the service to the seam and the context.
	 *
	 * @param PriceConversionService $service Conversion seam.
	 * @param CurrencyContext        $context Request-scoped currency facade.
	 */
	public function __construct( PriceConversionService $service, CurrencyContext $context ) {
		$this->service = $service;
		$this->context = $context;
	}

	/**
	 * Registers the coupon amount + threshold filters.
	 */
	public function register(): void {
		add_filter( 'woocommerce_coupon_get_amount', array( $this, 'convert_coupon_amount' ), 10, 2 );
		add_filter( 'woocommerce_coupon_get_minimum_amount', array( $this, 'convert_threshold' ), 10, 2 );
		add_filter( 'woocommerce_coupon_get_maximum_amount', array( $this, 'convert_threshold' ), 10, 2 );
	}

	/**
	 * Converts a fixed-value coupon amount; leaves percentage coupons untouched.
	 *
	 * @param mixed          $amount Coupon amount in base currency.
	 * @param WC_Coupon|null $coupon Coupon being read.
	 * @return mixed Converted amount, or the input unchanged.
	 */
	public function convert_coupon_amount( $amount, $coupon = null ) {
		if ( ! $this->should_convert( $coupon ) ) {
			return $amount;
		}

		if ( ! $coupon instanceof WC_Coupon || ! in_array( $coupon->get_discount_type(), self::FIXED_TYPES, true ) ) {
			return $amount;
		}

		return $this->convert( $amount );
	}

	/**
	 * Converts a minimum / maximum spend threshold for any coupon type.
	 *
	 * @param mixed          $amount Threshold in base currency.
	 * @param WC_Coupon|null $coupon Coupon being read.
	 * @return mixed Converted amount, or the input unchanged.
	 */
	public function convert_threshold( $amount, $coupon = null ) {
		if ( ! $this->should_convert( $coupon ) ) {
			return $amount;
		}

		return $this->convert( $amount );
	}

	/**
	 * Converts a numeric, non-zero amount; passes anything else through.
	 *
	 * @param mixed $amount Candidate amount.
	 * @return mixed
	 */
	private function convert( $amount ) {
		if ( ! is_numeric( $amount ) || 0.0 === (float) $amount ) {
			return $amount;
		}

		return $this->service->convert_amount( $amount );
	}

	/**
	 * Whether the coupon's base-currency amounts should be converted.
	 *
	 * @param WC_Coupon|null $coupon Coupon being read.
	 */
	private function should_convert( $coupon ): bool {
		if ( ! $this->context->is_convertible_request() || $this->context->is_base_active() ) {
			return false;
		}

		/**
		 * Whether this coupon's monetary values are authored in the base currency.
		 *
		 * Return false to declare the coupon already priced in the active currency
		 * (e.g. an integration that stores per-currency coupon amounts), which
		 * skips conversion for it.
		 *
		 * @since 0.3.0
		 *
		 * @param bool           $is_base Whether coupon amounts are base-currency.
		 * @param WC_Coupon|null $coupon  Coupon being read.
		 */
		return (bool) apply_filters( 'umc_coupon_amount_is_base', true, $coupon );
	}
}
