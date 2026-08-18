<?php
/**
 * Compatibility scanner factory.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

use UMC\CacheState\CacheStateService;
use UMC\Compatibility\Checks\ExtensionCompatibilityCheck;
use UMC\Compatibility\Checks\CacheCheck;
use UMC\Compatibility\Checks\CacheStateCheck;
use UMC\Compatibility\Checks\CheckoutConfigurationCheck;
use UMC\Compatibility\Checks\ConfigurationCheck;
use UMC\Compatibility\Checks\FixedProductPricingCheck;
use UMC\Compatibility\Checks\ConflictCheck;
use UMC\Compatibility\Checks\EnvironmentCheck;
use UMC\Compatibility\Checks\IntegrationCheck;
use UMC\Compatibility\Checks\ThemeCheck;
use UMC\Compatibility\Report\EnvironmentReportBuilder;
use UMC\Currency;
use UMC\Diagnostics\ConflictDetector;
use UMC\Rates\ExchangeRateStore;
use UMC\Settings;

/**
 * Wires the Compatibility scanner for admin use.
 */
final class CompatibilityServices {

	/**
	 * Creates a memoized scanner for the current request.
	 *
	 * @param Settings               $settings    Settings store.
	 * @param ExchangeRateStore      $rate_store  Rate store.
	 * @param Currency               $base        Base currency.
	 * @param ConflictDetector       $detector    Shared conflict detector.
	 * @param CacheStateService|null $cache_state Optional shared cache-state service.
	 */
	public static function scanner(
		Settings $settings,
		ExchangeRateStore $rate_store,
		Currency $base,
		ConflictDetector $detector,
		?CacheStateService $cache_state = null
	): CompatibilityScanner {
		$inventory = CompatibilityInventory::from_runtime( $settings, $rate_store, $base, $detector );
		$builder   = new EnvironmentReportBuilder();
		$checks    = array(
			new ConflictCheck(),
			new ConfigurationCheck(),
			new FixedProductPricingCheck(),
			new CheckoutConfigurationCheck(),
			new IntegrationCheck(),
			new ExtensionCompatibilityCheck(),
			new ThemeCheck(),
			new CacheCheck(),
			new EnvironmentCheck(),
		);

		if ( null !== $cache_state ) {
			$checks[] = new CacheStateCheck( $cache_state );
		}

		return new CompatibilityScanner(
			$inventory,
			$checks,
			static function ( CompatibilityScan $scan ) use ( $builder ): string {
				return $builder->build( $scan );
			}
		);
	}
}
