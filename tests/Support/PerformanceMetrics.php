<?php
/**
 * Deterministic performance counters for integration baselines.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use UMC\Settings;

/**
 * Tracks option, meta, and query deltas without wall-clock timing.
 */
trait PerformanceMetrics {

	/**
	 * Attempted reads of the settings option during a measurement window.
	 *
	 * @var int
	 */
	private int $umc_settings_option_read_count = 0;

	/**
	 * Attempted writes of the settings option during a measurement window.
	 *
	 * @var int
	 */
	private int $umc_settings_option_write_count = 0;

	/**
	 * User-meta writes observed during a measurement window.
	 *
	 * @var int
	 */
	private int $umc_user_meta_write_count = 0;

	/**
	 * Order-meta writes for UMC keys during a measurement window.
	 *
	 * @var int
	 */
	private int $umc_order_meta_write_count = 0;

	/**
	 * Resets in-memory counters without touching filters.
	 */
	protected function reset_performance_counters(): void {
		$this->umc_settings_option_read_count  = 0;
		$this->umc_settings_option_write_count = 0;
		$this->umc_user_meta_write_count       = 0;
		$this->umc_order_meta_write_count      = 0;
	}

	/**
	 * Starts counting settings option reads and writes.
	 */
	protected function start_umc_settings_option_metrics(): void {
		$this->reset_performance_counters();

		add_filter(
			'pre_option_' . Settings::OPTION,
			function ( $pre ) {
				++$this->umc_settings_option_read_count;

				return $pre;
			},
			1
		);

		add_filter(
			'pre_update_option_' . Settings::OPTION,
			function ( $value, $old_value ) {
				unset( $old_value );
				++$this->umc_settings_option_write_count;

				return $value;
			},
			10,
			2
		);
	}

	/**
	 * Removes settings option metric filters.
	 */
	protected function stop_umc_settings_option_metrics(): void {
		remove_all_filters( 'pre_option_' . Settings::OPTION );
		remove_all_filters( 'pre_update_option_' . Settings::OPTION );
	}

	/**
	 * Starts counting user-meta writes.
	 */
	protected function start_user_meta_write_metrics(): void {
		add_filter(
			'update_user_metadata',
			function ( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
				unset( $object_id, $meta_value, $prev_value );
				if ( is_string( $meta_key ) ) {
					++$this->umc_user_meta_write_count;
				}

				return $check;
			},
			10,
			5
		);
	}

	/**
	 * Removes user-meta write metric filters.
	 */
	protected function stop_user_meta_write_metrics(): void {
		remove_all_filters( 'update_user_metadata' );
	}

	/**
	 * Starts counting order-meta writes for UMC audit keys.
	 */
	protected function start_umc_order_meta_write_metrics(): void {
		add_action(
			'woocommerce_before_order_object_save',
			function ( $order ): void {
				if ( ! $order instanceof \WC_Order ) {
					return;
				}

				$changes = $order->get_meta_data();

				foreach ( $changes as $meta ) {
					if ( ! is_object( $meta ) || ! method_exists( $meta, 'get_data' ) ) {
						continue;
					}

					$data = $meta->get_data();
					$key  = isset( $data['key'] ) ? (string) $data['key'] : '';

					if ( 0 === strpos( $key, '_umc_' ) ) {
						++$this->umc_order_meta_write_count;
					}
				}
			},
			10,
			1
		);
	}

	/**
	 * Removes order-meta write metric hooks.
	 */
	protected function stop_umc_order_meta_write_metrics(): void {
		remove_all_actions( 'woocommerce_before_order_object_save' );
	}

	/**
	 * Returns the number of database queries executed by a callback.
	 *
	 * @param callable():void $callback Scoped operation under measurement.
	 */
	protected function measure_query_delta( callable $callback ): int {
		global $wpdb;

		$before = (int) $wpdb->num_queries;
		$callback();

		return (int) $wpdb->num_queries - $before;
	}

	/**
	 * Builds a currency graph for baseline measurements.
	 *
	 * @param array<string, array<string, mixed>> $currencies Configured currencies.
	 * @param string|null                         $cookie       Optional guest cookie code.
	 * @param string|null                         $session      Optional session code.
	 * @param string|null                         $query        Optional query preference.
	 * @return \UMC\CurrencyContext
	 */
	protected function build_currency_context(
		array $currencies,
		?string $cookie = null,
		?string $session = null,
		?string $query = null
	): \UMC\CurrencyContext {
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save( array( 'currencies' => $currencies ) );

		$settings = new Settings();
		$registry = new \UMC\CurrencyRegistry( $settings, new \UMC\Currency( 'EUR', 2 ) );
		$rates    = new \UMC\Rates\ManualRateProvider( $settings, 'EUR' );
		$context  = new \UMC\CurrencyContext( $registry, $rates, new \UMC\CurrencyResolver() );

		unset( $_COOKIE[ \UMC\CurrencyContext::COOKIE_NAME ], $_GET[ \UMC\CurrencyContext::QUERY_VAR ] );

		if ( null !== $cookie ) {
			$_COOKIE[ \UMC\CurrencyContext::COOKIE_NAME ] = $cookie;
		}

		if ( null !== WC()->session ) {
			if ( null !== $session ) {
				WC()->session->set( \UMC\CurrencyContext::SESSION_KEY, $session );
			} else {
				WC()->session->set( \UMC\CurrencyContext::SESSION_KEY, null );
			}
		}

		if ( null !== $query ) {
			$_GET[ \UMC\CurrencyContext::QUERY_VAR ] = $query;
		}

		return $context;
	}
}
