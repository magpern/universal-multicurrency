<?php
/**
 * WooCommerce settings tab for multicurrency configuration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Rates\ExchangeRateStore;
use UMC\Settings;
use WC_Settings_Page;

/**
 * Adds a "Multicurrency" tab under WooCommerce → Settings.
 */
final class SettingsPage extends WC_Settings_Page {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currencies table field renderer.
	 *
	 * @var CurrencyTableField
	 */
	private CurrencyTableField $field;

	/**
	 * Global exchange-rate settings field renderer.
	 *
	 * @var ExchangeRateSettingsField
	 */
	private ExchangeRateSettingsField $exchange_field;

	/**
	 * Builds the settings tab and its custom field renderers.
	 *
	 * @param Settings          $settings Merchant settings store.
	 * @param Currency          $base     Store base currency.
	 * @param ExchangeRateStore $store    Rate persistence boundary.
	 */
	public function __construct( Settings $settings, Currency $base, ExchangeRateStore $store ) {
		$this->id             = 'umc';
		$this->label          = __( 'Multicurrency', 'universal-multicurrency' );
		$this->settings       = $settings;
		$this->field          = new CurrencyTableField( $settings, $base, $store );
		$this->exchange_field = new ExchangeRateSettingsField( $settings, $store );

		parent::__construct();

		add_action( 'woocommerce_admin_field_umc_exchange_rates', array( $this->exchange_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_currencies', array( $this->field, 'render' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * Returns the WooCommerce settings field definitions for this tab.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Multicurrency', 'universal-multicurrency' ),
				'id'   => 'umc_settings_title',
			),
			array(
				'type' => 'umc_exchange_rates',
				'id'   => 'umc_exchange_rates',
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
	 * Saves multicurrency settings from the current POST payload.
	 */
	public function save(): void {
		$raw = isset( $_POST['umc_currencies'] ) ? wp_unslash( $_POST['umc_currencies'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce settings save.

		$currencies = $this->field->parse( is_array( $raw ) ? $raw : array() );
		$globals    = $this->exchange_field->parse_post();
		$merged     = array_merge( $this->settings->get(), $globals, array( 'currencies' => $currencies ) );

		$this->settings->save( $merged );

		/**
		 * Fires after multicurrency settings are persisted.
		 *
		 * @since 0.8.0
		 */
		do_action( 'umc_settings_saved' );
	}

	/**
	 * Renders a one-time admin notice after manual rate-update redirects.
	 */
	public function maybe_render_notice(): void {
		if ( ! isset( $_GET['umc_msg'], $_GET['umc_typ'] ) || ! is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'umc' !== $_GET['tab'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display-only query args after redirect.
		$raw_msg = wp_unslash( (string) $_GET['umc_msg'] );
		$message = sanitize_text_field( rawurldecode( $raw_msg ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type  = sanitize_key( wp_unslash( (string) $_GET['umc_typ'] ) );
		$class = 'warning' === $type ? 'notice-warning' : 'notice-success';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
