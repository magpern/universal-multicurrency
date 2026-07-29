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
				$this->description_for_mode( $checkout ),
				CompatibilityDeterminism::DETERMINISTIC,
				array(
					'mode'            => $checkout->mode(),
					'show_notice'     => $checkout->show_notice() ? 'yes' : 'no',
					'settings_schema' => (string) ( $settings['schema_version'] ?? Settings::SCHEMA_VERSION ),
				)
			),
		);
	}

	/**
	 * Returns the diagnostics description for one checkout mode.
	 *
	 * @param CheckoutSettings $checkout Checkout settings.
	 */
	private function description_for_mode( CheckoutSettings $checkout ): string {
		if ( $checkout->is_store_mode() ) {
			return __( 'Checkout uses store currency at entry.', 'universal-multicurrency' );
		}

		return __( 'Checkout keeps the shopper-selected currency.', 'universal-multicurrency' );
	}
}
