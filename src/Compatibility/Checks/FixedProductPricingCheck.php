<?php
/**
 * Fixed product pricing configuration diagnostic check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Pricing\FixedPriceDocument;

/**
 * Reports fixed-pricing feature posture without catalog scans.
 */
final class FixedProductPricingCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$base_code     = $inventory->base()->code();
		$foreign_count = 0;

		foreach ( $inventory->settings()->get_currencies() as $code => $config ) {
			if ( strtoupper( (string) $code ) === strtoupper( $base_code ) ) {
				continue;
			}

			if ( ! empty( $config['enabled'] ) ) {
				++$foreign_count;
			}
		}

		$details = array(
			__( 'Base currency prices remain WooCommerce-native; _umc_fixed_prices never overrides the store base currency.', 'universal-multicurrency' ),
			__( 'Blank foreign-currency fields use automatic FX conversion.', 'universal-multicurrency' ),
			__( 'Disabled currencies retain authored fixed prices but ignore them at runtime.', 'universal-multicurrency' ),
			__( 'WooCommerce sale schedules gate fixed sale amounts.', 'universal-multicurrency' ),
		);

		if ( 0 === $foreign_count ) {
			return array(
				new CompatibilityResult(
					'pricing.fixed_product',
					CompatibilityCategory::CONFIGURATION,
					CompatibilitySeverity::INFO,
					__( 'Fixed product pricing', 'universal-multicurrency' ),
					__( 'No enabled foreign currencies are configured; fixed pricing is inactive until a non-base currency is enabled.', 'universal-multicurrency' ),
					CompatibilityDeterminism::DETERMINISTIC,
					array(
						'feature'          => 'fixed_product_pricing',
						'fixed_price_meta' => FixedPriceDocument::META_KEY,
						'enabled_foreign'  => '0',
						'base_currency'    => $base_code,
						'catalog_scan'     => 'none',
					),
					array(),
					$details
				),
			);
		}

		return array(
			new CompatibilityResult(
				'pricing.fixed_product',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::INFO,
				__( 'Fixed product pricing', 'universal-multicurrency' ),
				sprintf(
					/* translators: 1: number of enabled foreign currencies, 2: base currency code */
					_n(
						'%1$d enabled foreign currency can use optional fixed prices; base %2$s remains WooCommerce-native.',
						'%1$d enabled foreign currencies can use optional fixed prices; base %2$s remains WooCommerce-native.',
						$foreign_count,
						'universal-multicurrency'
					),
					$foreign_count,
					$base_code
				),
				CompatibilityDeterminism::DETERMINISTIC,
				array(
					'feature'          => 'fixed_product_pricing',
					'fixed_price_meta' => FixedPriceDocument::META_KEY,
					'enabled_foreign'  => (string) $foreign_count,
					'base_currency'    => $base_code,
					'catalog_scan'     => 'none',
				),
				array(),
				$details
			),
		);
	}
}
