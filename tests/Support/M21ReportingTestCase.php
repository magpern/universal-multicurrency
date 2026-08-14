<?php
/**
 * Shared harness for M21 multicurrency reporting integration tests.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

use UMC\Checkout\CheckoutSettings;
use UMC\CurrencySwitcher;
use UMC\Order\LineItemPriceProvenance;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Pricing\ProductPriceResolution;
use UMC\Reporting\CurrencyPerformanceRow;
use UMC\Reporting\LineItemProvenanceAggregator;
use UMC\Reporting\OrderReportingRepository;
use UMC\Reporting\ReportingCache;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingDateRange;
use UMC\Reporting\ReportingOriginClassifier;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingResult;
use UMC\Reporting\ReportingService;
use UMC\Reporting\RefundValueResolver;
use UMC\Reporting\TransactionCurrencyResolver;
use UMC\Settings;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Boots the reporting graph and exposes order/report fixtures.
 */
abstract class M21ReportingTestCase extends WP_UnitTestCase {

	/**
	 * HPOS-safe order loader under test.
	 *
	 * @var OrderReportingRepository
	 */
	protected OrderReportingRepository $repository;

	/**
	 * Reporting orchestrator under test.
	 *
	 * @var ReportingService
	 */
	protected ReportingService $service;

