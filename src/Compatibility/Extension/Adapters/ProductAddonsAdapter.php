<?php
/**
 * Product Add-Ons compatibility adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

/**
 * Converts base-authored add-on prices through the UMC generic seam (E2).
 *
 * Official WooCommerce.com Product Add-Ons merchant documentation does not
 * publish a developer filter reference for raw option-price conversion. A
 * community-cited filter name exists in third-party sources but is unverified
 * at E2. This adapter therefore registers only the UMC-owned test seam until
 * E3 validates the real extension hook and monetary semantics.
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
