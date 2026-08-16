<?php
/**
 * Composition root for the Universal Multicurrency plugin.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Admin\AdminAssets;
use UMC\Admin\CurrencyActionController;
use UMC\Admin\Geo\GeoLegacyPanelRedirect;
use UMC\Admin\GeoDetectionSimulationController;
use UMC\Admin\GeoRecommendedRulesController;
use UMC\Admin\DecisionInspectorController;
use UMC\Admin\GeoSandboxController;
use UMC\Admin\OrderCurrencyMetaBox;
use UMC\Admin\FixedPriceCoverageColumn;
use UMC\Admin\ProductFixedPricesPanel;
use UMC\Admin\PluginActionLinks;
use UMC\Admin\RateFailureNotice;
use UMC\Admin\RateUpdateController;
use UMC\Admin\SettingsPage;
use UMC\CLI\PricesCommand;
use UMC\CLI\RatesCommand;
use UMC\Diagnostics\Diagnostics;
use UMC\Cart\CartRecalculation;
use UMC\Checkout\CheckoutCurrencyPolicy;
use UMC\Checkout\CheckoutEffectiveCurrencyProvider;
use UMC\Checkout\CheckoutNoticeService;
use UMC\Checkout\CheckoutPolicyCoordinator;
use UMC\Checkout\CheckoutRecalculationService;
use UMC\Checkout\CheckoutSettingsRepository;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Display\AutomaticRenderRegistry;
use UMC\Display\AutomaticSwitcherPlacement;
use UMC\Display\StorefrontRequestContext;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherBlock;
use UMC\Display\SwitcherBlockEditorAssets;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Geo\CountryContextResolver;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionApplicator;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\UniversalGeoContextAdapter;
use UMC\Geo\WooCommerceFallbackProvider;
use UMC\Compatibility\Extension\ExtensionCompatibilityBootstrap;
use UMC\Integration\ClassicCheckoutPolicyAdapter;
use UMC\Integration\CouponConversion;
use UMC\Integration\CurrencyFormatting;
use UMC\Integration\FeeConversion;
use UMC\Integration\GatewayCompatibility;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Integration\ShippingConversion;
use UMC\Order\LineItemPriceProvenance;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\HistoricalOrderDisplay;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderCurrencyFormatting;
use UMC\Order\OrderPayCurrencyLock;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Order\RefundSnapshot;
use UMC\Rates\ExchangeRateSource;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ManualRateProvider;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateHealthService;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateService;
use UMC\Rates\RateUpdateState;
use UMC\Rates\Scheduler;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceCsvIntegration;
use UMC\Pricing\FixedPriceRepository;
use UMC\Pricing\ProductPriceProvenanceRegistry;
use UMC\Pricing\ProductPriceResolutionService;
use UMC\Pricing\ProductSaleStateResolver;
use UMC\StoreApi\CartExtensionData;
use UMC\StoreApi\CheckoutBlocksNoticeAssets;
use UMC\StoreApi\CheckoutSnapshotAdapter;
use UMC\StoreApi\OrderCurrencyLock;
use UMC\StoreApi\StoreApiCheckoutPolicyAdapter;

/**
 * Instantiates services once and registers their hooks.
 *
 * The object graph is built from the store's base currency and the plugin
 * settings, then wired in layers: storefront conversion filters and the switch
 * handler, the transaction integrations (cart, coupons, core shipping, gateway
 * compatibility, order snapshot), historical order rendering and refunds, and
 * the Store API adapters serving the Cart and Checkout blocks.
 *
 * Stock is never touched; fees pass through unless opted in via `umc_convert_fee`,
 * and no monetary total is ever written: WooCommerce owns those. Everything registers on every request
 * type and gates itself at call time, which keeps the variation-price cache key
 * stable regardless of how a request arrived.
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

		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'universal-multicurrency',
					false,
					dirname( plugin_basename( UMC_PLUGIN_FILE ) ) . '/languages'
				);
			}
		);

		$this->booted = true;

		$settings     = new Settings();
		$base         = $this->base_currency();
		$rate_state   = new RateUpdateState();
		$rate_store   = new ExchangeRateStore( $settings, $rate_state, $base->code() );
		$rate_source  = $this->resolve_rate_source( $settings );
		$rate_service = new RateUpdateService( $rate_source, $rate_store, $base->code() );
		$rate_health  = new RateHealthService(
			$settings,
			$rate_store,
			new RateStatusEvaluator( $settings, $rate_store )
		);
		( new Scheduler( $rate_store, $rate_service ) )->register();
		( new PluginActionLinks() )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			\WP_CLI::add_command(
				'umc rates',
				new RatesCommand( $rate_health, $rate_service, $settings, $rate_store )
			);
		}

		if ( is_admin() ) {
			( new AdminAssets() )->register();
			( new RateUpdateController( $rate_service ) )->register();
			( new RateFailureNotice( $settings, $rate_store ) )->register();
			( new CurrencyActionController( $settings, $base, new WooCommerceCurrencyProvider() ) )->register();
			( new GeoRecommendedRulesController( $settings, $base ) )->register();
			( new GeoDetectionSimulationController( $settings, $base ) )->register();
			( new GeoSandboxController( $settings, $base ) )->register();
			( new DecisionInspectorController( $settings, $base ) )->register();
			( new GeoLegacyPanelRedirect() )->register();
		}

		$registry         = new CurrencyRegistry( $settings, $base );
		$fixed_repository = new FixedPriceRepository( $base->code() );
		$rates            = new ManualRateProvider( $settings, $base->code() );
		$context          = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$service          = new PriceConversionService( $context );
		$version          = defined( 'UMC_VERSION' ) ? (string) UMC_VERSION : '';
		$switcher_block   = new SwitcherBlock();

		( new SwitcherBlockEditorAssets() )->register();
		add_action( 'init', array( $switcher_block, 'register' ), 20 );

		if ( is_admin() ) {
			( new ProductFixedPricesPanel( $settings, $registry, $fixed_repository ) )->register();
			( new FixedPriceCoverageColumn( new FixedPriceCoverageReport( $fixed_repository ), $registry ) )->register();
			( new FixedPriceCsvIntegration( $fixed_repository, $registry ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			$fixed_price_coverage = new FixedPriceCoverageReport( $fixed_repository );
			\WP_CLI::add_command(
				'umc prices',
				new PricesCommand(
					new FixedPriceCatalogOperationsService( $fixed_repository, $fixed_price_coverage, $rates, $service, $registry ),
					new FixedPriceCatalogQuery( $fixed_price_coverage ),
					$registry
				)
			);
		}

		// Storefront: attach conversion filters, handle the switch, register the
		// shortcode. Registered on woocommerce_init so the WC session is
		// available; filters attach unconditionally and gate themselves.
		//
		// Milestone 3 adds the transaction integrations: cart recalculation on
		// currency change, coupon and core-shipping conversion, gateway currency
		// compatibility, and the immutable order snapshot written at creation.
		//
		// Milestone 4 adds historical order rendering, order-pay currency lock,
		// and refund metadata.
		add_action(
			'woocommerce_init',
			static function () use ( $context, $service, $settings, $version, $registry, $fixed_repository, $switcher_block ) {
				// One GatewayCompatibility instance is shared between the
				// storefront callback and the order-pay lock so the lock can
				// deregister the storefront callback (matched by instance) and
				// filter the original gateway set with the explicit order currency.
				$gateway_compat = new GatewayCompatibility( $context );

				$display_settings = new SwitcherSettingsRepository( $settings );
				$switcher         = new CurrencySwitcher( $context, $display_settings );

				$switcher->maybe_switch();

				$metadata_provider = new WooCommerceCurrencyProvider();
				$render_registry   = new AutomaticRenderRegistry();
				$request_context   = new StorefrontRequestContext();
				$renderer          = new SwitcherRenderer();
				$view_factory      = new SwitcherViewModelFactory( $context, $metadata_provider, $display_settings );
				$assets            = new SwitcherAssets( $request_context, $display_settings, $context );

				$assets->register();
				$switcher_block->bind( $display_settings, $view_factory, $renderer, $assets );
				( new SwitcherShortcode( $view_factory, $renderer, $assets ) )->register();
				( new AutomaticSwitcherPlacement(
					$display_settings,
					$view_factory,
					$renderer,
					$assets,
					$request_context,
					$render_registry,
					$context
				) )->register();

				$provenance_registry = new ProductPriceProvenanceRegistry();
				$price_resolution    = new ProductPriceResolutionService(
					$fixed_repository,
					new ProductSaleStateResolver(),
					$service,
					$context,
					$registry,
					$provenance_registry
				);

				( new PriceHooks( $price_resolution, $context ) )->register();
				( new LineItemPriceProvenance( $provenance_registry ) )->register();
				( new CurrencyFormatting( $context ) )->register();
				( new CartRecalculation( $context ) )->register();
				( new CouponConversion( $service, $context ) )->register();
				( new ShippingConversion( $service, $context ) )->register();
				( new FeeConversion( $service, $context ) )->register();
				$gateway_compat->register();

				$checkout_settings  = new CheckoutSettingsRepository( $settings );
				$transition_repo    = new CheckoutTransitionStateRepository();
				$notice_service     = new CheckoutNoticeService( $transition_repo );
				$effective_currency = new CheckoutEffectiveCurrencyProvider( $context );
				$recalculation      = new CheckoutRecalculationService( $context );
				$reader             = new OrderSnapshotReader();
				$resolver           = new HistoricalFormattingResolver( $registry );
				$order_context      = new OrderCurrencyContext( $reader, $resolver );
				$coordinator        = new CheckoutPolicyCoordinator(
					$checkout_settings,
					new CheckoutCurrencyPolicy(),
					$effective_currency,
					$gateway_compat,
					$recalculation,
					$transition_repo,
					$notice_service,
					$order_context
				);

				( new ClassicCheckoutPolicyAdapter( $coordinator, $context ) )->register();
				( new StoreApiCheckoutPolicyAdapter( $coordinator, $context ) )->register();

				// One OrderSnapshot instance serves both checkout flows: classic
				// checkout hooks it directly, the Store API adapter drives the same
				// writer at the equivalent points in its own lifecycle.
				$order_snapshot = new OrderSnapshot( $context, $settings, $version, $transition_repo );
				$order_snapshot->register();

				// M4 services: historical orders, refunds, order-pay.
				( new OrderCurrencyFormatting( $order_context, $resolver ) )->register();
				( new HistoricalOrderDisplay( $order_context ) )->register();
				( new OrderPayCurrencyLock( $order_context, $gateway_compat, $registry ) )->register();
				( new RefundSnapshot( $reader ) )->register();

				// M5 services: Cart and Checkout blocks via the Store API.
				( new CheckoutSnapshotAdapter( $order_snapshot ) )->register();
				( new OrderCurrencyLock( $order_context, $gateway_compat ) )->register();
				( new CartExtensionData( $context, $coordinator, $checkout_settings, $notice_service ) )->register();
				( new CheckoutBlocksNoticeAssets( $context ) )->register();

				$geo_settings_repo = new GeoDetectionSettingsRepository( $settings );
				$geo_decision      = new GeoCurrencyDecisionService( $geo_settings_repo );
				$geo_settings      = $geo_settings_repo->get();
				$is_checkout       = function_exists( 'is_checkout' ) && is_checkout();
				$country_resolver  = new CountryContextResolver(
					array(
						new UniversalGeoContextAdapter(),
						new WooCommerceFallbackProvider(
							$geo_settings->allow_wc_geolocation_fallback(),
							$geo_settings->country_precedence(),
							$is_checkout
						),
					)
				);

				$geo_applicator = new GeoDetectionApplicator(
					$geo_settings_repo,
					$geo_decision,
					$country_resolver,
					$context,
					$switcher,
					$registry,
					$order_context,
					$transition_repo
				);
				$geo_applicator->register();
				$geo_applicator->maybe_apply();

				( new ExtensionCompatibilityBootstrap( $service, $context ) )->register();
			}
		);

		// Admin settings tab (instantiated lazily, only when WC builds settings).
		add_filter(
			'woocommerce_get_settings_pages',
			static function ( array $pages ) use ( $settings, $base, $rate_store, $rate_health ): array {
				$pages[] = new SettingsPage( $settings, $base, $rate_store, $rate_health );

				return $pages;
			}
		);

		// M4 admin audit meta box.
		add_action(
			'woocommerce_init',
			static function () use ( $registry ) {
				$reader   = new OrderSnapshotReader();
				$resolver = new HistoricalFormattingResolver( $registry );
				( new OrderCurrencyMetaBox( $reader, $resolver ) )->register();
			}
		);

		// M6 diagnostics: admin-only, no price/cart/currency hooks. Conditional
		// registration cannot perturb the variation-price cache key because
		// Diagnostics attaches no WooCommerce filters.
		if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			( new Diagnostics( null, $settings, $rate_store, $rate_health ) )->register();
		}
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

	/**
	 * Resolves the configured exchange-rate source implementation.
	 *
	 * @param Settings $settings Merchant settings store.
	 */
	private function resolve_rate_source( Settings $settings ): ExchangeRateSource {
		/**
		 * Filters the available exchange-rate source implementations.
		 *
		 * @since 0.8.0
		 *
		 * @param ExchangeRateSource[] $sources Registered rate sources.
		 */
		$sources = (array) apply_filters(
			'umc_exchange_rate_sources',
			array( new FrankfurterRateSource() )
		);

		$configured = (string) ( $settings->get()['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER );

		foreach ( $sources as $source ) {
			if ( $source instanceof ExchangeRateSource && $source->id() === $configured ) {
				return $source;
			}
		}

		return new FrankfurterRateSource();
	}
}
