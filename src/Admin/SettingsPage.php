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

	private Settings $settings;

	private CurrencyTableField $field;

	private ExchangeRateSettingsField $exchange_field;

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

	public function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['umc_currencies'] ) ? wp_unslash( $_POST['umc_currencies'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$currencies = $this->field->parse( is_array( $raw ) ? $raw : array() );
		$globals    = $this->exchange_field->parse_post();
		$merged     = array_merge( $this->settings->get(), $globals, array( 'currencies' => $currencies ) );

		$this->settings->save( $merged );

		/**
		 * Fires after multicurrency settings are persisted.
		 */
		do_action( 'umc_settings_saved' );
	}

	public function maybe_render_notice(): void {
		if ( ! isset( $_GET['umc_msg'], $_GET['umc_typ'] ) || ! is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'umc' !== $_GET['tab'] ) {
			return;
		}

		$message = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['umc_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type    = sanitize_key( wp_unslash( (string) $_GET['umc_typ'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class   = 'warning' === $type ? 'notice-warning' : 'notice-success';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
