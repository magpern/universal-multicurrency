<?php
/**
 * Product Bundles compatibility adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

/**
 * Converts base-authored bundled item prices without double-converting parent totals.
 *
 * Public Product Bundles hooks:
 * - `woocommerce_bundled_item_price` — individually priced bundled items
 * - Parent bundle totals aggregate child lines; only child base-authored prices convert here.
 *
 * Composite Products deferred (Not evaluated) in M19.
 *
 * @see docs/adr/0024-third-party-extension-compatibility-contract.md
 */
final class BundlesAdapter extends AbstractExtensionAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function extension_id(): string {
		return 'woocommerce_product_bundles';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'woocommerce_bundled_item_price', array( $this, 'convert_bundled_item_price' ), 10, 1 );
		add_filter( 'umc_test_extension_bundled_item_price', array( $this, 'convert_bundled_item_price' ), 10, 1 );
	}

	/**
	 * Converts a base-authored bundled item price once.
	 *
	 * @param mixed $price Bundled item price.
	 * @return mixed
	 */
	public function convert_bundled_item_price( $price ) {
		/**
		 * Whether a bundled item price is base-authored and should convert.
		 *
		 * @since 0.18.0
		 *
		 * @param bool  $convert Default true.
		 * @param mixed $price   Item price.
		 */
		$should = (bool) apply_filters( 'umc_convert_bundled_item_price', true, $price );

		if ( ! $should ) {
			return $price;
		}

		return $this->convert_amount( $price );
	}
}
