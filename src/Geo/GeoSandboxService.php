<?php
/**
 * Read-only Geo Sandbox execution service.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettingsRepository;

/**
 * Runs sandbox simulations without mutating storefront session state.
 */
final class GeoSandboxService {

	/**
	 * Geo currency decision service.
	 *
	 * @var GeoCurrencyDecisionService
	 */
	private GeoCurrencyDecisionService $decision_service;

	/**
	 * Store currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Geo settings repository.
	 *
	 * @var GeoDetectionSettingsRepository
	 */
	private GeoDetectionSettingsRepository $settings;

	/**
	 * Constructs the Geo Sandbox service.
	 *
	 * @param GeoCurrencyDecisionService     $decision_service Decision service.
	 * @param CurrencyRegistry               $registry         Currency registry.
	 * @param GeoDetectionSettingsRepository $settings         Geo settings repository.
	 */
	public function __construct(
		GeoCurrencyDecisionService $decision_service,
		CurrencyRegistry $registry,
		GeoDetectionSettingsRepository $settings
	) {
		$this->decision_service = $decision_service;
		$this->registry         = $registry;
		$this->settings         = $settings;
	}

	/**
	 * Executes a sandbox run and returns an enriched GeoContext document.
	 *
	 * @param GeoContext $input Sandbox input document.
	 */
	public function run( GeoContext $input ): GeoContext {
		$selectable = $this->registry->get_selectable_codes();
		$base       = $this->registry->get_base_code();
		$shopper    = $input->shopper();
		$sample     = $selectable[0] ?? $base;

		$result = $this->decision_service->simulate(
			array(
				'country_code'      => (string) ( $input->country_code() ?? '' ),
				'selectable'        => $selectable,
				'base_currency'     => $base,
				'geo_enabled'       => $this->settings->get()->is_enabled(),
				'explicit_currency' => is_string( $shopper['explicit_currency'] ?? null ) ? (string) $shopper['explicit_currency'] : ( ! empty( $shopper['explicit_currency'] ) ? $sample : '' ),
				'session_currency'  => is_string( $shopper['session_currency'] ?? null ) ? (string) $shopper['session_currency'] : ( ! empty( $shopper['session_currency'] ) ? $sample : '' ),
				'cookie_currency'   => is_string( $shopper['cookie_currency'] ?? null ) ? (string) $shopper['cookie_currency'] : ( ! empty( $shopper['cookie_currency'] ) ? $sample : '' ),
				'checkout_locked'   => ! empty( $shopper['checkout_locked'] ),
			)
		);

		$output = $input->to_array();

		$output['resolution']['source']     = 'sandbox';
		$output['resolution']['confidence'] = null === $input->country_code() ? 0.0 : 1.0;
		$output['resolution']['trace'][]    = array(
			'step'    => 'provider',
			'message' => __( 'Sandbox uses the supplied GeoContext country directly (provider chain simulation deferred).', 'universal-multicurrency' ),
		);

		if ( ! empty( $result['geo_skipped'] ) ) {
			$output['routing']['final_currency']  = (string) ( $result['final_currency'] ?? $base );
			$output['routing']['geo_skipped']     = true;
			$output['routing']['geo_skip_reason'] = (string) ( $result['geo_skip_reason'] ?? '' );

			return GeoContext::from_array( $output );
		}

		$evaluation = $result['evaluation'] ?? null;

		if ( $evaluation instanceof GeoRuleEvaluationResult ) {
			$output['routing']['evaluation'] = array(
				'currency'                => $evaluation->currency(),
				'matched_rule_id'         => $evaluation->matched_rule_id(),
				'technical_fallback_used' => $evaluation->technical_fallback_used(),
				'catch_all_matched'       => $evaluation->catch_all_matched(),
				'trace'                   => $evaluation->trace(),
			);
		}

		$output['routing']['final_currency'] = (string) ( $result['final_currency'] ?? $base );

		return GeoContext::from_array( $output );
	}
}
