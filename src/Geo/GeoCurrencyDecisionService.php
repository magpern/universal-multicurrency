<?php
/**
 * Geo currency decision orchestration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

use UMC\CurrencyContext;

/**
 * Combines country resolution, rule evaluation, and shopper precedence simulation.
 */
final class GeoCurrencyDecisionService {

	public const SESSION_GEO_APPLIED = 'umc_geo_applied';

	public const SESSION_GEO_SESSION_DONE = 'umc_geo_session_done';

	/**
	 * Geo settings repository.
	 *
	 * @var GeoDetectionSettingsRepository
	 */
	private GeoDetectionSettingsRepository $settings;

	/**
	 * Rule evaluator.
	 *
	 * @var GeoCurrencyRuleEvaluator
	 */
	private GeoCurrencyRuleEvaluator $evaluator;

	/**
	 * Region registry.
	 *
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $regions;

	/**
	 * Constructs the decision service.
	 *
	 * @param GeoDetectionSettingsRepository $settings   Settings repository.
	 * @param GeoCurrencyRuleEvaluator|null  $evaluator  Rule evaluator.
	 * @param GeoRegionRegistry|null         $regions    Region registry.
	 */
	public function __construct(
		GeoDetectionSettingsRepository $settings,
		?GeoCurrencyRuleEvaluator $evaluator = null,
		?GeoRegionRegistry $regions = null
	) {
		$this->settings  = $settings;
		$this->regions   = $regions ?? new GeoRegionRegistry();
		$this->evaluator = $evaluator ?? new GeoCurrencyRuleEvaluator( $this->regions );
	}

	/**
	 * Evaluates geo routing for a known country code.
	 *
	 * @param string                    $country_code       ISO alpha-2.
	 * @param array<int, string>        $selectable         Selectable currencies.
	 * @param string                    $base_currency      Base currency.
	 * @param GeoDetectionSettings|null $settings_override Optional settings.
	 */
	public function evaluate_country(
		string $country_code,
		array $selectable,
		string $base_currency,
		?GeoDetectionSettings $settings_override = null
	): GeoRuleEvaluationResult {
		$settings = $settings_override ?? $this->settings->get();
		$fallback = $settings->fallback_currency();

		return $this->evaluator->evaluate(
			$country_code,
			$settings->rules(),
			$selectable,
			$fallback,
			$base_currency
		);
	}

	/**
	 * Simulates the full geo decision including shopper precedence flags.
	 *
	 * @param array<string, mixed> $input Simulation input.
	 */
	public function simulate( array $input ): array {
		$settings        = $this->settings->get();
		$selectable      = is_array( $input['selectable'] ?? null ) ? array_map( 'strtoupper', $input['selectable'] ) : array();
		$base            = strtoupper( (string) ( $input['base_currency'] ?? 'EUR' ) );
		$country         = strtoupper( trim( (string) ( $input['country_code'] ?? '' ) ) );
		$explicit        = strtoupper( trim( (string) ( $input['explicit_currency'] ?? '' ) ) );
		$session         = strtoupper( trim( (string) ( $input['session_currency'] ?? '' ) ) );
		$cookie          = strtoupper( trim( (string) ( $input['cookie_currency'] ?? '' ) ) );
		$checkout_locked = ! empty( $input['checkout_locked'] );
		$geo_enabled     = array_key_exists( 'geo_enabled', $input ) ? (bool) $input['geo_enabled'] : $settings->is_enabled();

		$output = array(
			'geo_skipped'             => false,
			'geo_skip_reason'         => '',
			'country_code'            => $country,
			'evaluation'              => null,
			'final_currency'          => $base,
			'technical_fallback_used' => false,
			'catch_all_matched'       => false,
		);

		$shopper = $this->resolve_shopper_currency( $explicit, $session, $cookie, $base, $selectable );

		if ( null !== $shopper ) {
			$output['geo_skipped']     = true;
			$output['geo_skip_reason'] = $this->shopper_skip_reason( $explicit, $session, $cookie, $selectable );
			$output['final_currency']  = $shopper;

			return $output;
		}

		if ( $checkout_locked ) {
			$output['geo_skipped']     = true;
			$output['geo_skip_reason'] = 'checkout_locked';
			$output['final_currency']  = $base;

			return $output;
		}

		if ( ! $geo_enabled ) {
			$output['geo_skipped']     = true;
			$output['geo_skip_reason'] = 'geo_disabled';
			$output['final_currency']  = $this->fallback_currency( $settings, $selectable, $base );

			return $output;
		}

		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$evaluation = $this->evaluator->evaluate( '', $settings->rules(), $selectable, $settings->fallback_currency(), $base );
		} else {
			$evaluation = $this->evaluate_country( $country, $selectable, $base, $settings );
		}

