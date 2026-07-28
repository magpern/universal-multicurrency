<?php
/**
 * WooCommerce settings tab for multicurrency configuration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\CurrencyViewModelFactory;
use UMC\Currency;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Rates\ExchangeRateStore;
use UMC\Settings;
use WC_Settings_Page;

/**
 * Adds a "Multicurrency" tab under WooCommerce → Settings.
 */
final class SettingsPage extends WC_Settings_Page {

	public const SECTION_CURRENCIES     = 'currencies';
	public const SECTION_EXCHANGE_RATES = 'exchange_rates';
	public const SECTION_DISPLAY        = 'display';
	public const SECTION_CHECKOUT       = 'checkout';
	public const SECTION_COMPATIBILITY  = 'compatibility';
	public const SECTION_ADVANCED       = 'advanced';

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currencies overview field renderer.
	 *
	 * @var CurrencyOverviewField
	 */
	private CurrencyOverviewField $overview_field;

	/**
	 * Shared currency POST parser.
	 *
	 * @var CurrencySettingsParser
	 */
	private CurrencySettingsParser $parser;

	/**
	 * Global exchange-rate settings field renderer.
	 *
	 * @var ExchangeRateSettingsField
	 */
	private ExchangeRateSettingsField $exchange_field;

	/**
	 * Placeholder section renderer.
	 *
	 * @var PlaceholderSectionField
	 */
	private PlaceholderSectionField $placeholder_field;

	/**
	 * Builds the settings tab and its custom field renderers.
	 *
	 * @param Settings          $settings Merchant settings store.
	 * @param Currency          $base     Store base currency.
	 * @param ExchangeRateStore $store    Rate persistence boundary.
	 */
	public function __construct( Settings $settings, Currency $base, ExchangeRateStore $store ) {
		$this->id                = 'umc';
		$this->label             = __( 'Multicurrency', 'universal-multicurrency' );
		$this->settings          = $settings;
		$this->parser            = new CurrencySettingsParser( $settings, $base );
		$this->exchange_field    = new ExchangeRateSettingsField( $settings, $store );
		$this->placeholder_field = new PlaceholderSectionField();
		$this->overview_field    = new CurrencyOverviewField(
			new CurrencyViewModelFactory(
				$settings,
				$base,
				$store,
				new WooCommerceCurrencyProvider()
			)
		);

		parent::__construct();

		add_action( 'woocommerce_admin_field_umc_exchange_rates', array( $this->exchange_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_currencies', array( $this->overview_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_placeholder', array( $this->placeholder_field, 'render' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * Returns the Multicurrency settings sections.
	 *
	 * @return array<string, string>
	 */
	public function get_sections(): array {
		return array(
			self::SECTION_CURRENCIES     => __( 'Currencies', 'universal-multicurrency' ),
			self::SECTION_EXCHANGE_RATES => __( 'Exchange Rates', 'universal-multicurrency' ),
			self::SECTION_DISPLAY        => __( 'Display', 'universal-multicurrency' ),
			self::SECTION_CHECKOUT       => __( 'Checkout', 'universal-multicurrency' ),
			self::SECTION_COMPATIBILITY  => __( 'Compatibility', 'universal-multicurrency' ),
			self::SECTION_ADVANCED       => __( 'Advanced', 'universal-multicurrency' ),
		);
	}

	/**
	 * Returns field definitions for the default (Currencies) section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section() {
		return $this->currency_settings();
	}

	/**
	 * Returns field definitions for the Currencies section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_currencies_section() {
		return $this->currency_settings();
	}

	/**
	 * Returns field definitions for the Exchange Rates section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_exchange_rates_section() {
		return $this->exchange_rate_settings();
	}

	/**
	 * Returns field definitions for the Display section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_display_section() {
		return $this->placeholder_settings( self::SECTION_DISPLAY );
	}

	/**
	 * Returns field definitions for the Checkout section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_checkout_section() {
		return $this->placeholder_settings( self::SECTION_CHECKOUT );
	}

	/**
	 * Returns field definitions for the Compatibility section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_compatibility_section() {
		return $this->placeholder_settings( self::SECTION_COMPATIBILITY );
	}

	/**
	 * Returns field definitions for the Advanced section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_advanced_section() {
		return $this->placeholder_settings( self::SECTION_ADVANCED );
	}

	/**
	 * Saves multicurrency settings from the current POST payload.
	 */
	public function save(): void {
		$section = $this->active_section();

		if ( self::SECTION_EXCHANGE_RATES === $section ) {
			$merged = array_merge( $this->settings->get(), $this->exchange_field->parse_post() );
			$this->settings->save( $merged );
			$this->fire_saved_hook();
			return;
		}

		if ( self::SECTION_CURRENCIES !== $section ) {
			return;
		}

		$raw = isset( $_POST['umc_currencies'] ) ? wp_unslash( $_POST['umc_currencies'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce settings save.

		$currencies = $this->parser->parse( is_array( $raw ) ? $raw : array() );
		$merged     = array_merge( $this->settings->get(), array( 'currencies' => $currencies ) );

		$this->settings->save( $merged );
		$this->fire_saved_hook();
	}

	/**
	 * Renders a one-time admin notice after admin-post redirects.
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

	/**
	 * Returns field definitions for the currencies section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function currency_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Currencies', 'universal-multicurrency' ),
				'id'   => 'umc_currencies_title',
			),
			array(
				'type' => 'umc_currencies',
				'id'   => 'umc_currencies',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_currencies_end',
			),
		);
	}

	/**
	 * Returns field definitions for the exchange rates section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function exchange_rate_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Exchange Rates', 'universal-multicurrency' ),
				'id'   => 'umc_exchange_rates_title',
			),
			array(
				'type' => 'umc_exchange_rates',
				'id'   => 'umc_exchange_rates',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_exchange_rates_end',
			),
		);
	}

	/**
	 * Returns field definitions for placeholder sections.
	 *
	 * @param string $section Section key.
	 * @return array<int, array<string, mixed>>
	 */
	private function placeholder_settings( string $section ): array {
		return array(
			array(
				'type' => 'title',
				'name' => $this->section_title( $section ),
				'id'   => 'umc_placeholder_title',
			),
			array(
				'type' => 'umc_placeholder',
				'id'   => 'umc_placeholder',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_placeholder_end',
			),
		);
	}

	/**
	 * Resolves the active settings section key.
	 */
	private function active_section(): string {
		global $current_section;

		$section = is_string( $current_section ) ? $current_section : '';

		if ( '' === $section ) {
			return self::SECTION_CURRENCIES;
		}

		return $section;
	}

	/**
	 * Returns the localized title for one section key.
	 *
	 * @param string $section Section key.
	 */
	private function section_title( string $section ): string {
		$sections = $this->get_sections();

		return $sections[ $section ] ?? __( 'Multicurrency', 'universal-multicurrency' );
	}

	/**
	 * Fires the settings saved action hook.
	 */
	private function fire_saved_hook(): void {
		/**
		 * Fires after multicurrency settings are persisted.
		 *
		 * @since 0.8.0
		 */
		do_action( 'umc_settings_saved' );
	}
}
