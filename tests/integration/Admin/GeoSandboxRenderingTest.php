<?php
/**
 * Integration tests for the Currency Simulation (Geo Sandbox) result rendering.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\GeoSandboxController;
use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\Geo\GeoContext;
use UMC\Geo\GeoContextSerializer;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies the design-system sandbox result presentation and the
 * stale-schema discard introduced with GeoContext v2 (M14).
 */
final class GeoSandboxRenderingTest extends WP_UnitTestCase {

	/**
	 * Cached settings page for this test case.
	 *
	 * @var SettingsPage|null
	 */
	private ?SettingsPage $page = null;

	protected function setUp(): void {
		parent::setUp();

		remove_all_actions( 'woocommerce_admin_field_umc_geo_detection' );
		remove_all_actions( 'woocommerce_admin_field_umc_checkout' );
		remove_all_actions( 'woocommerce_admin_field_umc_display' );
		remove_all_actions( 'woocommerce_admin_field_umc_exchange_rates' );
		remove_all_actions( 'woocommerce_admin_field_umc_compatibility' );
		remove_all_actions( 'woocommerce_admin_field_umc_currencies' );
		remove_all_actions( 'woocommerce_admin_field_umc_placeholder' );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		global $current_section;
		$current_section = SettingsPage::SECTION_GEO_DETECTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness sets display-only query args.
		$_GET[ GeoPanelRegistry::QUERY_VAR ] = GeoPanelRegistry::PANEL_SANDBOX;
	}

	protected function tearDown(): void {
		delete_user_meta( get_current_user_id(), GeoSandboxController::RESULT_META );
		unset( $_GET['section'], $_GET['page'], $_GET['tab'], $_GET[ GeoPanelRegistry::QUERY_VAR ] );
		unset( $GLOBALS['hide_save_button'] );
		$this->page = null;

		parent::tearDown();
	}

	public function test_matched_rule_result_renders_status_badges_and_trace(): void {
		$document = GeoContext::from_array(
			array(
				'geo'     => array( 'country' => 'SE' ),
				'routing' => array(
					'final_currency' => 'SEK',
					'evaluation'     => array(
						'currency'                => 'SEK',
						'matched_rule_id'         => 'rule_00000001',
						'technical_fallback_used' => false,
						'catch_all_matched'       => false,
						'trace'                   => array(
							array(
								'index'    => 0,
								'label'    => 'SE',
								'currency' => 'SEK',
								'matched'  => true,
								'stopped'  => true,
								'reason'   => 'match',
							),
						),
					),
				),
			)
		);
		$this->store_result( $document );

		$output = $this->render_geo_field();

		$this->assertStringContainsString( 'Effective currency: SEK', $output );
		$this->assertStringContainsString( 'Matched rule: rule_00000001', $output );
		$this->assertStringContainsString( 'umc-geo-sandbox-trace', $output );
		$this->assertStringContainsString( 'umc-geo-sandbox-raw', $output );
		$this->assertStringNotContainsString( '<pre class="umc-geo-sandbox-output"><', $output );
	}

	public function test_skipped_result_renders_the_skip_reason_instead_of_a_trace(): void {
		$document = GeoContext::from_array(
			array(
				'geo'     => array( 'country' => 'SE' ),
				'routing' => array(
					'final_currency'  => 'EUR',
					'geo_skipped'     => true,
					'geo_skip_reason' => 'checkout_locked',
				),
			)
		);
		$this->store_result( $document );

		$output = $this->render_geo_field();

		$this->assertStringContainsString( 'Effective currency: EUR', $output );
		$this->assertStringContainsString( 'Currency routing was skipped', $output );
		$this->assertStringContainsString( 'Checkout has locked the currency', $output );
		$this->assertStringNotContainsString( 'umc-geo-sandbox-trace', $output );
	}

	public function test_a_stale_schema_result_is_discarded_and_nothing_renders(): void {
		update_user_meta(
			get_current_user_id(),
			GeoSandboxController::RESULT_META,
			GeoContextSerializer::encode(
				array(
					'schema_version' => 1,
					'geo'            => array( 'country' => 'SE' ),
					'network'        => array( 'ip_address' => null ),
					'providers'      => array( 'chain_override' => null ),
				)
			)
		);

		$output = $this->render_geo_field();

		$this->assertStringNotContainsString( 'Simulated outcome', $output );
		$this->assertStringNotContainsString( 'Effective currency', $output );
	}

	/**
	 * Persists a GeoContext document as the current admin's last sandbox result.
	 *
	 * @param GeoContext $document Document to persist.
	 */
	private function store_result( GeoContext $document ): void {
		update_user_meta(
			get_current_user_id(),
			GeoSandboxController::RESULT_META,
			GeoContextSerializer::encode( $document->to_array() )
		);
	}

	private function render_geo_field(): string {
		ob_start();
		$this->page()->output();

		return (string) ob_get_clean();
	}

	private function page(): SettingsPage {
		if ( null === $this->page ) {
			$settings = new Settings();

			$this->page = new SettingsPage(
				$settings,
				new Currency( 'EUR', 2 ),
				new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
			);
		}

		return $this->page;
	}
}
