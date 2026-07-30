<?php
/**
 * Unit tests for geographic region membership.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoRegionRegistry;

/**
 * Exercises EU, Eurozone, and EEA membership sets.
 */
final class GeoRegionRegistryTest extends TestCase {

	/**
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $registry;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = new GeoRegionRegistry();
	}

	public function test_germany_is_in_eu_and_eurozone(): void {
		$this->assertTrue( $this->registry->contains( 'DE', GeoRegionRegistry::REGION_EU ) );
		$this->assertTrue( $this->registry->contains( 'DE', GeoRegionRegistry::REGION_EUROZONE ) );
		$this->assertTrue( $this->registry->contains( 'DE', GeoRegionRegistry::REGION_EEA ) );
	}

	public function test_poland_is_in_eu_but_not_eurozone(): void {
		$this->assertTrue( $this->registry->contains( 'PL', GeoRegionRegistry::REGION_EU ) );
		$this->assertFalse( $this->registry->contains( 'PL', GeoRegionRegistry::REGION_EUROZONE ) );
		$this->assertTrue( $this->registry->contains( 'PL', GeoRegionRegistry::REGION_EEA ) );
	}

	public function test_sweden_and_denmark_are_in_eu_but_not_eurozone(): void {
		foreach ( array( 'SE', 'DK' ) as $code ) {
			$this->assertTrue( $this->registry->contains( $code, GeoRegionRegistry::REGION_EU ), $code );
			$this->assertFalse( $this->registry->contains( $code, GeoRegionRegistry::REGION_EUROZONE ), $code );
			$this->assertTrue( $this->registry->contains( $code, GeoRegionRegistry::REGION_EEA ), $code );
		}
	}

	public function test_norway_is_in_eea_but_not_eu(): void {
		$this->assertFalse( $this->registry->contains( 'NO', GeoRegionRegistry::REGION_EU ) );
		$this->assertFalse( $this->registry->contains( 'NO', GeoRegionRegistry::REGION_EUROZONE ) );
		$this->assertTrue( $this->registry->contains( 'NO', GeoRegionRegistry::REGION_EEA ) );
	}

	public function test_great_britain_is_in_no_region(): void {
		foreach ( array( GeoRegionRegistry::REGION_EU, GeoRegionRegistry::REGION_EUROZONE, GeoRegionRegistry::REGION_EEA ) as $region ) {
			$this->assertFalse( $this->registry->contains( 'GB', $region ), $region );
		}
	}

	public function test_is_valid_region_accepts_known_regions_case_insensitively(): void {
		$this->assertTrue( GeoRegionRegistry::is_valid_region( 'eu' ) );
		$this->assertTrue( GeoRegionRegistry::is_valid_region( 'EUROZONE' ) );
		$this->assertTrue( GeoRegionRegistry::is_valid_region( ' Eea ' ) );
		$this->assertFalse( GeoRegionRegistry::is_valid_region( 'uk' ) );
	}

	public function test_region_ids_lists_all_supported_regions(): void {
		$this->assertSame(
			array(
				GeoRegionRegistry::REGION_EU,
				GeoRegionRegistry::REGION_EUROZONE,
				GeoRegionRegistry::REGION_EEA,
			),
			GeoRegionRegistry::region_ids()
		);
	}

	public function test_members_returns_sorted_country_codes(): void {
		$members = $this->registry->members( GeoRegionRegistry::REGION_EUROZONE );
		$sorted  = $members;
		sort( $sorted );

		$this->assertSame( $sorted, $members );
		$this->assertContains( 'DE', $members );
		$this->assertNotContains( 'PL', $members );
	}

	public function test_members_returns_empty_for_unknown_region(): void {
		$this->assertSame( array(), $this->registry->members( 'unknown' ) );
	}

	public function test_contains_normalizes_country_code_and_region_id(): void {
		$this->assertTrue( $this->registry->contains( ' de ', ' EU ' ) );
	}

	public function test_label_context_returns_lowercase_region_id(): void {
		$this->assertSame( 'eurozone', GeoRegionRegistry::label_context( 'EUROZONE' ) );
	}
}