	/**
	 * Reporting cache under test.
	 *
	 * @var ReportingCache
	 */
	protected ReportingCache $cache;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_calc_taxes', 'no' );

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		$this->boot_reporting_stack();
		$this->clear_reporting_cache();
	}

	public function tear_down(): void {
		$this->clear_reporting_cache();
		delete_option( Settings::OPTION );
		delete_option( ReportingCache::GENERATION_OPTION );

		parent::tear_down();
	}

	protected function boot_reporting_stack(): void {
		$this->repository = new OrderReportingRepository(
			new OrderSnapshotReader(),
			new TransactionCurrencyResolver(),
			new RefundValueResolver(),
			new LineItemProvenanceAggregator(),
			new ReportingOriginClassifier()
		);
		$this->service    = new ReportingService( $this->repository );
		$this->cache      = new ReportingCache( $this->service );
	}

	/**
	 * @param array<string, mixed> $overrides Query input overrides.
	 */
	protected function reporting_query( array $overrides = array() ): ReportingQuery {
		$input = array_merge(
			array(
				'preset'   => ReportingDateRange::PRESET_30_DAYS,
				'statuses' => ReportingConstants::default_statuses(),
			),
			$overrides
		);

		return ReportingQuery::from_input( $input );
	}

	protected function build_report( array $query_overrides = array() ): ReportingResult {
		return $this->service->build( $this->reporting_query( $query_overrides ) );
	}

	protected function clear_reporting_cache(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . ReportingCache::TRANSIENT_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . ReportingCache::TRANSIENT_PREFIX ) . '%'
			)
		);

		delete_option( ReportingCache::GENERATION_OPTION );
		update_option( ReportingCache::GENERATION_OPTION, 1, false );
	}

	/**
	 * Creates a qualifying order with schema-5 snapshot metadata.
	 *
	 * @param array<string, mixed> $options Order options.
	 */
	protected function create_schema5_order( array $options = array() ): WC_Order {
		$currency             = (string) ( $options['currency'] ?? 'SEK' );
		$total                = (string) ( $options['total'] ?? '100.00' );
		$transaction_currency = (string) ( $options['transaction_currency'] ?? $currency );
		$rate                 = (string) ( $options['rate'] ?? '11.50' );
		$status               = (string) ( $options['status'] ?? 'completed' );
		$origin               = $options['origin'] ?? null;
		$checkout_mode        = (string) ( $options['checkout_mode'] ?? CheckoutSettings::MODE_SELECTED );
		$shopper_currency     = (string) ( $options['shopper_currency'] ?? $transaction_currency );
		$fallback_occurred    = (bool) ( $options['fallback_occurred'] ?? false );
		$rate_source          = (string) ( $options['rate_source'] ?? OrderSnapshot::SOURCE_MANUAL );
		$rate_provider        = (string) ( $options['rate_provider'] ?? '' );
		$lines                = is_array( $options['lines'] ?? null ) ? $options['lines'] : array();

		$order = wc_create_order();
		$order->set_currency( $currency );
		$order->set_status( $status );
		$order->set_date_created( time() );

		if ( array() === $lines ) {
			$lines = array(
				array(
					'source' => ProductPriceResolution::SOURCE_CONVERTED,
					'total'  => $total,
				),
			);
		}

		foreach ( $lines as $line ) {
			$this->add_product_line(
				$order,
				(string) ( $line['source'] ?? ProductPriceResolution::SOURCE_CONVERTED ),
				(string) ( $line['total'] ?? $total ),
				$transaction_currency
			);
		}

		$order->set_total( $total );

		$meta = OrderSnapshot::snapshot_meta(
			'EUR',
			$transaction_currency,
			$rate,
			1_700_000_000,
			$rate_source,
			'0.20.0',
			$transaction_currency . ':' . $rate,
			OrderSnapshot::SCHEMA_VERSION,
			'JPY' === $transaction_currency ? 0 : 2,
			$checkout_mode,
			$shopper_currency,
			$fallback_occurred,
			$rate_provider,
			'0'
		);

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( (string) $key, $value );
		}

		if ( null !== $origin && '' !== $origin ) {
			$order->update_meta_data( OrderSnapshot::META_CURRENCY_ORIGIN, (string) $origin );
		}

		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Creates a legacy order without UMC snapshot metadata.
	 *
	 * @param array<string, mixed> $options Order options.
	 */
	protected function create_legacy_order( array $options = array() ): WC_Order {
		$currency = (string) ( $options['currency'] ?? 'SEK' );
		$total    = (string) ( $options['total'] ?? '50.00' );
		$status   = (string) ( $options['status'] ?? 'completed' );
		$lines    = is_array( $options['lines'] ?? null ) ? $options['lines'] : array();

		$order = wc_create_order();
		$order->set_currency( $currency );
		$order->set_status( $status );
		$order->set_date_created( time() );

		if ( array() === $lines ) {
			$this->add_product_line( $order, ProductPriceResolution::SOURCE_CONVERTED, $total, $currency );
		} else {
			foreach ( $lines as $line ) {
				$this->add_product_line(
					$order,
					(string) ( $line['source'] ?? '' ),
					(string) ( $line['total'] ?? $total ),
					$currency
				);
			}
		}

		$order->set_total( $total );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Creates a qualifying order whose currency cannot be resolved for reporting.
	 *
	 * WooCommerce rejects invalid currency codes at write time, so the fixture
	 * persists a valid order first and then corrupts the stored currency directly.
	 *
	 * @param array<string, mixed> $options Order options.
	 */
	protected function create_unresolvable_currency_order( array $options = array() ): WC_Order {
		$order = $this->create_legacy_order(
			array_merge(
				$options,
				array(
					'currency' => 'EUR',
				)
			)
		);

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'wc_orders',
			array( 'currency' => 'INVALID' ),
			array( 'id' => $order->get_id() ),
			array( '%s' ),
			array( '%d' )
		);

		wp_cache_delete( 'order-' . $order->get_id(), 'orders' );
		wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( 'orders' ) . $order->get_id(), 'orders' );

		$reloaded = new WC_Order( $order->get_id() );
		$reloaded->get_data_store()->read( $reloaded );
		$this->assertSame( '', $reloaded->get_currency(), 'Fixture must yield an empty legacy currency for unresolvable classification.' );

		return $reloaded;
	}

	/**
	 * Creates a partial snapshot order (missing audit keys).
	 *
	 * @param array<string, mixed> $options Order options.
	 */
	protected function create_partial_snapshot_order( array $options = array() ): WC_Order {
		$currency = (string) ( $options['currency'] ?? 'USD' );
		$total    = (string) ( $options['total'] ?? '75.00' );

		$order = wc_create_order();
		$order->set_currency( $currency );
		$order->set_status( 'completed' );
		$order->set_date_created( time() );
		$this->add_product_line( $order, ProductPriceResolution::SOURCE_CONVERTED, $total, $currency );
		$order->set_total( $total );

		$order->update_meta_data( OrderSnapshot::META_SNAPSHOT_VERSION, OrderSnapshot::SCHEMA_VERSION );
		$order->update_meta_data( OrderSnapshot::META_TRANSACTION_CURRENCY, $currency );
		$order->update_meta_data( OrderSnapshot::META_BASE_CURRENCY, 'EUR' );
		$order->update_meta_data( OrderSnapshot::META_EXCHANGE_RATE, '1.20' );
		// Missing rate_timestamp, rate_source, plugin_version, rate_identity.

		$order->save();

		return wc_get_order( $order->get_id() );
	}

	protected function add_product_line(
		WC_Order $order,
		string $source,
		string $total,
		string $currency
	): WC_Order_Item_Product {
		$product = new WC_Product_Simple();
		$product->set_regular_price( $total );
		$product->set_price( $total );
		$product->save();

		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( $total );
		$item->set_total( $total );

		if ( '' !== $source ) {
			$item->update_meta_data( LineItemPriceProvenance::META_SOURCE, $source );
			$item->update_meta_data( LineItemPriceProvenance::META_CURRENCY, $currency );
		}

		$order->add_item( $item );

		return $item;
	}

	/**
	 * @return \WC_Order_Refund
	 */
	protected function refund_order( WC_Order $order, string $amount ): \WC_Order_Refund {
		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => $amount,
			)
		);

		$this->assertInstanceOf( \WC_Order_Refund::class, $refund );

		return $refund;
	}

	protected function performance_row( ReportingResult $result, string $currency ): ?CurrencyPerformanceRow {
		foreach ( $result->currency_performance()->rows() as $row ) {
			if ( $row->currency() === $currency ) {
				return $row;
			}
		}

		return null;
	}

	protected function seed_checkout_origin( ?string $origin ): void {
		WC()->session->set( CurrencySwitcher::SESSION_CURRENCY_ORIGIN, $origin );
	}

	protected function snapshot_writer( array $currencies, string $active ): OrderSnapshot {
		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new \UMC\CurrencyRegistry( $settings, new \UMC\Currency( 'EUR', 2 ) );
		$rates    = new \UMC\Rates\ManualRateProvider( $settings, 'EUR' );
		$context  = new \UMC\CurrencyContext( $registry, $rates, new \UMC\CurrencyResolver() );

		$_COOKIE[ \UMC\CurrencyContext::COOKIE_NAME ] = $active;

		return new OrderSnapshot(
			$context,
			$settings,
			(string) UMC_VERSION,
			new \UMC\Checkout\CheckoutTransitionStateRepository()
		);
	}
}
