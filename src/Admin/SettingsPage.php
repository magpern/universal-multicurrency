<?php
/**
 * WooCommerce settings tab for multicurrency configuration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\ViewModel\CurrencyViewModelFactory;
use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\Compatibility\CompatibilityServices;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\CurrencyContext;
use UMC\CurrencyResolver;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Integration\PriceConversionService;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceRepository;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ManualRateProvider;
use UMC\Rates\RateHealthService;
use UMC\Reporting\LineItemProvenanceAggregator;
use UMC\Reporting\OrderReportingRepository;
use UMC\Reporting\RefundValueResolver;
use UMC\Reporting\ReportingCache;
use UMC\Reporting\ReportingCacheInvalidator;
use UMC\Reporting\ReportingCsvRenderer;
use UMC\Reporting\ReportingOriginClassifier;
use UMC\Reporting\ReportingService;
use UMC\Reporting\TransactionCurrencyResolver;
use UMC\Order\OrderSnapshotReader;
use UMC\Settings;
use WC_Settings_Page;

/**
 * Adds a "Multicurrency" tab under WooCommerce → Settings.
 */
final class SettingsPage extends WC_Settings_Page {

	public const SECTION_CURRENCIES         = 'currencies';
	public const SECTION_EXCHANGE_RATES     = 'exchange_rates';
	public const SECTION_GEO_DETECTION      = 'geo_detection';
	public const SECTION_DISPLAY            = 'display';
	public const SECTION_CHECKOUT           = 'checkout';
	public const SECTION_DECISION_INSPECTOR = 'decision_inspector';
	public const SECTION_COMPATIBILITY      = 'compatibility';
	public const SECTION_REPORTING          = 'reporting';
	public const SECTION_FIXED_PRICING      = 'fixed_pricing';
	public const SECTION_ADVANCED           = 'advanced';

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
	 * Display switcher settings field renderer.
	 *
	 * @var DisplaySettingsField
	 */
	private DisplaySettingsField $display_field;

	/**
	 * Checkout policy settings field renderer.
	 *
	 * @var CheckoutSettingsField
	 */
	private CheckoutSettingsField $checkout_field;

	/**
	 * Compatibility diagnostics field renderer.
	 *
	 * @var CompatibilitySettingsField
	 */
	private CompatibilitySettingsField $compatibility_field;

	/**
	 * Decision Inspector field renderer.
	 *
	 * @var DecisionInspectorSettingsField
	 */
	private DecisionInspectorSettingsField $decision_inspector_field;

	/**
	 * Geo Detection settings field renderer.
	 *
	 * @var GeoDetectionSettingsField
	 */
	private GeoDetectionSettingsField $geo_field;

	/**
	 * Reporting field renderer.
	 *
	 * @var ReportingSettingsField
	 */
	private ReportingSettingsField $reporting_field;

