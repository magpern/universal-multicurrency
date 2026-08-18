<?php
/**
 * Unit tests for the cache-state service.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CacheState;

use PHPUnit\Framework\TestCase;
use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;
use UMC\Tests\Support\OptionWriteMetrics;

/**
 * Tests the cache-state service.
 */
final class CacheStateServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		OptionWriteMetrics::reset();
	}

	/**
	 * @param array<string, mixed> $overrides Settings data overrides.
	 */
	private function service( array $overrides = array(), ?CacheStateStore $store = null ): CacheStateService {
		$defaults = array(
			'currencies' => array(
				'SEK' => array(
					'enabled'     => true,
					'manual_rate' => '11.50',
				),
			),
			'geo'        => array( 'enabled' => false ),
		);

		$settings = new Settings( array_replace_recursive( $defaults, $overrides ) );
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$geo      = new GeoDetectionSettingsRepository( $settings );

		return new CacheStateService( $registry, $geo, $settings, $store ?? new CacheStateStore() );
	}

	public function test_absent_option_is_honestly_not_reconciled(): void {
		$report = $this->service()->report();

		$this->assertFalse( $report->monitoring_enrolled() );
		$this->assertSame( '', $report->acknowledged_hash() );
		$this->assertTrue( $report->reconciliation_required() );
	}

	public function test_acknowledge_of_current_hash_succeeds_and_enrolls(): void {
		$service = $this->service();
		$hash    = $service->report()->state_hash();

		$this->assertTrue( $service->acknowledge( $hash ) );

		$report = $service->report();
		$this->assertTrue( $report->monitoring_enrolled() );
		$this->assertFalse( $report->reconciliation_required() );
	}

	public function test_acknowledge_of_stale_hash_is_rejected_and_writes_nothing(): void {
		$service = $this->service();

		$this->assertFalse( $service->acknowledge( '0000000000000000' ) );
		$this->assertSame( 0, OptionWriteMetrics::$umc_cache_state_writes );
		$this->assertFalse( $service->report()->monitoring_enrolled() );
	}

	public function test_acknowledge_of_malformed_hash_is_rejected_and_writes_nothing(): void {
		$service = $this->service();

		$this->assertFalse( $service->acknowledge( 'not-a-hash' ) );
		$this->assertFalse( $service->acknowledge( '' ) );
		$this->assertSame( 0, OptionWriteMetrics::$umc_cache_state_writes );
	}

	public function test_geo_change_after_acknowledgement_requires_reconciliation_again(): void {
		$store   = new CacheStateStore();
		$service = $this->service( array( 'geo' => array( 'enabled' => false ) ), $store );
		$store->record( $service->report()->state_hash(), 1700000000 );

		$this->assertFalse( $service->report()->reconciliation_required() );

		$after = $this->service( array( 'geo' => array( 'enabled' => true ) ), $store );

		$this->assertTrue( $after->report()->reconciliation_required() );
	}

	public function test_currency_set_change_after_acknowledgement_requires_reconciliation_again(): void {
		$store   = new CacheStateStore();
		$service = $this->service( array(), $store );
		$store->record( $service->report()->state_hash(), 1700000000 );

		$this->assertFalse( $service->report()->reconciliation_required() );

		$after = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'manual_rate' => '11.50',
					),
					'USD' => array(
						'enabled'     => true,
						'manual_rate' => '1.10',
					),
				),
			),
			$store
		);

		$this->assertTrue( $after->report()->reconciliation_required() );
	}

	public function test_rate_value_change_leaves_reconciliation_required_false_when_already_enrolled(): void {
		$store   = new CacheStateStore();
		$service = $this->service( array(), $store );
		$hash    = $service->report()->state_hash();
		$store->record( $hash, 1700000000 );

		$after = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'manual_rate' => '11.60',
					),
				),
			),
			$store
		);

		$this->assertSame( $hash, $after->report()->state_hash() );
		$this->assertFalse( $after->report()->reconciliation_required() );
	}

	public function test_rate_value_change_leaves_reconciliation_required_true_when_never_enrolled(): void {
		$before = $this->service();
		$hash   = $before->report()->state_hash();
		$this->assertTrue( $before->report()->reconciliation_required() );

		$after = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'manual_rate' => '11.60',
					),
				),
			)
		);

		$this->assertSame( $hash, $after->report()->state_hash() );
		$this->assertTrue( $after->report()->reconciliation_required() );
	}

	public function test_rates_last_updated_at_is_unaffected_by_moving_forward_alone(): void {
		$store   = new CacheStateStore();
		$service = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'manual_rate'     => '11.50',
						'rate_updated_at' => 1000,
					),
				),
			),
			$store
		);
		$hash    = $service->report()->state_hash();
		$store->record( $hash, 1700000000 );

		$after = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'manual_rate'     => '11.50',
						'rate_updated_at' => 2000,
					),
				),
			),
			$store
		);

		$this->assertSame( $hash, $after->report()->state_hash() );
		$this->assertFalse( $after->report()->reconciliation_required() );
		$this->assertSame( 2000, ( new \DateTimeImmutable( $after->report()->to_array()['rates_last_updated_at'] ) )->getTimestamp() );
	}

	public function test_rates_last_updated_at_is_max_over_selectable_non_base_currencies_only(): void {
		$service = $this->service(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'manual_rate'     => '11.50',
						'rate_updated_at' => 500,
					),
					'USD' => array(
						'enabled'         => false,
						'manual_rate'     => '1.10',
						'rate_updated_at' => 9999999,
					),
				),
			)
		);

		$array = $service->report()->to_array();

		$this->assertSame( 500, ( new \DateTimeImmutable( $array['rates_last_updated_at'] ) )->getTimestamp() );
	}

	public function test_acknowledge_writes_no_key_other_than_cache_state(): void {
		$service = $this->service();
		$hash    = $service->report()->state_hash();

		$service->acknowledge( $hash );

		$this->assertSame( 0, OptionWriteMetrics::$umc_settings_writes );
		$this->assertSame( 0, OptionWriteMetrics::$umc_rate_state_writes );
		$this->assertSame( 1, OptionWriteMetrics::$umc_cache_state_writes );
	}

	public function test_aba_sequence_documents_the_known_limitation_rather_than_fixing_it(): void {
		$store = new CacheStateStore();

		// State A.
		$a = $this->service( array( 'geo' => array( 'enabled' => false ) ), $store );
		$this->assertTrue( $a->acknowledge( $a->report()->state_hash() ) );

		// Mutate to state B; external cache is reconciled to B but acknowledgement is skipped.
		$b = $this->service( array( 'geo' => array( 'enabled' => true ) ), $store );
		$this->assertTrue( $b->report()->reconciliation_required() );

		// Mutate back to state A without ever acknowledging B.
		$a_again = $this->service( array( 'geo' => array( 'enabled' => false ) ), $store );

		$this->assertFalse(
			$a_again->report()->reconciliation_required(),
			'Documented ABA limitation: hash equality with the last acknowledgement cannot prove external runtime state.'
		);
	}
}
