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
 * Classification, umc_caused_empty derivation, unknown support, and explicit removal.
 */
final class GatewayCurrencyClassifierTest extends TestCase {

	/**
	 * Classifier under test.
	 *
	 * @var GatewayCurrencyClassifier
	 */
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
		$this->assertSame( array( 'bacs' ), $result->evaluation()->retained_gateway_ids() );
		$this->assertSame( array(), $result->evaluation()->removed_for_currency_gateway_ids() );
		$this->assertFalse( $result->evaluation()->umc_caused_empty() );
	}

	public function test_explicit_exclusion_removes_gateway(): void {
		$result = $this->classifier->apply(
			array( 'bacs' => (object) array( 'id' => 'bacs' ) ),
			'SEK',
			static fn () => array( 'EUR' )
		);

		$this->assertSame( array(), $result->filtered_gateways() );
		$this->assertSame( array( 'bacs' ), $result->evaluation()->removed_for_currency_gateway_ids() );
		$this->assertSame( 0, $result->evaluation()->retained_count() );
		$this->assertTrue( $result->evaluation()->umc_caused_empty() );
	}

	public function test_unknown_support_retains_gateway_without_causality(): void {
		$result = $this->classifier->apply(
			array( 'cheque' => (object) array( 'id' => 'cheque' ) ),
			'SEK',
			static fn () => null
		);

		$this->assertSame( array( 'cheque' ), array_keys( $result->filtered_gateways() ) );
		$this->assertSame( array( 'cheque' ), $result->evaluation()->unknown_support_gateway_ids() );
		$this->assertSame( 1, $result->evaluation()->unknown_support_count() );
		$this->assertFalse( $result->evaluation()->umc_caused_empty() );
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
		$this->assertSame( array( 'bacs' ), $result->evaluation()->removed_for_currency_gateway_ids() );
		$this->assertSame( array( 'cheque' ), $result->evaluation()->unknown_support_gateway_ids() );
		$this->assertFalse( $result->evaluation()->umc_caused_empty() );
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
		$this->assertSame( 2, $result->evaluation()->before_umc_count() );
		$this->assertSame( 2, $result->evaluation()->removed_for_currency_count() );
		$this->assertSame( 0, $result->evaluation()->unknown_support_count() );
		$this->assertSame( 0, $result->evaluation()->retained_count() );
		$this->assertSame( 0, $result->evaluation()->after_umc_count() );
		$this->assertTrue( $result->evaluation()->umc_caused_empty() );
	}

	public function test_empty_incoming_map_never_causes_umc_empty(): void {
		$result = $this->classifier->apply(
			array(),
			'SEK',
			static fn () => array( 'EUR' )
		);

		$this->assertSame( array(), $result->filtered_gateways() );
		$this->assertSame( 0, $result->evaluation()->before_umc_count() );
		$this->assertFalse( $result->evaluation()->umc_caused_empty() );
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

		$this->assertSame( 3, $result->evaluation()->enabled_gateway_count() );
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
		$this->assertSame( 1, $result->evaluation()->retained_count() );
		$this->assertFalse( $result->evaluation()->umc_caused_empty() );
	}
}
