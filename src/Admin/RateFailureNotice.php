<?php
/**
 * Admin notice when automatic rate fetches fail repeatedly.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Rates\ExchangeRateStore;
use UMC\Settings;

/**
 * Surfaces consecutive provider failures on the dashboard.
 */
final class RateFailureNotice {

	private const THRESHOLD = 3;

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Rate persistence boundary.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $store;

	/**
	 * Binds the notice to settings and the rate store.
	 *
	 * @param Settings          $settings Merchant settings store.
	 * @param ExchangeRateStore $store    Rate persistence boundary.
	 */
	public function __construct( Settings $settings, ExchangeRateStore $store ) {
		$this->settings = $settings;
		$this->store    = $store;
	}

	/**
	 * Registers the admin notice hook.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Renders the warning notice when failures exceed the threshold.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			return;
		}

		if ( Settings::RATE_MODE_AUTOMATIC !== ( $this->settings->get()['rate_mode'] ?? Settings::RATE_MODE_MANUAL ) ) {
			return;
		}

		$failed_codes = array();

		foreach ( array_keys( $this->settings->get_currencies() ) as $code ) {
			if ( Settings::RATE_MODE_AUTOMATIC !== $this->settings->get_effective_rate_mode( $code ) ) {
				continue;
			}

			$status = $this->store->get_operational_status( $code );

			if ( $status->consecutive_failures() >= self::THRESHOLD ) {
				$failed_codes[] = $code;
			}
		}

		if ( array() === $failed_codes ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated currency codes */
					__( 'Automatic exchange-rate updates failed repeatedly for: %s. Last known rates are still in use.', 'universal-multicurrency' ),
					implode( ', ', $failed_codes )
				)
			)
		);
	}
}
