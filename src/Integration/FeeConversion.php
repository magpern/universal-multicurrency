<?php
/**
 * Opt-in fee conversion for base-authored cart fees.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Converts cart fees only when explicitly opted in via `umc_convert_fee`.
 *
 * Default pass-through preserves M18 fee boundary. Third-party extensions
 * declare base-authored fees by returning true from the filter.
 */
final class FeeConversion {

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
	 * Registers fee conversion after fees are added.
	 */
	public function register(): void {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'convert_opt_in_fees' ), 99 );
	}

	/**
	 * Converts opted-in fees on the cart fee objects.
	 */
	public function convert_opt_in_fees(): void {
		if ( ! $this->should_convert() || ! function_exists( 'WC' ) || null === WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_fees() as $fee ) {
			/**
			 * Opt-in fee conversion. Default false — fees pass through unchanged.
			 *
			 * @since 0.3.0
			 *
			 * @param bool  $convert Whether to convert this fee (base-authored).
			 * @param object $fee    Fee object from the cart.
			 */
			$should = (bool) apply_filters( 'umc_convert_fee', false, $fee );

			if ( ! $should ) {
				continue;
			}

			$converted = $this->service->convert( $fee->amount );
			if ( is_numeric( $converted ) ) {
				$fee->amount = (float) $converted;
			}
		}
	}

	/**
	 * Whether the current request should convert opted-in fees.
	 */
	private function should_convert(): bool {
		return $this->context->is_convertible_request() && ! $this->context->is_base_active();
	}
}
