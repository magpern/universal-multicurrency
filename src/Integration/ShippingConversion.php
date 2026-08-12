<?php
/**
 * Core shipping-rate conversion and per-currency package-cache isolation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;
use WC_Shipping_Rate;

/**
 * Converts WooCommerce **core** shipping-method rates base→active and isolates
 * the shipping-rate cache per currency and rate.
 *
 * Only core methods (`flat_rate`, `free_shipping`, `local_pickup`) are assumed to
 * author their costs in the store base currency and are converted here. Any
 * third-party / dynamically-returned rate is left untouched — it is assumed to
 * already return the transaction currency — but the decision is filterable per
 * rate via `umc_convert_shipping_rate` (opt-out for core, opt-in for others).
 *
 * The rate cost and its per-class taxes are scaled by the same exchange rate, so
 * `tax = cost × tax_rate` stays consistent after conversion. Conversion runs
 * through {@see PriceConversionService::convert_amount()} only.
 *
 * Free-shipping **minimum order amounts** are also base-authored. WooCommerce
 * compares them to the (already converted) cart subtotal; without converting the
 * threshold, eligibility is wrong in foreign currencies. Milestone 18 reconverts
 * the threshold at evaluation time via
 * `woocommerce_shipping_free_shipping_is_available` — request-scoped only, never
 * persisted into method settings, and never by mutating the shared method's
 * `$min_amount` permanently.
 *
 * WooCommerce caches calculated rates in the session keyed by a hash of the
 * package; identical packages across currencies would otherwise reuse one
 * currency's cached rates for another. Injecting the rate identity into each
 * package makes that hash currency-specific, so the cache self-invalidates on a
 * currency switch or a rate edit.
 */
final class ShippingConversion {

	/**
	 * Core WooCommerce shipping method ids whose costs are base-currency.
	 */
	private const CORE_METHODS = array( 'flat_rate', 'free_shipping', 'local_pickup' );

