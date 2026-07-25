<?php
/**
 * Composition root for the Universal Multicurrency plugin.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC;

use UMC\Admin\SettingsPage;
use UMC\Cart\CartRecalculation;
use UMC\Frontend\Switcher;
use UMC\Integration\CouponConversion;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Integration\ShippingConversion;
use UMC\Rates\ManualRateProvider;

/**
 * Instantiates services once and registers their hooks.
 *
 * Milestone 2 builds the object graph from the store's base currency and the
 * plugin settings, then registers the storefront conversion filters, the
 * switch handler, the switcher shortcode and the admin settings tab. Nothing
 * touches stock, orders, cart totals persistence, or REST/Store API.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether services have been wired.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wires services and registers hooks. Idempotent.
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$settings = new Settings();
		$base     = $this->base_currency();
		$registry = new CurrencyRegistry( $settings, $base );
		$rates    = new ManualRateProvider( $settings, $base->code() );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service  = new PriceConversionService( $context );

		// Storefront: attach conversion filters, handle the switch, register the
		// shortcode. Registered on woocommerce_init so the WC session is
		// available; filters attach unconditionally and gate themselves.
		add_action(
			'woocommerce_init',
			static function () use ( $context, $service ) {
				( new CurrencySwitcher( $context ) )->maybe_switch();
				( new PriceHooks( $service, $context ) )->register();
				( new CurrencyFormatting( $context ) )->register();
				( new Switcher( $context ) )->register();
				( new CartRecalculation( $context ) )->register();
				( new CouponConversion( $service, $context ) )->register();
				( new ShippingConversion( $service, $context ) )->register();
			}
		);

		// Admin settings tab (instantiated lazily, only when WC builds settings).
		add_filter(
			'woocommerce_get_settings_pages',
			static function ( array $pages ) use ( $settings, $base ): array {
				$pages[] = new SettingsPage( $settings, $base );

				return $pages;
			}
		);
	}

	/**
	 * Builds the base currency from WooCommerce's own store settings.
	 *
	 * The base currency lives in `woocommerce_currency`; its formatting comes
	 * from WooCommerce's price options. It is never stored in `umc_settings`.
	 */
	private function base_currency(): Currency {
		$code     = strtoupper( (string) get_option( 'woocommerce_currency', 'USD' ) );
		$decimals = max( 0, min( Currency::MAX_DECIMALS, (int) get_option( 'woocommerce_price_num_decimals', 2 ) ) );
		$position = (string) get_option( 'woocommerce_currency_pos', Currency::DEFAULT_POSITION );

		if ( ! in_array( $position, Currency::POSITIONS, true ) ) {
			$position = Currency::DEFAULT_POSITION;
		}

		return new Currency( $code, $decimals, '', $position, true );
	}
}
