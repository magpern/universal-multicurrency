<?php
/**
 * Unit tests for ProviderMetadata.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\ProviderMetadata;

/**
 * Tests versioned provider metadata serialization.
 */
final class ProviderMetadataTest extends TestCase {

	public function test_schema_version_defaults_when_missing_from_storage(): void {
		$meta = ProviderMetadata::from_array(
			array(
				'provider_id'   => 'frankfurter',
				'provider_date' => '2026-07-24',
			)
		);

		$this->assertSame( ProviderMetadata::SCHEMA_VERSION, $meta->schema_version() );
		$this->assertSame( 'frankfurter', $meta->provider_id() );
		$this->assertSame( '2026-07-24', $meta->provider_date() );
		$this->assertNull( $meta->etag() );
	}

	public function test_nullable_fields_are_independent(): void {
		$meta = new ProviderMetadata(
			ProviderMetadata::SCHEMA_VERSION,
			'frankfurter',
			null,
			null,
			'W/"abc"',
			null
		);

		$this->assertNull( $meta->provider_date() );
		$this->assertSame( 'W/"abc"', $meta->etag() );
		$this->assertNull( $meta->last_modified() );
	}

	public function test_to_array_round_trip(): void {
		$original = new ProviderMetadata(
			1,
			'frankfurter',
			'2026-07-24',
			null,
			'etag-1',
			'Fri, 24 Jul 2026 16:00:00 GMT'
		);

		$restored = ProviderMetadata::from_array( $original->to_array() );

		$this->assertSame( $original->to_array(), $restored->to_array() );
	}
}
