<?php
/**
 * Checkout geo re-evaluation policy.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Pure rules for when geo may re-evaluate during checkout.
 */
final class CheckoutGeoPolicy {

	/**
	 * Whether geo currency may change at the current checkout stage.
	 *
	 * @param GeoDetectionSettings $settings      Geo settings.
	 * @param bool                 $checkout_lock Whether checkout currency is locked.
	 * @param string               $change_source billing|shipping|'' for initial.
	 */
	public function allows_revaluation( GeoDetectionSettings $settings, bool $checkout_lock, string $change_source = '' ): bool {
		if ( $checkout_lock && $settings->lock_on_entry() ) {
			return false;
		}

		if ( '' === $change_source ) {
			return true;
		}

		if ( 'billing' === $change_source ) {
			return $settings->reevaluate_on_billing_change();
		}

		if ( 'shipping' === $change_source ) {
			return $settings->reevaluate_on_shipping_change();
		}

		return false;
	}
}
