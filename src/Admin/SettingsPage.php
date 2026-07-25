<?php
/**
 * WooCommerce settings tab for multicurrency configuration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Admin;

use UMC\Currency;
use UMC\Settings;
use WC_Settings_Page;

/**
 * Adds a "Multicurrency" tab under WooCommerce → Settings.
 *
 * Renders the currencies table via {@see CurrencyTableField} and persists it
 * through {@see Settings::save()} (which runs the Milestone 1 sanitizer). The
 * save handler runs inside WooCommerce's settings-save flow, which has already
 * verified the settings nonce. A neutral label is used because the product name
 * is provisional (see ADR-0003).
 */
final class SettingsPage extends WC_Settings_Page {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currencies table field.
	 *
	 * @var CurrencyTableField
	 */
	private CurrencyTableField $field;

	/**
	 * Builds the settings page and wires WooCommerce's settings hooks.
	 *
	 * @param Settings $settings Settings store.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->id       = 'umc';
		$this->label    = __( 'Multicurrency', 'universal-multicurrency' );
		$this->settings = $settings;
		$this->field    = new CurrencyTableField( $settings, $base );

		parent::__construct();

		add_action( 'woocommerce_admin_field_umc_currencies', array( $this->field, 'render' ) );
	}

	/**
	 * The settings fields for this tab (a titled section wrapping the table).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings(): array {
		return array(
			array(
				'type' => 'title',
				'name' => __( 'Multicurrency', 'universal-multicurrency' ),
				'id'   => 'umc_settings_title',
			),
			array(
				'type' => 'umc_currencies',
				'id'   => 'umc_currencies',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_settings_end',
			),
		);
	}

	/**
	 * Persists the posted currencies through the sanitizing settings store.
	 *
	 * WooCommerce verifies the `woocommerce-settings` nonce before firing the
	 * save hook this method is bound to.
	 */
	public function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the woocommerce-settings nonce before woocommerce_settings_save_{tab}.
		$raw = isset( $_POST['umc_currencies'] ) ? wp_unslash( $_POST['umc_currencies'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by WooCommerce; each field is sanitized in CurrencyTableField::parse() and Settings::sanitize().

		$currencies = $this->field->parse( is_array( $raw ) ? $raw : array() );

		$this->settings->save( array( 'currencies' => $currencies ) );
	}
}