		$output['evaluation']              = $evaluation;
		$output['final_currency']          = $evaluation->currency() ?? $base;
		$output['technical_fallback_used'] = $evaluation->technical_fallback_used();
		$output['catch_all_matched']       = $evaluation->catch_all_matched();

		return $output;
	}

	/**
	 * Whether geo application should run for the current mode and session flags.
	 *
	 * @param GeoDetectionSettings $settings Geo settings.
	 */
	public function should_apply_for_mode( GeoDetectionSettings $settings ): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return true;
		}

		$mode = $settings->mode();

		if ( GeoDetectionSettings::MODE_SESSION === $mode ) {
			return ! WC()->session->get( self::SESSION_GEO_SESSION_DONE );
		}

		if ( GeoDetectionSettings::MODE_FIRST_VISIT === $mode || GeoDetectionSettings::MODE_UNTIL_MANUAL === $mode ) {
			return ! WC()->session->get( self::SESSION_GEO_APPLIED );
		}

		return true;
	}

	/**
	 * Marks mode-specific session flags after geo application.
	 *
	 * @param GeoDetectionSettings $settings Geo settings.
	 */
	public function mark_applied( GeoDetectionSettings $settings ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_GEO_APPLIED, '1' );

		if ( GeoDetectionSettings::MODE_SESSION === $settings->mode() ) {
			WC()->session->set( self::SESSION_GEO_SESSION_DONE, '1' );
		}
	}

	/**
	 * Resolves shopper currency from explicit, session, or cookie.
	 *
	 * @param string|null        $explicit   Explicit currency.
	 * @param string|null        $session    Session currency.
	 * @param string|null        $cookie     Cookie currency.
	 * @param string             $base       Base currency.
	 * @param array<int, string> $selectable Selectable codes.
	 */
	private function resolve_shopper_currency( ?string $explicit, ?string $session, ?string $cookie, string $base, array $selectable ): ?string {
		foreach ( array( $explicit, $session, $cookie ) as $candidate ) {
			$code = is_string( $candidate ) ? strtoupper( trim( $candidate ) ) : '';

			if ( '' === $code ) {
				continue;
			}

			if ( $code === $base || in_array( $code, $selectable, true ) ) {
				return $code;
			}
		}

		return null;
	}

	/**
	 * Determines why geo was skipped due to shopper currency.
	 *
	 * @param string             $explicit   Explicit.
	 * @param string             $session    Session.
	 * @param string             $cookie     Cookie.
	 * @param array<int, string> $selectable Selectable.
	 */
	private function shopper_skip_reason( string $explicit, string $session, string $cookie, array $selectable ): string {
		if ( '' !== $explicit && ( in_array( $explicit, $selectable, true ) ) ) {
			return 'explicit_currency';
		}

		if ( '' !== $session && ( in_array( $session, $selectable, true ) ) ) {
			return 'session_currency';
		}

		if ( '' !== $cookie && ( in_array( $cookie, $selectable, true ) ) ) {
			return 'cookie_currency';
		}

		return 'shopper_currency';
	}

	/**
	 * Resolves enabled fallback or base currency.
	 *
	 * @param GeoDetectionSettings $settings   Settings.
	 * @param array<int, string>   $selectable Selectable.
	 * @param string               $base       Base.
	 */
	private function fallback_currency( GeoDetectionSettings $settings, array $selectable, string $base ): string {
		$fallback = strtoupper( trim( $settings->fallback_currency() ) );

		if ( '' !== $fallback && in_array( $fallback, $selectable, true ) ) {
			return $fallback;
		}

		return $base;
	}
}
