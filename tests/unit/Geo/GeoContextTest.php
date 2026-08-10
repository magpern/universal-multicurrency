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

	public function test_default_document_no_longer_carries_the_removed_network_and_providers_subtrees(): void {
		$document = GeoContext::default_document( false );

		$this->assertArrayNotHasKey( 'network', $document );
		$this->assertArrayNotHasKey( 'providers', $document );
		$this->assertSame( 2, GeoContext::SCHEMA_VERSION );
	}

	public function test_decode_discards_a_document_from_a_prior_schema_version(): void {
		$stale = wp_json_encode(
			array(
				'schema_version' => 1,
				'simulation'     => true,
				'shopper'        => array(),
				'geo'            => array( 'country' => 'DE' ),
				'network'        => array( 'ip_address' => null ),
				'providers'      => array( 'chain_override' => null ),
				'resolution'     => array(),
				'routing'        => array(),
			)
		);

		$this->assertNull( GeoContextSerializer::decode( (string) $stale ) );
	}

	public function test_decode_accepts_a_current_schema_version_document(): void {
		$current = GeoContextSerializer::encode( GeoContext::for_sandbox( array( 'geo' => array( 'country' => 'DE' ) ) )->to_array() );

		$this->assertInstanceOf( GeoContext::class, GeoContextSerializer::decode( $current ) );
	}

	public function test_decode_returns_null_on_genuinely_malformed_json(): void {
		$malformed_json = '{"incomplete": "json"';

		$this->assertNull( GeoContextSerializer::decode( $malformed_json ) );
	}

	public function test_decode_returns_null_when_json_decodes_to_non_array(): void {
		$json_string = wp_json_encode( 'just a string, not an array' );

		$this->assertNull( GeoContextSerializer::decode( (string) $json_string ) );
	}

	public function test_decode_returns_null_when_json_decodes_to_null(): void {
		$json_null = wp_json_encode( null );

		$this->assertNull( GeoContextSerializer::decode( (string) $json_null ) );
	}
}
