<?php
/**
 * Checkout settings read model.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

use UMC\Settings;

/**
 * Reads checkout policy settings from the plugin settings store.
 */
final class CheckoutSettingsRepository {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Binds the repository to the settings store.
	 *
	 * @param Settings $settings Merchant settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns normalized checkout settings for the current store configuration.
	 */
	public function get(): CheckoutSettings {
		$raw = $this->settings->get()['checkout'] ?? array();

		return CheckoutSettings::from_array( is_array( $raw ) ? $raw : array() );
	}
}
