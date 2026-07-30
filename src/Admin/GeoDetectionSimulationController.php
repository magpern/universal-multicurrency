<?php
/**
 * Geo detection simulation admin controller.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoCurrencyRuleEvaluator;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\GeoRegionRegistry;
use UMC\Settings;

/**
 * Read-only geo routing simulation via admin-post.
 */
final class GeoDetectionSimulationController {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Decision service.
	 *
	 * @var GeoCurrencyDecisionService
	 */
	private GeoCurrencyDecisionService $decision_service;

	/**
	 * Constructs the simulation controller.
	 *
	 * @param Settings $settings Settings store.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings         = $settings;
		$this->registry         = new CurrencyRegistry( $settings, $base );
		$repository             = new GeoDetectionSettingsRepository( $settings );
		$this->decision_service = new GeoCurrencyDecisionService(
			$repository,
			new GeoCurrencyRuleEvaluator( new GeoRegionRegistry() )
		);
	}

	/**
	 * Registers the admin-post handler.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_geo_simulate', array( $this, 'handle' ) );
	}

	/**
	 * Handles simulation POST.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to run geo simulations.', 'universal-multicurrency' ) );
		}

		check_admin_referer( 'umc_geo_simulate' );

		$country = isset( $_POST['sim_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['sim_country'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$selectable = $this->registry->get_selectable_codes();
		$base       = $this->registry->get_base_code();
		$shopper    = ! empty( $_POST['sim_explicit'] ) ? ( $selectable[0] ?? $base ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = $this->decision_service->simulate(
			array(
				'country_code'      => $country,
				'selectable'        => $selectable,
				'base_currency'     => $base,
				'explicit_currency' => $shopper,
				'session_currency'  => ! empty( $_POST['sim_session'] ) ? ( $selectable[0] ?? $base ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'cookie_currency'   => ! empty( $_POST['sim_cookie'] ) ? ( $selectable[0] ?? $base ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
				'checkout_locked'   => ! empty( $_POST['sim_checkout_locked'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);

		$output = $this->format_simulation( $country, $result );

		$url = add_query_arg(
			array(
				'page'        => 'wc-settings',
				'tab'         => 'umc',
				'section'     => SettingsPage::SECTION_GEO_DETECTION,
				'umc_geo_sim' => rawurlencode( $output ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Formats simulation output for display.
	 *
	 * @param string               $country Simulated country.
	 * @param array<string, mixed> $result  Simulation result.
	 */
	private function format_simulation( string $country, array $result ): string {
		$lines   = array();
		$lines[] = sprintf( 'Simulated country: %s', $country );
		$lines[] = '';

		if ( ! empty( $result['geo_skipped'] ) ) {
			$lines[] = 'Geo skipped: ' . (string) ( $result['geo_skip_reason'] ?? '' );
			$lines[] = 'Final currency: ' . (string) ( $result['final_currency'] ?? '' );

			return implode( "\n", $lines );
		}

		$evaluation = $result['evaluation'] ?? null;

		if ( $evaluation instanceof \UMC\Geo\GeoRuleEvaluationResult ) {
			$position = 1;

			foreach ( $evaluation->trace() as $step ) {
				$label   = (string) ( $step['label'] ?? '' );
				$cur     = (string) ( $step['currency'] ?? '' );
				$lines[] = sprintf( '%d. %s → %s', $position, $label, $cur );

				if ( ! empty( $step['matched'] ) && ! empty( $step['stopped'] ) ) {
					$lines[] = '   Match';
					$lines[] = '';
					$lines[] = 'Evaluation stopped.';
					break;
				}

				$lines[] = '   No match';
				++$position;
			}

			$lines[] = '';
			$lines[] = 'Final currency: ' . (string) ( $result['final_currency'] ?? '' );
			$lines[] = 'Technical fallback used: ' . ( $evaluation->technical_fallback_used() ? 'Yes' : 'No' );
			$lines[] = 'Catch-all matched: ' . ( $evaluation->catch_all_matched() ? 'Yes' : 'No' );
		}

		return implode( "\n", $lines );
	}
}
