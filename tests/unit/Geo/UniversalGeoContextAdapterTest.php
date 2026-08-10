<?php
/**
 * Characterization tests locking UniversalGeoContextAdapter storefront behaviour
 * before the M14 admin-IA refactor touches its availability check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\UniversalGeoContextAdapter;
use UMC\Tests\Support\UniversalGeoContextStub;

/**
 * @covers \UMC\Geo\UniversalGeoContextAdapter
 */
final class UniversalGeoContextAdapterTest extends TestCase {

	protected function tearDown(): void {
		UniversalGeoContextStub::reset();

		parent::tearDown();
	}

	public function test_id_is_universal_geo_context(): void {
		$this->assertSame( 'universal_geo_context', ( new UniversalGeoContextAdapter() )->id() );
	}

	public function test_is_available_when_ugc_reports_compatible_api_version(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );

		$this->assertTrue( ( new UniversalGeoContextAdapter() )->is_available() );
	}

	public function test_is_not_available_when_api_version_is_below_minimum(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 0 );

		$this->assertFalse( ( new UniversalGeoContextAdapter() )->is_available() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_not_available_when_ugc_functions_do_not_exist(): void {
		$this->assertFalse( ( new UniversalGeoContextAdapter() )->is_available() );
	}

	public function test_resolve_returns_null_when_unavailable(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 0 );
		UniversalGeoContextStub::set_country( 'SE' );

		$this->assertNull( ( new UniversalGeoContextAdapter() )->resolve() );
	}

	public function test_resolve_returns_null_when_country_is_null(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_country( null );

		$this->assertNull( ( new UniversalGeoContextAdapter() )->resolve() );
	}

	public function test_resolve_returns_null_when_country_is_invalid_shape(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_country( 'Sweden' );

		$this->assertNull( ( new UniversalGeoContextAdapter() )->resolve() );
	}

	public function test_resolve_normalizes_country_case_and_whitespace(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_country( ' se ' );

		$context = ( new UniversalGeoContextAdapter() )->resolve();

		$this->assertNotNull( $context );
		$this->assertSame( 'SE', $context->country_code() );
	}

	public function test_resolve_uses_reported_source_and_confidence(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_country( 'DE' );
		UniversalGeoContextStub::set_source( 'cloudflare' );
		UniversalGeoContextStub::set_confidence( 0.95 );

		$context = ( new UniversalGeoContextAdapter() )->resolve();

		$this->assertNotNull( $context );
		$this->assertSame( 'DE', $context->country_code() );
		$this->assertSame( 'cloudflare', $context->source() );
		$this->assertSame( 0.95, $context->confidence() );
	}

	public function test_resolve_reports_simulation_source_unmodified(): void {
		UniversalGeoContextStub::install();
		UniversalGeoContextStub::set_api_version( 1 );
		UniversalGeoContextStub::set_country( 'NO' );
		UniversalGeoContextStub::set_source( 'simulation' );
		UniversalGeoContextStub::set_confidence( 1.0 );

		$context = ( new UniversalGeoContextAdapter() )->resolve();

		$this->assertNotNull( $context );
		$this->assertSame( 'simulation', $context->source() );
	}
}
