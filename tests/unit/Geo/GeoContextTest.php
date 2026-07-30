<?php
/**
 * Unit tests for GeoContext document and serializer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoContext;
use UMC\Geo\GeoContextSerializer;

/**
 * @covers \UMC\Geo\GeoContext
 * @covers \UMC\Geo\GeoContextSerializer
 */
final class GeoContextTest extends TestCase {

	public function test_for_sandbox_marks_simulation_and_normalizes_country(): void {
		$context = GeoContext::for_sandbox(
			array(
				'geo' => array(
					'country' => ' se ',
				),
			)
		);

		$this->assertTrue( $context->is_simulation() );
		$this->assertSame( 'SE', $context->country_code() );
		$this->assertSame( GeoContext::SCHEMA_VERSION, $context->to_array()['schema_version'] );
	}

	public function test_serializer_round_trip(): void {
		$original = GeoContext::for_sandbox(
			array(
				'geo'     => array( 'country' => 'DE' ),
				'shopper' => array( 'checkout_locked' => true ),
			)
		);

		$json    = GeoContextSerializer::encode( $original->to_array() );
		$decoded = GeoContextSerializer::decode( $json );

		$this->assertInstanceOf( GeoContext::class, $decoded );
		$this->assertSame( 'DE', $decoded->country_code() );
		$this->assertTrue( $decoded->shopper()['checkout_locked'] );
	}

	public function test_from_sandbox_post_maps_precedence_flags(): void {
		$context = GeoContextSerializer::from_sandbox_post(
			array(
				'country'           => 'no',
				'explicit_currency' => '1',
				'checkout_locked'   => '1',
			),
			'NOK'
		);

		$shopper = $context->shopper();

		$this->assertSame( 'NO', $context->country_code() );
		$this->assertSame( 'NOK', $shopper['explicit_currency'] );
		$this->assertTrue( $shopper['checkout_locked'] );
	}
}
