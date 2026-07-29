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
	 * Placeholder section renderer callback target.
	 *
	 * @var AdminPageShellViewModelFactory
	 */
	private AdminPageShellViewModelFactory $shell_factory;

	/**
	 * Page shell renderer.
	 *
	 * @var AdminPageShell
	 */
	private AdminPageShell $shell;

	/**
	 * Active section header renderer.
	 *
	 * @var SectionHeader
	 */
	private SectionHeader $section_header;

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
		$this->parser         = new CurrencySettingsParser( $settings, $base );
		$this->exchange_field = new ExchangeRateSettingsField( $settings, $store );
		$this->section_header = new SectionHeader();
		$this->shell          = new AdminPageShell( new SectionNavigation() );
		$this->overview_field = new CurrencyOverviewField(
			new CurrencyViewModelFactory(
				$settings,
				$base,
				$store,
				new WooCommerceCurrencyProvider()
			)
		);

		parent::__construct();

		$this->shell_factory = new AdminPageShellViewModelFactory( $this );

		add_action( 'woocommerce_admin_field_umc_exchange_rates', array( $this->exchange_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_currencies', array( $this->overview_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_placeholder', array( $this, 'render_placeholder_field' ) );
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
	 * Renders the Multicurrency page shell instead of the default subsubsub links.
	 */
	public function output_sections(): void {
		global $current_section;

		$section    = is_string( $current_section ) ? $current_section : '';
		$active     = $this->normalize_section( $section );
		$settings   = $this->get_settings( $section );
		$view_model = $this->shell_factory->build(
			$active,
			$this->capture_conflict_notice( $settings )
		);

		$this->shell->render( $view_model );
	}

	/**
	 * Renders section fields inside the shell content card.
	 */
	public function output(): void {
		global $current_section;

		$section    = is_string( $current_section ) ? $current_section : '';
		$active     = $this->normalize_section( $section );
		$settings   = $this->get_settings( $section );
		$view_model = $this->shell_factory->build(
			$active,
			$this->capture_conflict_notice( $settings )
		);

		$GLOBALS['hide_save_button'] = true;

		$this->shell->open_section_card( $view_model, $this->section_header );

		echo '<table class="form-table umc-form-table">';
		\WC_Admin_Settings::output_fields( $this->content_settings( $settings ) );
		echo '</table>';

		$this->shell->close_section_card();
	}

	/**
	 * Returns whether a section exposes saveable settings fields.
	 *
	 * @param string $section Section slug.
	 */
	public function section_has_saveable_settings( string $section ): bool {
		return in_array( $section, array( self::SECTION_CURRENCIES, self::SECTION_EXCHANGE_RATES ), true );
	}

	/**
	 * Returns the admin URL for one settings section.
	 *
	 * @param string $section Section slug.
	 */
	public function section_url( string $section ): string {
		return admin_url( 'admin.php?page=wc-settings&tab=' . $this->id . '&section=' . rawurlencode( $section ) );
	}

	/**
	 * Renders the placeholder field for the active section.
	 *
	 * @param array<string, mixed> $value Field definition.
	 */
	public function render_placeholder_field( array $value ): void {
		unset( $value );

		$field = new PlaceholderSectionField(
			$this->shell_factory->placeholder_secondary_line( $this->active_section() )
		);
		$field->render();
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
	 * Normalizes the active WooCommerce section slug.
	 *
	 * @param string $section Raw current section value.
	 */
	private function normalize_section( string $section ): string {
		if ( '' === $section || self::SECTION_CURRENCIES === $section ) {
			return self::SECTION_CURRENCIES;
		}

		return $section;
	}

	/**
	 * Captures the compatibility notice markup when present in a section schema.
	 *
	 * @param array<int, array<string, mixed>> $settings Settings field definitions.
	 */
	private function capture_conflict_notice( array $settings ): string {
		foreach ( $settings as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( 'umc_conflict' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			ob_start();
			/**
			 * Renders the Multicurrency compatibility notice field on the settings tab.
			 *
			 * @since 0.8.0
			 */
			do_action( 'woocommerce_admin_field_umc_conflict', $field );

			return (string) ob_get_clean();
		}

		return '';
	}

	/**
	 * Returns only field definitions that belong inside the section card body.
	 *
	 * @param array<int, array<string, mixed>> $settings Settings field definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function content_settings( array $settings ): array {
		$filtered = array();

		foreach ( $settings as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = (string) ( $field['type'] ?? '' );

			if ( in_array( $type, array( 'title', 'sectionend', 'umc_conflict' ), true ) ) {
				continue;
			}

			$filtered[] = $field;
		}

		return $filtered;
	}

	/**
	 * Resolves the active settings section key.
	 */
	private function active_section(): string {
		global $current_section;

		return $this->normalize_section( is_string( $current_section ) ? $current_section : '' );
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
