<?php
/**
 * Integration tests for currency admin view models.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\ViewModel\CurrencyViewModelFactory;
use UMC\Currency;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies overview view-model presentation data.
 */
final class CurrencyViewModelFactoryTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_overview_puts_base_currency_first_with_base_label(): void {
		update_option( 'woocommerce_currency', 'EUR' );

		$factory = $this->view_model_factory();
		$view    = $factory->overview();

		$this->assertNotEmpty( $view->rows );
		$this->assertTrue( $view->rows[0]->is_base );
		$this->assertSame( 'EUR', $view->rows[0]->code );
		$this->assertSame( 'base', $view->rows[0]->status_class );
	}

	public function test_manual_currency_uses_manual_status_and_derivation(): void {
		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'enabled'             => true,
							'symbol'              => 'kr',
							'position'            => 'right_space',
							'decimals'            => 2,
							'manual_rate'         => '11.50',
							'provider_rate'       => '',
							'merchant_adjustment' => '0',
							'rate_mode'           => Settings::RATE_MODE_MANUAL,
							'rate_updated_at'     => time(),
						),
					),
				)
			)
		);

		$rows = $this->view_model_factory()->overview()->rows;

		$this->assertCount( 2, $rows );
		$this->assertSame( 'SEK', $rows[1]->code );
		$this->assertSame( 'manual', $rows[1]->status_class );
		$this->assertSame( '11.50', $rows[1]->effective_rate_value );
		$this->assertSame( 'Manual', $rows[1]->effective_rate_source );
	}

	public function test_automatic_adjustment_shows_signed_derivation_and_adjustment_column(): void {
		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
					'currencies' => array(
						'SEK' => array(
							'enabled'             => true,
							'symbol'              => 'kr',
							'position'            => 'right_space',
							'decimals'            => 2,
							'manual_rate'         => '',
							'provider_rate'       => '10.00',
							'merchant_adjustment' => '2',
							'rate_mode'           => Settings::RATE_MODE_AUTOMATIC,
							'rate_updated_at'     => time(),
						),
					),
				)
			)
		);

		$row = $this->view_model_factory()->overview()->rows[1];

		$this->assertSame( '10.2', $row->effective_rate_value );
		$this->assertSame( 'Automatic — Frankfurter (+2%)', $row->effective_rate_source );
		$this->assertSame( '+2%', $row->adjustment_label );
	}

	private function view_model_factory(): CurrencyViewModelFactory {
		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$store    = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );

		return new CurrencyViewModelFactory(
			$settings,
			$base,
			$store,
			new WooCommerceCurrencyProvider()
		);
	}
}
