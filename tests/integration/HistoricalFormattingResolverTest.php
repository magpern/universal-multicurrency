<?php
/**
 * Integration tests for historical formatting resolution.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencySnapshot;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies formatting resolution (decimals fallback, symbol, position) with a real registry.
 */
final class HistoricalFormattingResolverTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * Stored decimals from M4 snapshot take precedence.
	 */
	public function test_stored_decimals_from_snapshot_are_used(): void {
		$registry = $this->create_registry(
			array(
				'JPY' => array(
					'symbol'   => '¥',
					'position' => 'left',
				),
			)
		);

		$snapshot = new OrderCurrencySnapshot(
			2,
			'EUR',
			'JPY',
			'155.50',
			1_700_000_000,
			'manual',
			'0.4.0',
			'JPY:155.50',
			0, // Stored decimals override config.
			true,
			false,
			false,
			false,
			false
		);

		$resolver = new HistoricalFormattingResolver( $registry );
		$resolved = $resolver->resolve( $snapshot, 'JPY' );

		$this->assertSame( 'JPY', $resolved->code() );
		$this->assertSame( 0, $resolved->decimals() );
	}

	/**
	 * Falls back to current config when no stored decimals.
	 */
	public function test_current_config_used_when_no_stored_decimals(): void {
		$registry = $this->create_registry(
			array(
				'SEK' => array(
					'symbol'   => 'kr',
					'position' => 'right',
				),
			)
		);

		$snapshot = new OrderCurrencySnapshot(
			1,
			'EUR',
			'SEK',
			'11.50',
			1_700_000_000,
			'manual',
			'0.3.0',
			'SEK:11.50',
			null,
			true,
			false,
			false,
			false,
			false
		);

		$resolver = new HistoricalFormattingResolver( $registry );
		$resolved = $resolver->resolve( $snapshot, 'SEK' );

		$this->assertSame( 2, $resolved->decimals() );
		$this->assertSame( 'kr', $resolved->symbol() );
	}

	/**
	 * Falls back to ISO map when currency is disabled.
	 */
	public function test_iso_map_used_when_currency_disabled(): void {
		$registry = $this->create_registry( array() );

		$snapshot = new OrderCurrencySnapshot(
			1,
			'EUR',
			'JPY',
			'155.50',
			1_700_000_000,
			'manual',
			'0.3.0',
			'JPY:155.50',
			null,
			true,
			false,
			false,
			false,
			false
		);

		$resolver = new HistoricalFormattingResolver( $registry );
		$resolved = $resolver->resolve( $snapshot, 'JPY' );

		// ISO map: JPY = 0.
		$this->assertSame( 0, $resolved->decimals() );
	}

	/**
	 * Creates a real CurrencyRegistry from test data.
	 *
	 * @param array<string, array{symbol: string, position: string}> $currencies Configured currencies.
	 */
	private function create_registry( array $currencies ): CurrencyRegistry {
		update_option( 'woocommerce_currency', 'EUR' );

		$settings_data = array();
		foreach ( $currencies as $code => $data ) {
			$settings_data[ $code ] = array(
				'symbol'   => $data['symbol'],
				'position' => $data['position'],
			);
		}

		if ( $settings_data ) {
			( new Settings() )->save( array( 'currencies' => $settings_data ) );
		}

		return new CurrencyRegistry(
			new Settings(),
			new Currency( 'EUR', 2 )
		);
	}
}
