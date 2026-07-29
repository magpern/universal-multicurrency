<?php
/**
 * Unit tests for SettingsConfigurationValidator symbol resolution.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\Validation\SettingsConfigurationValidator;
use UMC\Currency;
use UMC\Currency\CurrencyMetadata;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;
use UMC\Tests\Unit\Doubles\MapMetadataProvider;

/**
 * Verifies configuration validation respects metadata symbol defaults.
 */
final class SettingsConfigurationValidatorTest extends TestCase {

	public function test_empty_symbol_override_is_accepted_when_metadata_provides_default(): void {
		$settings = new Settings(
			Settings::sanitize(
				array(
					'currencies' => array(
						'SEK' => array(
							'enabled'     => true,
							'symbol'      => '',
							'manual_rate' => '11.50',
						),
					),
				)
			)
		);

		$results = $this->validate(
			$settings,
			new MapMetadataProvider(
				array(
					'SEK' => new CurrencyMetadata( 'SEK', 'Swedish Krona', 'kr', 2, 'right_space' ),
				)
			)
		);

		$this->assertFalse( $this->has_result_id( $results, 'config.missing_symbol.sek' ) );
	}

	public function test_empty_symbol_override_is_flagged_when_no_metadata_default_exists(): void {
		$settings = new Settings(
			Settings::sanitize(
				array(
					'currencies' => array(
						'XYZ' => array(
							'enabled'     => true,
							'symbol'      => '',
							'manual_rate' => '2.00',
						),
					),
				)
			)
		);

		$results = $this->validate( $settings, new MapMetadataProvider( array() ) );
		$match   = $this->find_result_id( $results, 'config.missing_symbol.xyz' );

		$this->assertNotNull( $match );
		$this->assertSame( CompatibilitySeverity::WARNING, $match->severity() );
		$this->assertSame( CompatibilityCategory::CONFIGURATION, $match->category() );
	}

	public function test_explicit_symbol_override_still_satisfies_symbol_check(): void {
		$settings = new Settings(
			Settings::sanitize(
				array(
					'currencies' => array(
						'USD' => array(
							'enabled'     => true,
							'symbol'      => 'US$',
							'manual_rate' => '1.10',
						),
					),
				)
			)
		);

		$results = $this->validate( $settings, new MapMetadataProvider( array() ) );

		$this->assertFalse( $this->has_result_id( $results, 'config.missing_symbol.usd' ) );
	}

	public function test_base_currency_is_not_flagged_when_missing_from_persisted_currency_rows(): void {
		$settings = new Settings(
			Settings::sanitize(
				array(
					'currencies' => array(
						'SEK' => array(
							'enabled'     => true,
							'symbol'      => '',
							'manual_rate' => '11.50',
						),
						'USD' => array(
							'enabled'     => true,
							'symbol'      => '',
							'manual_rate' => '1.10',
						),
					),
				)
			)
		);

		$results = $this->validate(
			$settings,
			new MapMetadataProvider(
				array(
					'SEK' => new CurrencyMetadata( 'SEK', 'Swedish Krona', 'kr', 2, 'right_space' ),
					'USD' => new CurrencyMetadata( 'USD', 'US Dollar', '$', 2, 'left_space' ),
				)
			)
		);

		$this->assertFalse( $this->has_result_id( $results, 'config.base_not_enabled' ) );
	}

	/**
	 * @param array<int, \UMC\Compatibility\CompatibilityResult> $results Result list.
	 * @param string                                             $id      Result identifier.
	 */
	private function has_result_id( array $results, string $id ): bool {
		return null !== $this->find_result_id( $results, $id );
	}

	/**
	 * @param array<int, \UMC\Compatibility\CompatibilityResult> $results Result list.
	 * @param string                                             $id      Result identifier.
	 */
	private function find_result_id( array $results, string $id ): ?\UMC\Compatibility\CompatibilityResult {
		foreach ( $results as $result ) {
			if ( $result->id() === $id ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * @return array<int, \UMC\Compatibility\CompatibilityResult>
	 */
	private function validate( Settings $settings, CurrencyMetadataProvider $metadata ): array {
		$base      = new Currency( 'EUR', 2 );
		$store     = new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' );
		$detector  = new ConflictDetector(
			new DetectorRegistry(),
			new ArrayEnvironmentProbe( array() ),
			new ConflictScorer()
		);
		$inventory = new CompatibilityInventory(
			$settings,
			$store,
			$base,
			$detector,
			array(),
			array(),
			array(
				'name'       => 'Test',
				'version'    => '1.0.0',
				'stylesheet' => 'test',
				'template'   => 'test',
			),
			array(),
			array(
				'umc_version'    => '0.9.0',
				'schema_version' => '3',
			)
		);

		return ( new SettingsConfigurationValidator( $metadata ) )->validate( $inventory );
	}
}
