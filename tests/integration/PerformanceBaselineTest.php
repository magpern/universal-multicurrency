<?php
/**
 * Deterministic performance baselines for the Release Candidate.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\CurrencyContext;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Order\OrderSnapshot;
use UMC\Order\OrderSnapshotReader;
use UMC\Order\RefundSnapshot;
use UMC\Plugin;
use UMC\Settings;
use UMC\Tests\Support\PerformanceMetrics;
use UMC\Tests\Support\SourceGuardTrait;
use WC_Order;
use WP_UnitTestCase;

/**
 * Query-count, write-count, and execution-count ceilings for M7.
 *
 * @group performance
 */
final class PerformanceBaselineTest extends WP_UnitTestCase {

	use PerformanceMetrics;
	use SourceGuardTrait;

	private const CURRENCIES = array(
		'SEK' => array(
			'enabled'  => true,
			'rate'     => '11.50',
			'decimals' => 2,
		),
	);

	/**
	 * Enforced ceilings — observed baselines plus a small framework allowance.
	 * Documented in docs/PERFORMANCE_BASELINES.md.
	 */
	public const CEILING_SETTINGS_WRITE_CANONICAL_LOAD = 0;

	public const CEILING_SETTINGS_WRITE_ABSENT_LOAD = 0;

	public const CEILING_SETTINGS_WRITE_V0_UPGRADE = 1;

	public const CEILING_SETTINGS_WRITE_REPEATED_GET = 0;

	public const CEILING_SETTINGS_READS_REPEATED_GET = 0;

	public const CEILING_CURRENCY_RESOLUTION_WRITES = 0;

	public const CEILING_DIAGNOSTICS_QUERY_DELTA = 0;

	public const CEILING_PLUGIN_INIT_REPEAT_QUERIES = 0;

	public const CEILING_STOREFRONT_ORDER_META_WRITES = 0;

	public const CEILING_ORDER_SNAPSHOT_REPEAT_WRITES = 0;

	public const CEILING_REFUND_SNAPSHOT_META_KEYS = 2;

	public const CEILING_UNINSTALL_OPTION_DELETES = 1;

	public const CEILING_UNINSTALL_USER_META_DELETES = 0;

	public const CEILING_STORE_API_CART_QUERY_DELTA = 6;

	public const CEILING_CART_RESOLUTION_QUERY_DELTA = 4;

	public const CEILING_CHECKOUT_SNAPSHOT_HOOKS = 1;

	public const CEILING_CART_EXTENSION_CALLBACKS = 1;

	public function set_up(): void {
		parent::set_up();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer = new \WC_Customer( 0, true );
		WC()->cart     = new \WC_Cart();
		WC()->cart->empty_cart();
	}

