<?php
/**
 * First-match geographic currency rule evaluator.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Pure ordered rule evaluation — no WordPress, sessions, or persistence.
 */
final class GeoCurrencyRuleEvaluator {

	/**
	 * Geographic region membership registry.
	 *
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $regions;

	/**
	 * Constructs the evaluator.
	 *
	 * @param GeoRegionRegistry|null $regions Region registry.
	 */
	public function __construct( ?GeoRegionRegistry $regions = null ) {
		$this->regions = $regions ?? new GeoRegionRegistry();
	}

	/**
	 * Evaluates ordered rules against a country code.
	 *
	 * @param string                     $country_code        Uppercase ISO alpha-2.
	 * @param array<int, GeoRoutingRule> $rules               Ordered rules.
	 * @param array<int, string>         $selectable          Selectable currency codes.
	 * @param string                     $technical_fallback  Technical fallback or ''.
	 * @param string                     $base_currency       Store base currency.
	 */
	public function evaluate(
		string $country_code,
		array $rules,
		array $selectable,
		string $technical_fallback,
		string $base_currency
	): GeoRuleEvaluationResult {
		$country_code = strtoupper( trim( $country_code ) );
		$selectable   = array_map( 'strtoupper', $selectable );
		$base         = strtoupper( trim( $base_currency ) );
		$fallback     = strtoupper( trim( $technical_fallback ) );

		$trace    = array();
		$warnings = array();

		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			return $this->resolve_fallback( $selectable, $fallback, $base, $trace, $warnings, true );
		}

		foreach ( $rules as $index => $rule ) {
			$label   = $this->rule_label( $rule );
			$matched = $this->rule_matches( $rule, $country_code );

			$step = array(
				'index'    => $index,
				'label'    => $label,
				'currency' => $rule->currency(),
				'matched'  => $matched,
				'stopped'  => false,
			);

			if ( ! $matched ) {
				$step['reason'] = 'no_match';
				$trace[]        = $step;
				continue;
			}

			$currency = strtoupper( $rule->currency() );

			if ( $currency !== $base && ! in_array( $currency, $selectable, true ) ) {
				$warnings[]      = sprintf( 'Rule %s matched but currency %s is unavailable.', $label, $currency );
				$step['reason']  = 'currency_unavailable';
				$step['matched'] = false;
				$trace[]         = $step;
				continue;
			}

			$step['reason']  = 'match';
			$step['stopped'] = true;
			$trace[]         = $step;

			return new GeoRuleEvaluationResult(
				$currency,
				$rule->id(),
				$rule->type(),
				$label,
				$index,
				GeoRoutingRule::TYPE_OTHER === $rule->type(),
				false,
				$trace,
				$warnings
			);
		}

		return $this->resolve_fallback( $selectable, $fallback, $base, $trace, $warnings, true );
	}

	/**
	 * Resolves technical fallback then base.
	 *
	 * @param array<int, string>              $selectable  Selectable codes.
	 * @param string                          $fallback    Technical fallback.
	 * @param string                          $base        Base currency.
	 * @param array<int, array<string,mixed>> $trace       Trace steps.
	 * @param array<int, string>              $warnings    Warnings.
	 * @param bool                            $used_fallback Whether fallback path taken.
	 */
	private function resolve_fallback(
		array $selectable,
		string $fallback,
		string $base,
		array $trace,
		array $warnings,
		bool $used_fallback
	): GeoRuleEvaluationResult {
		if ( '' !== $fallback && ( $fallback === $base || in_array( $fallback, $selectable, true ) ) ) {
			return new GeoRuleEvaluationResult(
				$fallback,
				null,
				null,
				null,
				null,
				false,
				true,
				$trace,
				$warnings
			);
		}

		return new GeoRuleEvaluationResult(
			$base,
			null,
			null,
			null,
			null,
			false,
			$used_fallback,
			$trace,
			$warnings
		);
	}

	/**
	 * Whether a rule matches the visitor country.
	 *
	 * @param GeoRoutingRule $rule         Rule.
	 * @param string         $country_code Country code.
	 */
	private function rule_matches( GeoRoutingRule $rule, string $country_code ): bool {
		if ( GeoRoutingRule::TYPE_COUNTRY === $rule->type() ) {
			return $rule->value() === $country_code;
		}

		if ( GeoRoutingRule::TYPE_REGION === $rule->type() ) {
			return $this->regions->contains( $country_code, $rule->value() );
		}

		return GeoRoutingRule::TYPE_OTHER === $rule->type();
	}

	/**
	 * Stable diagnostic label for a rule.
	 *
	 * @param GeoRoutingRule $rule Rule.
	 */
	public function rule_label( GeoRoutingRule $rule ): string {
		if ( GeoRoutingRule::TYPE_COUNTRY === $rule->type() ) {
			return $rule->value();
		}

		if ( GeoRoutingRule::TYPE_REGION === $rule->type() ) {
			return 'region:' . $rule->value();
		}

		return 'other';
	}
}
