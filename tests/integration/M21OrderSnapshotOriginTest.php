<?php
/**
 * Integration tests: schema-5 origin capture at checkout write.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration;

use UMC\CurrencyContext;
use UMC\CurrencySwitcher;
use UMC\Order\OrderSnapshot;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingOriginClassifier;
use UMC\Tests\Support\M21ReportingTestCase;
use WC_Order;

/**
 * Verifies factual origin persistence and reporting classification for classic checkout.
 */
final class M21OrderSnapshotOriginTest extends M21ReportingTestCase {

	public function tear_down(): void {
		$this->seed_checkout_origin( null );
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		parent::tear_down();
	}

	public function test_customer_origin_is_persisted_at_checkout_write(): void {
		$order = $this->write_checkout_snapshot( CurrencySwitcher::ORIGIN_CUSTOMER );

		$this->assertSame( OrderSnapshot::SCHEMA_VERSION, (int) $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION ) );
		$this->assertSame( CurrencySwitcher::ORIGIN_CUSTOMER, $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN ) );
		$this->assertSame(
			CurrencySwitcher::ORIGIN_CUSTOMER,
			( new ReportingOriginClassifier() )->classify( ( new \UMC\Order\OrderSnapshotReader() )->read( $order ) )
		);
	}

	public function test_visitor_location_origin_is_persisted_at_checkout_write(): void {
		$order = $this->write_checkout_snapshot( CurrencySwitcher::ORIGIN_VISITOR_LOCATION );

		$this->assertSame( CurrencySwitcher::ORIGIN_VISITOR_LOCATION, $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN ) );
	}

	public function test_absent_provenance_omits_origin_meta(): void {
		$order = $this->write_checkout_snapshot( null );

		$this->assertSame( '', (string) $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN ) );
		$this->assertSame(
			ReportingConstants::ORIGIN_UNKNOWN,
			( new ReportingOriginClassifier() )->classify( ( new \UMC\Order\OrderSnapshotReader() )->read( $order ) )
		);
	}

	public function test_malformed_provenance_omits_origin_meta(): void {
		$order = $this->write_checkout_snapshot( 'attacker' );

		$this->assertSame( '', (string) $order->get_meta( OrderSnapshot::META_CURRENCY_ORIGIN ) );
	}

	public function test_schema_five_snapshot_is_written_for_new_orders(): void {
		$order = $this->write_checkout_snapshot( CurrencySwitcher::ORIGIN_CUSTOMER );

		$this->assertSame( OrderSnapshot::SCHEMA_VERSION, (int) $order->get_meta( OrderSnapshot::META_SNAPSHOT_VERSION ) );
		$this->assertSame( 'SEK', $order->get_meta( OrderSnapshot::META_TRANSACTION_CURRENCY ) );
	}

	private function write_checkout_snapshot( ?string $origin ): WC_Order {
		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		$this->seed_checkout_origin( $origin );

		$writer = $this->snapshot_writer(
			array(
				'SEK' => array(
					'rate'            => '11.50',
					'rate_updated_at' => 1_700_000_000,
				),
			),
			'SEK'
		);

		$order = new WC_Order();
		$order->set_currency( 'SEK' );
		$writer->write_snapshot( $order );
		$order->save();

		return wc_get_order( $order->get_id() );
	}
}
