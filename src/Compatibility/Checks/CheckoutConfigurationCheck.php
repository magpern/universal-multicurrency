<?php
/**
 * Checkout configuration compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Checkout\CheckoutSettings;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Settings;

/**
 * Reports stable checkout policy configuration for diagnostics.
 */
final class CheckoutConfigurationCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$settings = $inventory->settings()->get();
		$checkout = CheckoutSettings::from_array( $settings['checkout'] ?? array() );

		return array(
			new CompatibilityResult(
				'checkout.mode',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::INFO,
				__( 'Checkout currency mode', 'universal-multicurrency' ),
				$checkout->is_store_mode()
					? __( 'Checkout uses store currency at entry.', 'universal-multicurrency' )
					: __( 'Checkout keeps the shopper-selected currency.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC,
				array(
					'mode'            => $checkout->mode(),
					'show_notice'     => $checkout->show_notice() ? 'yes' : 'no',
					'settings_schema' => (string) ( $settings['schema_version'] ?? Settings::SCHEMA_VERSION ),
				)
			),
		);
	}
}
