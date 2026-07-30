<?php
/**
 * Geo detection simulation admin controller (legacy redirect).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Currency;
use UMC\Settings;

/**
 * Backward-compatible redirect from legacy simulation POST to Geo Sandbox.
 */
final class GeoDetectionSimulationController {

	/**
	 * Constructs the simulation controller.
	 *
	 * @param Settings $settings Unused; retained for wiring compatibility.
	 * @param Currency $base     Unused; retained for wiring compatibility.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		unset( $settings, $base );
	}

	/**
	 * Registers the admin-post handler.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_geo_simulate', array( $this, 'handle' ) );
	}

	/**
	 * Redirects legacy simulation requests to the Geo Sandbox panel.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to run geo simulations.', 'universal-multicurrency' ) );
		}

		check_admin_referer( 'umc_geo_simulate' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => 'wc-settings',
					'tab'                       => 'umc',
					'section'                   => SettingsPage::SECTION_GEO_DETECTION,
					GeoPanelRegistry::QUERY_VAR => GeoPanelRegistry::PANEL_SANDBOX,
					'umc_geo_sim_legacy'        => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