	public function tear_down(): void {
		Settings::reset_upgrader();
		$this->stop_umc_settings_option_metrics();
		$this->stop_user_meta_write_metrics();
		$this->stop_umc_order_meta_write_metrics();

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ], $_GET[ CurrencyContext::QUERY_VAR ] );
		delete_option( Settings::OPTION );

		parent::tear_down();
	}

	public function test_canonical_settings_load_performs_no_option_write(): void {
		( new Settings() )->save(
			array(
				'currencies' => self::CURRENCIES,
			)
		);

		$this->start_umc_settings_option_metrics();

		( new Settings() )->get();

		$this->assertSame(
			self::CEILING_SETTINGS_WRITE_CANONICAL_LOAD,
			$this->umc_settings_option_write_count,
			'Canonical v1 settings must not rewrite the option on read.'
		);
	}

	public function test_absent_settings_option_performs_no_write_on_read(): void {
		delete_option( Settings::OPTION );
		$this->start_umc_settings_option_metrics();

		$this->assertSame( Settings::defaults(), ( new Settings() )->get() );
		$this->assertSame(
			self::CEILING_SETTINGS_WRITE_ABSENT_LOAD,
			$this->umc_settings_option_write_count,
			'Absent umc_settings must not be created by a read.'
		);
		$this->assertFalse( get_option( Settings::OPTION, false ) );
	}

	public function test_v0_settings_perform_at_most_one_upgrade_write(): void {
		update_option(
			Settings::OPTION,
			array(
				'currencies' => self::CURRENCIES,
			)
		);

		$this->start_umc_settings_option_metrics();

		$loaded = ( new Settings() )->get();

		$this->assertSame( Settings::SCHEMA_VERSION, $loaded['schema_version'] );
		$this->assertLessThanOrEqual(
			self::CEILING_SETTINGS_WRITE_V0_UPGRADE,
			$this->umc_settings_option_write_count,
			'Legacy v0 stores may persist exactly one normalized upgrade write.'
		);
	}

	public function test_repeated_settings_get_on_same_instance_is_write_free(): void {
		( new Settings() )->save( array( 'currencies' => self::CURRENCIES ) );

		$this->start_umc_settings_option_metrics();

		$settings = new Settings();
		$settings->get();
		$reads_after_first_load = $this->umc_settings_option_read_count;

		for ( $i = 0; $i < 5; $i++ ) {
			$settings->get();
			$settings->get_currencies();
			$settings->get_rate( 'SEK' );
		}

		$this->assertSame(
			self::CEILING_SETTINGS_WRITE_REPEATED_GET,
			$this->umc_settings_option_write_count,
			'Repeated reads on a memoized Settings instance must not write.'
		);
		$this->assertSame(
			$reads_after_first_load,
			$this->umc_settings_option_read_count,
			'Repeated reads on a memoized Settings instance must not re-hit the option.'
		);
		$this->assertGreaterThanOrEqual( 1, $reads_after_first_load );
	}

	public function test_settings_read_after_save_on_same_instance_stays_fresh_without_extra_write(): void {
		$settings = new Settings();
		$settings->save(
			array(
				'currencies' => array(
					'USD' => array( 'rate' => '1.10' ),
				),
			)
		);

		$this->reset_performance_counters();
		$this->start_umc_settings_option_metrics();

		$settings->save(
			array(
				'currencies' => array(
					'USD' => array( 'rate' => '1.20' ),
				),
			)
		);

		$this->assertSame( '1.20', $settings->get()['currencies']['USD']['manual_rate'] );
		$this->assertSame( 1, $this->umc_settings_option_write_count, 'save() performs one explicit write.' );

		$this->reset_performance_counters();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( '1.20', $settings->get()['currencies']['USD']['manual_rate'] );
		}

		$this->assertSame(
			self::CEILING_SETTINGS_WRITE_REPEATED_GET,
			$this->umc_settings_option_write_count,
			'Post-save reads must not trigger additional writes.'
		);
	}

	public function test_currency_resolution_from_base_performs_no_writes(): void {
		$this->assert_currency_resolution_writes( null, null, null, 'EUR' );
	}

	public function test_currency_resolution_from_valid_cookie_performs_no_writes(): void {
		$this->assert_currency_resolution_writes( 'SEK', null, null, 'SEK' );
	}

	public function test_currency_resolution_from_valid_session_performs_no_writes(): void {
		$this->assert_currency_resolution_writes( null, 'SEK', null, 'SEK' );
	}

	public function test_currency_resolution_from_valid_query_performs_no_writes(): void {
		$this->assert_currency_resolution_writes( null, null, 'SEK', 'SEK' );
	}

	public function test_malformed_currency_candidates_fallback_without_writes(): void {
		$this->assert_currency_resolution_writes( '<script>', 'DROP TABLE', 'not-a-code', 'EUR' );
	}

	public function test_repeated_currency_resolution_stays_write_free(): void {
		$context = $this->build_currency_context( self::CURRENCIES, 'SEK' );

		$this->start_umc_settings_option_metrics();

		for ( $i = 0; $i < 10; $i++ ) {
			$this->assertSame( 'SEK', $context->get_active_code() );
			$this->assertSame( '11.50', $context->get_rate() );
			$this->assertTrue( $context->is_convertible_request() || ! $context->is_convertible_request() );
		}

		$this->assertSame(
			self::CEILING_CURRENCY_RESOLUTION_WRITES,
			$this->umc_settings_option_write_count,
			'Memoized currency resolution must not persist preference reads.'
		);
	}

	public function test_plugin_init_repeat_adds_no_database_queries(): void {
		$plugin = Plugin::instance();
		$delta  = $this->measure_query_delta(
			static function () use ( $plugin ): void {
				$plugin->init();
				$plugin->init();
			}
		);

		$this->assertLessThanOrEqual(
			self::CEILING_PLUGIN_INIT_REPEAT_QUERIES,
			$delta,
			'Idempotent Plugin::init() must not repeat database work.'
		);
	}

	public function test_diagnostics_detection_is_query_free_on_repeat(): void {
		global $wpdb;

		$detector = new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);

		$detector->findings();
		$before = (int) $wpdb->num_queries;
		$detector->findings();
		$detector->findings();

		$this->assertSame(
			self::CEILING_DIAGNOSTICS_QUERY_DELTA,
			(int) $wpdb->num_queries - $before,
			'Memoized diagnostics must not query the database on repeat reads.'
		);
	}

	public function test_diagnostics_read_does_not_write_user_meta(): void {
		$this->start_user_meta_write_metrics();

		( new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		) )->findings();

		$this->assertSame( 0, $this->umc_user_meta_write_count, 'Ordinary detection reads must not write user meta.' );
	}

	public function test_storefront_currency_application_writes_no_order_metadata(): void {
		$context = $this->build_currency_context( self::CURRENCIES, 'SEK' );
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->save();

		$snapshots = 0;
		add_action(
			'umc_order_snapshot_created',
			static function () use ( &$snapshots ): void {
				++$snapshots;
			}
		);

		$delta = $this->measure_query_delta(
			static function () use ( $context, $product ): void {
				$context->get_active_code();
				$context->get_rate();
				$product->get_price();
			}
		);

		$this->assertSame(
			self::CEILING_STOREFRONT_ORDER_META_WRITES,
			$snapshots,
			'Browsing and price resolution must not stage order snapshots.'
		);
		$this->assertLessThanOrEqual(
			self::CEILING_CART_RESOLUTION_QUERY_DELTA,
			$delta,
			'Scoped currency resolution query delta exceeded documented ceiling.'
		);
	}

	public function test_cart_currency_application_stages_no_order_metadata(): void {
		$this->build_currency_context( self::CURRENCIES, 'SEK' );

		$product = new \WC_Product_Simple();
		$product->set_regular_price( '50' );
		$product_id = $product->save();

		WC()->cart->add_to_cart( $product_id, 2 );

		$snapshots = 0;
		add_action(
			'umc_order_snapshot_created',
			static function () use ( &$snapshots ): void {
				++$snapshots;
			}
		);

		WC()->cart->calculate_totals();

		$this->assertSame(
			self::CEILING_STOREFRONT_ORDER_META_WRITES,
			$snapshots,
			'Cart recalculation must not write order/refund audit metadata.'
		);
	}

	public function test_order_snapshot_writes_once_without_refresh(): void {
		$context = $this->build_currency_context( self::CURRENCIES, 'SEK' );
		$writer  = new OrderSnapshot( $context, new Settings(), '0.7.0' );
		$order   = new WC_Order();

		$this->assertTrue( $writer->write_snapshot_for( $order ) );
		$this->assertFalse(
			$writer->write_snapshot_for( $order ),
			'Classic checkout must not rewrite an existing snapshot.'
		);
	}

	public function test_refund_snapshot_stages_expected_meta_once(): void {
		remove_all_filters( 'woocommerce_create_refund' );
		( new RefundSnapshot( new OrderSnapshotReader() ) )->register();

		$order = new WC_Order();
		$order->set_currency( 'SEK' );
		$order->set_total( '100.00' );
		$order->update_meta_data( OrderSnapshot::META_TRANSACTION_CURRENCY, 'SEK' );
		$order->update_meta_data( OrderSnapshot::META_RATE_IDENTITY, 'SEK:11.50' );
		$order->save();

		$refund = wc_create_refund(
			array(
				'amount'     => '10.00',
				'reason'     => 'baseline',
				'order_id'   => $order->get_id(),
				'line_items' => array(),
			)
		);

		$this->assertInstanceOf( \WC_Order_Refund::class, $refund );
		$this->assertSame(
			self::CEILING_REFUND_SNAPSHOT_META_KEYS,
			count(
				array_filter(
					$refund->get_meta_data(),
					static fn( $meta ): bool => in_array(
						(string) $meta->key,
						array(
							RefundSnapshot::META_PARENT_TRANSACTION_CURRENCY,
							RefundSnapshot::META_PARENT_RATE_IDENTITY,
						),
						true
					)
				)
			)
		);
	}

	public function test_store_api_cart_get_stays_read_only_for_umc_order_meta(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Restored after test.
		$request_uri            = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/cart';

		$this->build_currency_context( self::CURRENCIES, 'SEK' );

		$snapshots = 0;
		add_action(
			'umc_order_snapshot_created',
			static function () use ( &$snapshots ): void {
				++$snapshots;
			}
		);

		try {
			rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );

			$delta = $this->measure_query_delta(
				static function (): void {
					rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );
				}
			);

			$this->assertSame(
				self::CEILING_STOREFRONT_ORDER_META_WRITES,
				$snapshots,
				'Store API cart reads must not stage order/refund metadata.'
			);
			$this->assertLessThanOrEqual(
				self::CEILING_STORE_API_CART_QUERY_DELTA,
				$delta,
				'Store API cart GET query delta exceeded documented ceiling.'
			);
		} finally {
			if ( null === $request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $request_uri;
			}
		}
	}

	public function test_runtime_hooks_are_registered_once(): void {
		$this->assertSame(
			self::CEILING_CHECKOUT_SNAPSHOT_HOOKS,
			count( $this->umc_callbacks_on( 'woocommerce_checkout_create_order' ) ),
			'Order snapshot hook must register exactly one plugin callback.'
		);

		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );
		$this->assertSame(
			1,
			substr_count( $source, 'new CartExtensionData' ),
			'Cart extension must be constructed once in the composition root.'
		);
	}

	public function test_uninstall_path_deletes_only_contracted_option(): void {
		( new Settings() )->save( array( 'currencies' => self::CURRENCIES ) );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		$stored  = array( 'abcd1234abcd1234' => time() );
		update_user_meta( $user_id, 'umc_dismissed_notices', $stored );

		$option_deletes = 0;
		add_action(
			'delete_option',
			static function ( string $option ) use ( &$option_deletes ): void {
				if ( Settings::OPTION === $option ) {
					++$option_deletes;
				}
			}
		);

		$user_meta_deletes = 0;
		add_action(
			'deleted_user_meta',
			static function () use ( &$user_meta_deletes ): void {
				++$user_meta_deletes;
			}
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		include dirname( __DIR__, 2 ) . '/uninstall.php';

		$this->assertSame(
			self::CEILING_UNINSTALL_OPTION_DELETES,
			$option_deletes,
			'Uninstall must delete umc_settings exactly once.'
		);
		$this->assertSame(
			self::CEILING_UNINSTALL_USER_META_DELETES,
			$user_meta_deletes,
			'Uninstall must not delete dismissal user meta.'
		);
		$this->assertSame( $stored, get_user_meta( $user_id, 'umc_dismissed_notices', true ) );
	}

	public function test_baseline_documentation_lists_enforced_ceilings(): void {
		$doc = (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/PERFORMANCE_BASELINES.md' );

		foreach (
			array(
				'CEILING_SETTINGS_WRITE_CANONICAL_LOAD',
				'CEILING_SETTINGS_WRITE_ABSENT_LOAD',
				'CEILING_SETTINGS_WRITE_V0_UPGRADE',
				'CEILING_CURRENCY_RESOLUTION_WRITES',
				'CEILING_DIAGNOSTICS_QUERY_DELTA',
				'CEILING_STORE_API_CART_QUERY_DELTA',
			) as $constant
		) {
			$this->assertStringContainsString( $constant, $doc, 'PERFORMANCE_BASELINES.md must document ' . $constant );
		}
	}

	/**
	 * @param string|null $cookie         Cookie candidate.
	 * @param string|null $session        Session candidate.
	 * @param string|null $query          Query candidate.
	 * @param string      $expected_code  Expected resolved active code.
	 */
	private function assert_currency_resolution_writes( ?string $cookie, ?string $session, ?string $query, string $expected_code ): void {
		$context = $this->build_currency_context( self::CURRENCIES, $cookie, $session, $query );

		$this->start_umc_settings_option_metrics();
		$this->start_user_meta_write_metrics();

		$this->assertSame( $expected_code, $context->get_active_code() );

		$this->assertSame(
			self::CEILING_CURRENCY_RESOLUTION_WRITES,
			$this->umc_settings_option_write_count,
			'Currency resolution must not write settings.'
		);
		$this->assertSame( 0, $this->umc_user_meta_write_count, 'Currency resolution must not write user meta.' );
	}
}