	/**
	 * Package key carrying the rate identity for cache isolation.
	 */
	private const PACKAGE_SIGNATURE_KEY = 'umc_currency_signature';

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
	 * Registers the rate-conversion, free-shipping threshold, and package-cache filters.
	 */
	public function register(): void {
		add_filter( 'woocommerce_package_rates', array( $this, 'convert_rates' ), 90, 2 );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'isolate_package_cache' ), 10, 1 );
		add_filter( 'woocommerce_shipping_free_shipping_is_available', array( $this, 'filter_free_shipping_availability' ), 10, 3 );
	}

	/**
	 * Converts eligible core-method rate costs and taxes in a package.
	 *
	 * @param mixed $rates   Array of `WC_Shipping_Rate` keyed by rate id.
	 * @param mixed $package Shipping package (context for the opt-out filter).
	 * @return mixed
	 */
	public function convert_rates( $rates, $package = array() ) {
		if ( ! is_array( $rates ) || ! $this->should_convert() ) {
			return $rates;
		}

		foreach ( $rates as $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && $this->should_convert_rate( $rate, $package ) ) {
				$this->convert_rate( $rate );
			}
		}

		return $rates;
	}

	/**
	 * Adds the active rate identity to each package so the shipping-rate cache is
	 * keyed per currency and rate.
	 *
	 * @param mixed $packages Shipping packages.
	 * @return mixed
	 */
	public function isolate_package_cache( $packages ) {
		if ( ! is_array( $packages ) || ! $this->should_convert() ) {
			return $packages;
		}

		$signature = $this->context->get_currency_signature();

		foreach ( $packages as $index => $package ) {
			if ( is_array( $package ) ) {
				$packages[ $index ][ self::PACKAGE_SIGNATURE_KEY ] = $signature;
			}
		}

		return $packages;
	}

	/**
	 * Re-evaluates free-shipping eligibility using a converted min_amount.
	 *
	 * WooCommerce has already compared the base-authored threshold to the
	 * active-currency cart total. When converting, that comparison is wrong; this
	 * filter replaces the boolean using the same cart-total rules WooCommerce
	 * uses, against a one-shot converted threshold. The method object's
	 * `$min_amount` and persisted settings are left untouched.
	 *
	 * @param mixed $is_available Whether WooCommerce considered the method available.
	 * @param mixed $package      Shipping package.
	 * @param mixed $method       Free shipping method instance.
	 * @return mixed
	 */
	public function filter_free_shipping_availability( $is_available, $package = array(), $method = null ) {
		unset( $package );

		if ( ! $this->should_convert() || ! is_object( $method ) ) {
			return $is_available;
		}

		$requires = isset( $method->requires ) ? (string) $method->requires : '';

		if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
			return $is_available;
		}

		$base_min = isset( $method->min_amount ) ? $method->min_amount : 0;

		if ( ! is_numeric( $base_min ) ) {
			return $is_available;
		}

		$converted_min      = (float) $this->service->convert_amount( $base_min );
		$has_met_min_amount = $this->cart_meets_free_shipping_minimum( $method, $converted_min );
		$has_coupon         = $this->cart_has_free_shipping_coupon();

		switch ( $requires ) {
			case 'min_amount':
				return $has_met_min_amount;
			case 'either':
				return $has_met_min_amount || $has_coupon;
			case 'both':
				return $has_met_min_amount && $has_coupon;
			default:
				return $is_available;
		}
	}

	/**
	 * Whether the current cart meets a free-shipping minimum (active currency).
	 *
	 * Mirrors `WC_Shipping_Free_Shipping::is_available()` total composition so
	 * `ignore_discounts` and tax-display semantics stay identical.
	 *
	 * @param object $method        Free shipping method instance.
	 * @param float  $converted_min Threshold already converted to the active currency.
	 */
	private function cart_meets_free_shipping_minimum( object $method, float $converted_min ): bool {
		if ( null === WC()->cart ) {
			return false;
		}

		$total = (float) WC()->cart->get_displayed_subtotal();

		$ignore_discounts = isset( $method->ignore_discounts ) ? (string) $method->ignore_discounts : 'no';

		if ( 'no' === $ignore_discounts ) {
			$total -= (float) WC()->cart->get_discount_total();

			if ( WC()->cart->display_prices_including_tax() ) {
				$total -= (float) WC()->cart->get_discount_tax();
			}
		}

		$total = round( $total, wc_get_price_decimals() );

		return $total >= $converted_min;
	}

	/**
	 * Whether the cart holds a valid free-shipping coupon.
	 */
	private function cart_has_free_shipping_coupon(): bool {
		if ( null === WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_coupons() as $coupon ) {
			if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Scales a rate's cost and per-class taxes by the active exchange rate.
	 *
	 * @param WC_Shipping_Rate $rate Rate to convert in place.
	 */
	private function convert_rate( WC_Shipping_Rate $rate ): void {
		$cost = $rate->get_cost();

		if ( is_numeric( $cost ) && 0.0 !== (float) $cost ) {
			$rate->set_cost( $this->service->convert_amount( $cost ) );
		}

		$taxes = $rate->get_taxes();

		if ( is_array( $taxes ) && array() !== $taxes ) {
			$converted = array();

			foreach ( $taxes as $key => $tax ) {
				$converted[ $key ] = ( is_numeric( $tax ) && 0.0 !== (float) $tax )
					? $this->service->convert_amount( $tax )
					: $tax;
			}

			$rate->set_taxes( $converted );
		}
	}

	/**
	 * Whether this rate should be converted (core method by default, filterable).
	 *
	 * @param WC_Shipping_Rate $rate    Rate under consideration.
	 * @param mixed            $package Shipping package.
	 */
	private function should_convert_rate( WC_Shipping_Rate $rate, $package ): bool {
		$is_core = in_array( $rate->get_method_id(), self::CORE_METHODS, true );

		/**
		 * Whether a shipping rate's cost is authored in the base currency and
		 * should be converted. Defaults to true for core methods and false for
		 * everything else; return false to skip a core rate or true to convert a
		 * third-party rate that is in fact base-priced.
		 *
		 * @since 0.3.0
		 *
		 * @param bool             $convert Whether to convert this rate.
		 * @param WC_Shipping_Rate $rate    Rate under consideration.
		 * @param mixed            $package Shipping package.
		 */
		return (bool) apply_filters( 'umc_convert_shipping_rate', $is_core, $rate, $package );
	}

	/**
	 * Whether the current request + currency should convert shipping.
	 */
	private function should_convert(): bool {
		return $this->context->is_convertible_request() && ! $this->context->is_base_active();
	}
}
