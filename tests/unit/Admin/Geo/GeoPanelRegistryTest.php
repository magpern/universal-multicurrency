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

	public function test_panel_ids_are_exactly_overview_detection_and_sandbox(): void {
		$this->assertSame(
			array(
				GeoPanelRegistry::PANEL_OVERVIEW,
				GeoPanelRegistry::PANEL_DETECTION,
				GeoPanelRegistry::PANEL_SANDBOX,
			),
			GeoPanelRegistry::panel_ids()
		);
	}

	public function test_only_detection_is_saveable(): void {
		$this->assertTrue( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_DETECTION ) );
		$this->assertFalse( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_OVERVIEW ) );
		$this->assertFalse( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_SANDBOX ) );
		$this->assertFalse( GeoPanelRegistry::is_saveable_panel( GeoPanelRegistry::PANEL_SETTINGS ) );
	}

	public function test_panel_url_includes_geo_panel_query_var(): void {
		$url = GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SANDBOX );

		$this->assertStringContainsString( 'section=geo_detection', $url );
		$this->assertStringContainsString( GeoPanelRegistry::QUERY_VAR . '=' . GeoPanelRegistry::PANEL_SANDBOX, $url );
	}

	public function test_detection_and_sandbox_labels_use_currency_terminology(): void {
		$this->assertSame( 'Currency Routing', GeoPanelRegistry::label( GeoPanelRegistry::PANEL_DETECTION ) );
		$this->assertSame( 'Currency Simulation', GeoPanelRegistry::label( GeoPanelRegistry::PANEL_SANDBOX ) );
		$this->assertSame( 'Overview', GeoPanelRegistry::label( GeoPanelRegistry::PANEL_OVERVIEW ) );
	}

	/**
	 * @dataProvider legacy_panel_cases
	 */
	public function test_legacy_panels_redirect_to_their_new_home( string $legacy, string $expected_target ): void {
		$this->assertTrue( GeoPanelRegistry::is_legacy_panel( $legacy ) );
		$this->assertSame( $expected_target, GeoPanelRegistry::legacy_redirect_target( $legacy ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function legacy_panel_cases(): array {
		return array(
			'settings'    => array( GeoPanelRegistry::PANEL_SETTINGS, GeoPanelRegistry::PANEL_DETECTION ),
			'providers'   => array( GeoPanelRegistry::PANEL_PROVIDERS, GeoPanelRegistry::PANEL_OVERVIEW ),
			'proxies'     => array( GeoPanelRegistry::PANEL_PROXIES, GeoPanelRegistry::PANEL_OVERVIEW ),
			'diagnostics' => array( GeoPanelRegistry::PANEL_DIAGNOSTICS, GeoPanelRegistry::PANEL_OVERVIEW ),
		);
	}

	public function test_current_panels_are_not_legacy(): void {
		$this->assertFalse( GeoPanelRegistry::is_legacy_panel( GeoPanelRegistry::PANEL_OVERVIEW ) );
		$this->assertFalse( GeoPanelRegistry::is_legacy_panel( GeoPanelRegistry::PANEL_DETECTION ) );
		$this->assertFalse( GeoPanelRegistry::is_legacy_panel( GeoPanelRegistry::PANEL_SANDBOX ) );
	}

	public function test_active_panel_normalizes_a_legacy_panel_to_overview(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Unit test fixture, not a real request.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_PROVIDERS;

		try {
			$this->assertSame( GeoPanelRegistry::PANEL_OVERVIEW, GeoPanelRegistry::active_panel() );
		} finally {
			unset( $_GET[ GeoPanelRegistry::QUERY_VAR ] );
		}
	}
}