	/**
	 * Fixed Pricing catalog operations field renderer.
	 *
	 * @var FixedPricingSettingsField
	 */
	private FixedPricingSettingsField $fixed_pricing_field;

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
	 * @param Settings               $settings    Merchant settings store.
	 * @param Currency               $base        Store base currency.
	 * @param ExchangeRateStore      $store       Rate persistence boundary.
	 * @param RateHealthService|null $health      Optional shared rate health service.
	 * @param CacheStateService|null $cache_state Optional shared cache-state service.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		ExchangeRateStore $store,
		?RateHealthService $health = null,
		?CacheStateService $cache_state = null
	) {
		$this->id                       = 'umc';
		$this->label                    = __( 'Multicurrency', 'universal-multicurrency' );
		$this->settings                 = $settings;
		$this->parser                   = new CurrencySettingsParser( $settings, $base );
		$this->exchange_field           = new ExchangeRateSettingsField( $settings, $store, $health );
		$registry                       = new CurrencyRegistry( $settings, $base );
		$cache_state                    = $cache_state ?? new CacheStateService(
			$registry,
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			new CacheStateStore()
		);
		$rates                          = new ManualRateProvider( $settings, $base->code() );
		$context                        = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$display_repository             = new SwitcherSettingsRepository( $settings );
		$this->display_field            = new DisplaySettingsField(
			$settings,
			new SwitcherViewModelFactory( $context, new WooCommerceCurrencyProvider(), $display_repository ),
			new SwitcherRenderer(),
			$display_repository
		);
		$this->checkout_field           = new CheckoutSettingsField( $settings, $base );
		$this->geo_field                = new GeoDetectionSettingsField( $settings, $base, $registry );
		$this->decision_inspector_field = new DecisionInspectorSettingsField( $settings, $base );
		$reader                         = new OrderSnapshotReader();
		$repository                     = new OrderReportingRepository(
			$reader,
			new TransactionCurrencyResolver(),
			new RefundValueResolver(),
			new LineItemProvenanceAggregator(),
			new ReportingOriginClassifier()
		);
		$reporting_cache                = new ReportingCache( new ReportingService( $repository ) );
		$this->reporting_field          = new ReportingSettingsField( $reporting_cache, $registry );
		( new ReportingCacheInvalidator( $reporting_cache ) )->register();
		( new ReportingExportController( $reporting_cache, new ReportingCsvRenderer() ) )->register();

		$fixed_price_repository    = new FixedPriceRepository( $base->code() );
		$fixed_price_coverage      = new FixedPriceCoverageReport( $fixed_price_repository );
		$fixed_price_query         = new FixedPriceCatalogQuery( $fixed_price_coverage );
		$fixed_price_converter     = new PriceConversionService( $context );
		$fixed_price_operations    = new FixedPriceCatalogOperationsService(
			$fixed_price_repository,
			$fixed_price_coverage,
			$rates,
			$fixed_price_converter,
			$registry
		);
		$this->fixed_pricing_field = new FixedPricingSettingsField( $fixed_price_query, $registry );
		( new FixedPricingOperationController( $fixed_price_operations, $fixed_price_query ) )->register();
		$conflict_detector         = new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);
		$this->compatibility_field = new CompatibilitySettingsField(
			CompatibilityServices::scanner( $settings, $store, $base, $conflict_detector, $cache_state )
		);
		$this->section_header      = new SectionHeader();
		$this->shell               = new AdminPageShell( new SectionNavigation() );
		$this->overview_field      = new CurrencyOverviewField(
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
		add_action( 'woocommerce_admin_field_umc_display', array( $this->display_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_checkout', array( $this->checkout_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_geo_detection', array( $this->geo_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_decision_inspector', array( $this->decision_inspector_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_reporting', array( $this->reporting_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_fixed_pricing', array( $this->fixed_pricing_field, 'render' ) );
		add_action( 'woocommerce_admin_field_umc_compatibility', array( $this->compatibility_field, 'render' ) );
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
			self::SECTION_CURRENCIES         => __( 'Currencies', 'universal-multicurrency' ),
			self::SECTION_EXCHANGE_RATES     => __( 'Exchange Rates', 'universal-multicurrency' ),
			self::SECTION_GEO_DETECTION      => __( 'Visitor Location', 'universal-multicurrency' ),
			self::SECTION_DISPLAY            => __( 'Display', 'universal-multicurrency' ),
			self::SECTION_CHECKOUT           => __( 'Checkout', 'universal-multicurrency' ),
			self::SECTION_DECISION_INSPECTOR => __( 'Decision Inspector', 'universal-multicurrency' ),
			self::SECTION_REPORTING          => __( 'Reporting', 'universal-multicurrency' ),
			self::SECTION_FIXED_PRICING      => __( 'Fixed Pricing', 'universal-multicurrency' ),
			self::SECTION_COMPATIBILITY      => __( 'Compatibility', 'universal-multicurrency' ),
			self::SECTION_ADVANCED           => __( 'Advanced', 'universal-multicurrency' ),
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

		printf(
			'<input type="hidden" name="section" value="%s" />',
			esc_attr( $active )
		);

		echo '<table class="form-table umc-form-table">';
		\WC_Admin_Settings::output_fields( $this->content_settings( $settings ) );
		echo '</table>';

		if ( $this->section_has_saveable_settings( $active ) && ! $this->section_uses_sticky_save( $active ) && self::SECTION_DISPLAY !== $active ) {
			$this->render_section_save_actions();
		}

		if ( $this->section_uses_sticky_save( $active ) && self::SECTION_GEO_DETECTION !== $active ) {
			$this->render_sticky_save_actions( $active );
		}

		if ( self::SECTION_DISPLAY === $active ) {
			$this->render_display_sticky_actions();
		}

		$this->shell->close_section_card();
	}

	/**
	 * Returns whether a section exposes saveable settings fields.
	 *
	 * @param string $section Section slug.
	 */
	public function section_has_saveable_settings( string $section ): bool {
		if ( self::SECTION_GEO_DETECTION === $section ) {
			return GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::active_panel() );
		}

		return in_array( $section, array( self::SECTION_CURRENCIES, self::SECTION_EXCHANGE_RATES, self::SECTION_DISPLAY, self::SECTION_CHECKOUT ), true );
	}

	/**
	 * Returns whether a section exposes the header save button.
	 *
	 * @param string $section Section slug.
	 */
	public function section_has_header_save( string $section ): bool {
		if ( self::SECTION_GEO_DETECTION === $section || self::SECTION_CHECKOUT === $section ) {
			return false;
		}

		return in_array(
			$section,
			array( self::SECTION_CURRENCIES, self::SECTION_EXCHANGE_RATES ),
			true
		);
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
	 * Returns field definitions for the Geo Detection section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_geo_detection_section() {
		return $this->geo_detection_settings();
	}

	/**
	 * Returns field definitions for the Display section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_display_section() {
		return $this->display_settings();
	}

	/**
	 * Returns field definitions for the Checkout section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_checkout_section() {
		return $this->checkout_settings();
	}

	/**
	 * Returns field definitions for the Decision Inspector section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_decision_inspector_section() {
		return $this->decision_inspector_settings();
	}

	/**
	 * Returns field definitions for the Reporting section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_reporting_section() {
		return $this->reporting_settings();
	}

	/**
	 * Returns field definitions for the Fixed Pricing section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_fixed_pricing_section() {
		return $this->fixed_pricing_settings();
	}

	/**
	 * Returns field definitions for the Compatibility section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_compatibility_section() {
		return $this->compatibility_settings();
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

		if ( self::SECTION_DISPLAY === $section ) {
			$parsed = $this->display_field->parse_post();

			if ( null === $parsed ) {
				$message = __( 'When the switcher is enabled, at least one device visibility option must be selected.', 'universal-multicurrency' );

				if ( headers_sent() ) {
					\WC_Admin_Settings::add_error( $message );
					return;
				}

				$this->redirect_display_notice( $message, 'error' );
				return;
			}

			$merged = array_merge( $this->settings->get(), array( 'display' => $parsed['display'] ) );
			$this->settings->save( $merged );
			$this->fire_saved_hook();

			if ( ! empty( $parsed['show_custom_css_rejected_notice'] ) ) {
				$this->redirect_display_notice(
					__( 'Custom CSS was not saved because it contained disallowed constructs (for example @import, url(), escape sequences with backslashes, expression(), behavior:, -moz-binding, or angle brackets). Your previous Custom CSS was kept. Other Display settings were saved.', 'universal-multicurrency' ),
					'error'
				);
				return;
			}

			if ( ! empty( $parsed['show_coercion_notice'] ) ) {
				$this->redirect_display_notice(
					__( 'Horizontal list is only available with manual placement. Style was saved as Dropdown.', 'universal-multicurrency' ),
					'warning'
				);
			}

			return;
		}

		if ( self::SECTION_GEO_DETECTION === $section ) {
			$parsed = $this->geo_field->parse_post();

			if ( null === $parsed ) {
				return;
			}

			$merged = array_merge( $this->settings->get(), array( 'geo' => $parsed['geo'] ) );
			$this->settings->save( $merged );
			$this->fire_saved_hook();

			foreach ( $parsed['warnings'] as $warning ) {
				\WC_Admin_Settings::add_message( $warning );
			}

			return;
		}

		if ( self::SECTION_CHECKOUT === $section ) {
			$merged = array_merge(
				$this->settings->get(),
				array( 'checkout' => $this->checkout_field->parse_post() )
			);
			$this->settings->save( $merged );
			$this->fire_saved_hook();
			return;
		}

		if ( self::SECTION_CURRENCIES !== $section ) {
			return;
		}

		if ( ! isset( $_POST['umc_currencies'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce settings save.
		$raw = wp_unslash( $_POST['umc_currencies'] );

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
		$class = match ( $type ) {
			'error'   => 'notice-error',
			'warning' => 'notice-warning',
			default   => 'notice-success',
		};

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
	private function display_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Display', 'universal-multicurrency' ),
				'id'   => 'umc_display_title',
			),
			array(
				'type' => 'umc_display',
				'id'   => 'umc_display',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_display_end',
			),
		);
	}

	/**
	 * Returns field definitions for the Geo Detection section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function geo_detection_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Visitor Location', 'universal-multicurrency' ),
				'id'   => 'umc_geo_title',
			),
			array(
				'type' => 'umc_geo_detection',
				'id'   => 'umc_geo_detection',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_geo_end',
			),
		);
	}

	/**
	 * Returns field definitions for the Checkout section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function checkout_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Checkout', 'universal-multicurrency' ),
				'id'   => 'umc_checkout_title',
			),
			array(
				'type' => 'umc_checkout',
				'id'   => 'umc_checkout',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_checkout_end',
			),
		);
	}

	/**
	 * Returns field definitions for the Decision Inspector section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function decision_inspector_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Decision Inspector', 'universal-multicurrency' ),
				'id'   => 'umc_decision_inspector_title',
			),
			array(
				'type' => 'umc_decision_inspector',
				'id'   => 'umc_decision_inspector',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_decision_inspector_end',
			),
		);
	}

	/**
	 * Returns field definitions for the Reporting section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function reporting_settings(): array {
		return array(
			array(
				'type' => 'umc_conflict',
				'id'   => 'umc_conflict_notice',
			),
			array(
				'type' => 'title',
				'name' => __( 'Reporting', 'universal-multicurrency' ),
				'id'   => 'umc_reporting_title',
			),
			array(
				'type' => 'umc_reporting',
				'id'   => 'umc_reporting',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_reporting_end',
			),
		);
	}

	/**
	 * Returns field definitions for the Fixed Pricing section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fixed_pricing_settings(): array {
		return array(
			array(
				'type' => 'title',
				'name' => __( 'Fixed Pricing', 'universal-multicurrency' ),
				'id'   => 'umc_fixed_pricing_title',
			),
			array(
				'type' => 'umc_fixed_pricing',
				'id'   => 'umc_fixed_pricing',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_fixed_pricing_end',
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
	 * Returns field definitions for the Compatibility section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function compatibility_settings(): array {
		return array(
			array(
				'type' => 'title',
				'name' => __( 'Compatibility', 'universal-multicurrency' ),
				'id'   => 'umc_compatibility_title',
			),
			array(
				'type' => 'umc_compatibility',
				'id'   => 'umc_compatibility',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'umc_compatibility_end',
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

		$section = is_string( $current_section ) ? $current_section : '';

		if ( '' === $section && isset( $_REQUEST['section'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Section routing only; save nonce verified by WooCommerce.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Section routing only; save nonce verified by WooCommerce.
			$section = sanitize_title( wp_unslash( (string) $_REQUEST['section'] ) );
		}

		return $this->normalize_section( $section );
	}

	/**
	 * Renders the in-card save actions for saveable sections.
	 */
	private function render_section_save_actions(): void {
		?>
		<p class="submit umc-section-card__submit">
			<button type="submit" name="save" value="<?php echo esc_attr__( 'Save changes', 'universal-multicurrency' ); ?>" class="button button-primary">
				<?php esc_html_e( 'Save changes', 'universal-multicurrency' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Renders the sticky Display save bar inside the main settings form.
	 */
	private function render_display_sticky_actions(): void {
		?>
		<div class="umc-display-actions submit" data-umc-display-actions data-umc-sticky-save data-umc-sticky-scope="display">
			<span class="umc-display-actions__status" data-umc-unsaved-indicator hidden><?php esc_html_e( 'Unsaved changes', 'universal-multicurrency' ); ?></span>
			<button type="submit" name="save" value="<?php echo esc_attr__( 'Save changes', 'universal-multicurrency' ); ?>" class="button button-primary umc-display-actions__save">
				<?php esc_html_e( 'Save changes', 'universal-multicurrency' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Renders the shared sticky save bar for saveable sections.
	 *
	 * @param string $section Active section slug.
	 */
	private function render_sticky_save_actions( string $section ): void {
		$renderer = new AdminComponentRenderer();
		echo $renderer->sticky_save_bar( $section ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in renderer.
	}

	/**
	 * Returns whether a section uses the shared sticky save bar outside geo hub markup.
	 *
	 * @param string $section Section slug.
	 */
	private function section_uses_sticky_save( string $section ): bool {
		if ( self::SECTION_CHECKOUT === $section ) {
			return true;
		}

		if ( self::SECTION_GEO_DETECTION === $section ) {
			return GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::active_panel() );
		}

		return false;
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

	/**
	 * Redirects back to the Display section with a one-time notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type: success, warning, error.
	 *
	 * @codeCoverageIgnore
	 */
	private function redirect_display_notice( string $message, string $type ): void {
		if ( headers_sent() ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => $this->id,
					'section' => self::SECTION_DISPLAY,
					'umc_msg' => rawurlencode( $message ),
					'umc_typ' => sanitize_key( $type ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
