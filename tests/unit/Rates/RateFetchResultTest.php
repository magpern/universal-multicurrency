<?php
/**
 * Unit tests for RateFetchResult.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateQuote;

/**
 * Tests fetch result classification helpers.
 */
final class RateFetchResultTest extends TestCase {

	public function test_not_modified_result(): void {
		$result = RateFetchResult::not_modified( 'frankfurter', 1_700_000_000 );

		$this->assertTrue( $result->is_not_modified() );
		$this->assertNull( $result->metadata() );
		$this->assertSame( array(), $result->quotes() );
		$this->assertFalse( $result->is_partial_failure() );
		$this->assertFalse( $result->is_total_failure() );
	}

	public function test_partial_failure_classification(): void {
		$meta = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, 'frankfurter', '2026-07-24' );

		$result = RateFetchResult::success(
			array( new RateQuote( 'EUR', 'SEK', '11.50' ) ),
			array( 'NOK' => 'not_returned_by_provider' ),
			$meta,
			1_700_000_000
		);

		$this->assertTrue( $result->is_partial_failure() );
		$this->assertFalse( $result->is_total_failure() );
		$this->assertFalse( $result->is_not_modified() );
	}

	public function test_total_failure_classification(): void {
		$meta = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, 'frankfurter' );

		$result = RateFetchResult::success(
			array(),
			array( 'SEK' => 'provider_unavailable' ),
			$meta,
			1_700_000_000
		);

		$this->assertTrue( $result->is_total_failure() );
		$this->assertFalse( $result->is_partial_failure() );
	}
}
