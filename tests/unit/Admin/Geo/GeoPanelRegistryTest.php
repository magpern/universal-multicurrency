<?php
/**
 * Unit tests for Geo hub panel registry.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Admin\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Admin\Geo\GeoPanelRegistry;

/**
 * @covers \UMC\Admin\Geo\GeoPanelRegistry
 */
final class GeoPanelRegistryTest extends TestCase {

	public function test_panel_ids_include_all_hub_panels(): void {
		$ids = GeoPanelRegistry::panel_ids();

		$this->assertContains( GeoPanelRegistry::PANEL_OVERVIEW, $ids );
		$this->assertContains( GeoPanelRegistry::PANEL_DETECTION, $ids );
		$this->assertContains( GeoPanelRegistry::PANEL_SANDBOX, $ids );
		$this->assertContains( GeoPanelRegistry::PANEL_SETTINGS, $ids );
	}

	public function test_only_detection_and_settings_are_saveable(): void {
		$this->assertTrue( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_DETECTION ) );
		$this->assertTrue( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_SETTINGS ) );
		$this->assertFalse( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_OVERVIEW ) );
		$this->assertFalse( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_SANDBOX ) );
	}

	public function test_panel_url_includes_geo_panel_query_var(): void {
		$url = GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SANDBOX );

		$this->assertStringContainsString( 'section=geo_detection', $url );
		$this->assertStringContainsString( GeoPanelRegistry::QUERY_VAR . '=' . GeoPanelRegistry::PANEL_SANDBOX, $url );
	}
}
