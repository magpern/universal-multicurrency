<?php
/**
 * Integration test: base-currency changes are detected live, without a hook.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CacheState;

use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * `woocommerce_currency` never fires `umc_settings_saved` — this proves live
 * hash computation, not a hook, is what detects the change.
 */
final class CacheStateWooCommerceBaseCurrencyTest extends WP_UnitTestCase {

	/**
	 * Snapshot of `woocommerce_currency` taken before mutation, for restoration.
	 *
	 * @var string|null
	 */
	private ?string $original_currency = null;

	public function set_up(): void {
		parent::set_up();
		$this->original_currency = (string) get_option( 'woocommerce_currency' );
	}

	public function tear_down(): void {
		if ( null !== $this->original_currency ) {
			update_option( 'woocommerce_currency', $this->original_currency );
		}

		delete_option( Settings::OPTION );
		delete_option( CacheStateStore::OPTION );

		parent::tear_down();
	}

	private function service(): CacheStateService {
		update_option( 'woocommerce_currency', 'EUR' );
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$registry = new CurrencyRegistry( $settings, $base );

		return new CacheStateService(
			$registry,
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			new CacheStateStore()
		);
	}

	public function test_base_currency_change_in_woocommerce_settings_is_detected_without_a_hook(): void {
		$service = $this->service();
		$hash    = $service->report()->state_hash();
		$service->acknowledge( $hash );

		$this->assertFalse( $service->report()->reconciliation_required() );

		update_option( 'woocommerce_currency', 'SEK' );

		$settings    = new Settings();
		$new_base    = new Currency( 'SEK', 2 );
		$new_service = new CacheStateService(
			new CurrencyRegistry( $settings, $new_base ),
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			new CacheStateStore()
		);

		$this->assertNotSame( $hash, $new_service->report()->state_hash() );
		$this->assertTrue( $new_service->report()->reconciliation_required() );
	}
}
