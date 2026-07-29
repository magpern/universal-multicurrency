<?php
/**
 * Unit tests for the pure gateway currency classifier.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use UMC\Integration\GatewayCurrencyClassifier;

/**
 * Classification, umcCausedEmpty derivation, unknown support, and explicit removal.
 */
final class GatewayCurrencyClassifierTest extends TestCase {

	private GatewayCurrencyClassifier $classifier;

	protected function setUp(): void {
		parent::setUp();

		$this->classifier = new GatewayCurrencyClassifier();
	}

	public function test_explicit_support_retains_gateway(): void {
		$result = $this->classifier->apply(
			array( 'bacs' => (object) array( 'id' => 'bacs' ) ),
			'SEK',
			static fn () => array( 'SEK', 'EUR' )
		);

		$this->assertSame( array( 'bacs' ), array_keys( $result->filtered_gateways() ) );
		$this->assertSame( array( 'bacs' ), $result->evaluation()->retainedGatewayIds() );
		$this->assertSame( array(), $result->evaluation()->removedForCurrencyGatewayIds() );
		$this->assertFalse( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_explicit_exclusion_removes_gateway(): void {
		$result = $this->classifier->apply(
			array( 'bacs' => (object) array( 'id' => 'bacs' ) ),
			'SEK',
			static fn () => array( 'EUR' )
		);

		$this->assertSame( array(), $result->filtered_gateways() );
		$this->assertSame( array( 'bacs' ), $result->evaluation()->removedForCurrencyGatewayIds() );
		$this->assertSame( 0, $result->evaluation()->retainedCount() );
		$this->assertTrue( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_unknown_support_retains_gateway_without_causality(): void {
		$result = $this->classifier->apply(
			array( 'cheque' => (object) array( 'id' => 'cheque' ) ),
			'SEK',
			static fn () => null
		);

		$this->assertSame( array( 'cheque' ), array_keys( $result->filtered_gateways() ) );
		$this->assertSame( array( 'cheque' ), $result->evaluation()->unknownSupportGatewayIds() );
		$this->assertSame( 1, $result->evaluation()->unknownSupportCount() );
		$this->assertFalse( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_mixed_explicit_exclusion_and_unknown_prevents_umc_caused_empty(): void {
		$result = $this->classifier->apply(
			array(
				'bacs'   => (object) array( 'id' => 'bacs' ),
				'cheque' => (object) array( 'id' => 'cheque' ),
			),
			'SEK',
			static function ( object $gateway ): ?array {
				return 'bacs' === $gateway->id ? array( 'EUR' ) : null;
			}
		);

		$this->assertSame( array( 'cheque' ), array_keys( $result->filtered_gateways() ) );
		$this->assertSame( array( 'bacs' ), $result->evaluation()->removedForCurrencyGatewayIds() );
		$this->assertSame( array( 'cheque' ), $result->evaluation()->unknownSupportGatewayIds() );
		$this->assertFalse( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_all_pre_umc_gateways_explicitly_excluded_sets_umc_caused_empty(): void {
		$result = $this->classifier->apply(
			array(
				'bacs'   => (object) array( 'id' => 'bacs' ),
				'cheque' => (object) array( 'id' => 'cheque' ),
			),
			'SEK',
			static fn () => array( 'EUR' )
		);

		$this->assertSame( array(), $result->filtered_gateways() );
		$this->assertSame( 2, $result->evaluation()->beforeUmcCount() );
		$this->assertSame( 2, $result->evaluation()->removedForCurrencyCount() );
		$this->assertSame( 0, $result->evaluation()->unknownSupportCount() );
		$this->assertSame( 0, $result->evaluation()->retainedCount() );
		$this->assertSame( 0, $result->evaluation()->afterUmcCount() );
		$this->assertTrue( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_empty_incoming_map_never_causes_umc_empty(): void {
		$result = $this->classifier->apply(
			array(),
			'SEK',
			static fn () => array( 'EUR' )
		);

		$this->assertSame( array(), $result->filtered_gateways() );
		$this->assertSame( 0, $result->evaluation()->beforeUmcCount() );
		$this->assertFalse( $result->evaluation()->umcCausedEmpty() );
	}

	public function test_currency_code_is_normalized_to_uppercase(): void {
		$result = $this->classifier->apply(
			array( 'bacs' => (object) array( 'id' => 'bacs' ) ),
			'sek',
			static fn () => array( 'SEK' )
		);

		$this->assertSame( 'SEK', $result->evaluation()->currency() );
		$this->assertSame( array( 'bacs' ), array_keys( $result->filtered_gateways() ) );
	}

	public function test_enabled_gateway_count_is_recorded(): void {
		$result = $this->classifier->apply(
			array( 'bacs' => (object) array( 'id' => 'bacs' ) ),
			'SEK',
			static fn () => array( 'EUR' ),
			3
		);

		$this->assertSame( 3, $result->evaluation()->enabledGatewayCount() );
	}

	public function test_partial_retention_prevents_umc_caused_empty(): void {
		$result = $this->classifier->apply(
			array(
				'bacs'   => (object) array( 'id' => 'bacs' ),
				'cheque' => (object) array( 'id' => 'cheque' ),
			),
			'SEK',
			static function ( object $gateway ): array {
				return 'cheque' === $gateway->id ? array( 'SEK', 'EUR' ) : array( 'EUR' );
			}
		);

		$this->assertSame( array( 'cheque' ), array_keys( $result->filtered_gateways() ) );
		$this->assertSame( 1, $result->evaluation()->retainedCount() );
		$this->assertFalse( $result->evaluation()->umcCausedEmpty() );
	}
}
