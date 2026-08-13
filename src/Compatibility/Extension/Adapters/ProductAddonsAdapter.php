<?php
/**
 * Product Add-Ons compatibility adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

/**
 * Converts base-authored flat and quantity-based add-on prices.
 *
 * Hook contract from public Product Add-Ons documentation:
 * `woocommerce_product_addons_option_price_raw` supplies base-authored option prices.
 * Percentage add-ons operate on already-converted totals and are not converted here.
 *
 * @see docs/adr/0024-third-party-extension-compatibility-contract.md
 */
final class ProductAddonsAdapter extends AbstractExtensionAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function extension_id(): string {
		return 'woocommerce_product_addons';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_addons_option_price_raw', array( $this, 'convert_addon_price' ), 10, 1 );
		add_filter( 'umc_test_extension_product_addons_price_raw', array( $this, 'convert_addon_price' ), 10, 1 );
	}

	/**
	 * Converts a base-authored add-on option price once.
	 *
	 * @param mixed $price Raw add-on price.
	 * @return mixed
	 */
	public function convert_addon_price( $price ) {
		/**
		 * Whether a Product Add-Ons raw price is base-authored and should convert.
		 *
		 * @since 0.18.0
		 *
		 * @param bool  $convert Default true for flat/quantity add-ons.
		 * @param mixed $price   Raw price value.
		 */
		$should = (bool) apply_filters( 'umc_convert_product_addon_price', true, $price );

		if ( ! $should ) {
			return $price;
		}

		return $this->convert_amount( $price );
	}
}
