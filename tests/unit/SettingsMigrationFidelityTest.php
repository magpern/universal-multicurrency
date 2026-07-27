<?php
/**
 * Migration fidelity tests for schema v1 → v2.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Exceptions\MissingRateException;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Proves manual-mode conversion output is unchanged by the v1 → v2 migration.
 */
final class SettingsMigrationFidelityTest extends TestCase {

	protected function tearDown(): void {
		Settings::reset_upgrader();
		parent::tearDown();
	}

	public function test_migrate_1_to_2_maps_schema_fields_for_manual_stores(): void {
		$v1       = $this->representative_v1_fixture();
		$migrated = SettingsUpgrader::migrate_1_to_2( $v1 );
		$settings = Settings::sanitize( $migrated );

		$this->assertSame( Settings::SCHEMA_VERSION, $settings['schema_version'] );
		$this->assertSame( Settings::RATE_MODE_MANUAL, $settings['rate_mode'] );
		$this->assertSame( '11.50', $settings['currencies']['SEK']['manual_rate'] );
		$this->assertSame( '161', $settings['currencies']['JPY']['manual_rate'] );
		$this->assertSame( '', $settings['currencies']['SEK']['provider_rate'] );
		$this->assertSame( '0', $settings['currencies']['SEK']['merchant_adjustment'] );
		$this->assertArrayNotHasKey( 'rate', $settings['currencies']['SEK'] );
	}

	/**
	 * @dataProvider conversion_case_provider
	 *
	 * @param string $amount       Base-currency amount.
	 * @param string $target_code  Target currency code.
	 */
	public function test_v1_to_v2_upgrade_preserves_manual_conversion_output( string $amount, string $target_code ): void {
		$v1     = $this->representative_v1_fixture();
		$before = $this->converter_for( new Settings( $v1 ) );

		$result = ( new SettingsUpgrader() )->upgrade( $v1 );
		$this->assertFalse( $result->is_failed() );
		$this->assertTrue( $result->should_persist() );

		$after = $this->converter_for( new Settings( $result->settings() ) );

		$this->assertSame(
			$before->convert( $amount, $target_code ),
			$after->convert( $amount, $target_code ),
			sprintf( 'Conversion mismatch for %s → %s', $amount, $target_code )
		);
	}

	public function test_v1_to_v2_upgrade_preserves_missing_rate_failures(): void {
		$v1     = $this->representative_v1_fixture();
		$before = $this->converter_for( new Settings( $v1 ) );
		$after  = $this->converter_for(
			new Settings( ( new SettingsUpgrader() )->upgrade( $v1 )->settings() )
		);

		foreach ( array( 'NOK', 'CHF' ) as $code ) {
			$this->assertTrue(
				$this->throws_missing_rate( $before, '100', $code ),
				"Before migration must reject {$code}."
			);
			$this->assertTrue(
				$this->throws_missing_rate( $after, '100', $code ),
				"After migration must reject {$code}."
			);
		}
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function conversion_case_provider(): array {
		return array(
			'integer rate jpy'        => array( '100', 'JPY' ),
			'decimal rate sek'        => array( '100', 'SEK' ),
			'decimal precision gbp'   => array( '199.99', 'GBP' ),
			'high precision usd rate' => array( '50', 'USD' ),
			'disabled currency rate'  => array( '25', 'USD' ),
			'base currency noop'      => array( '199.99', 'EUR' ),
			'rounding boundary'       => array( '2.675', 'SEK' ),
			'small amount'            => array( '0.01', 'SEK' ),
			'large amount'            => array( '1000000', 'JPY' ),
			'negative refund'         => array( '-100', 'GBP' ),
		);
	}

	/**
	 * Representative v1 settings as persisted before Milestone 8.
	 *
	 * @return array<string, mixed>
	 */
	private function representative_v1_fixture(): array {
		return array(
			'schema_version' => 1,
			'currencies'     => array(
				'SEK' => array(
					'enabled'         => true,
					'symbol'          => 'kr',
					'position'        => 'right_space',
					'decimals'        => 2,
					'rate'            => '11.50',
					'rate_updated_at' => 1_700_000_000,
				),
				'JPY' => array(
					'enabled'  => true,
					'decimals' => 0,
					'rate'     => '161',
				),
				'GBP' => array(
					'enabled'  => true,
					'decimals' => 2,
					'rate'     => '0.85',
				),
				'USD' => array(
					'enabled'  => false,
					'decimals' => 2,
					'rate'     => '1.2345678901',
				),
				'NOK' => array(
					'enabled'  => true,
					'decimals' => 2,
					'rate'     => '',
				),
			),
		);
	}

	private function converter_for( Settings $settings ): Converter {
		$base = new Currency( 'EUR', 2 );

		return new Converter(
			new ManualRateProvider( $settings, $base->code() ),
			new CurrencyRegistry( $settings, $base )
		);
	}

	private function throws_missing_rate( Converter $converter, string $amount, string $code ): bool {
		try {
			$converter->convert( $amount, $code );
		} catch ( MissingRateException $exception ) {
			unset( $exception );

			return true;
		}

		return false;
	}
}
