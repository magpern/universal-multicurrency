<?php
/**
 * Save-time validation and shadow analysis for geo routing rules.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Validates merchant rule payloads before persistence.
 */
final class GeoRoutingRuleValidator {

	/**
	 * Geographic region membership registry.
	 *
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $regions;

	/**
	 * Rule evaluator for shadow analysis.
	 *
	 * @var GeoCurrencyRuleEvaluator
	 */
	private GeoCurrencyRuleEvaluator $evaluator;

	/**
	 * Constructs the validator.
	 *
	 * @param GeoRegionRegistry|null        $regions   Region registry.
	 * @param GeoCurrencyRuleEvaluator|null $evaluator Rule evaluator.
	 */
	public function __construct( ?GeoRegionRegistry $regions = null, ?GeoCurrencyRuleEvaluator $evaluator = null ) {
		$this->regions   = $regions ?? new GeoRegionRegistry();
		$this->evaluator = $evaluator ?? new GeoCurrencyRuleEvaluator( $this->regions );
	}

	/**
	 * Validates rules for save.
	 *
	 * @param array<int, GeoRoutingRule> $rules             Ordered rules.
	 * @param array<int, string>         $selectable_currencies Selectable currency codes.
	 * @param bool                       $geo_enabled         Whether geo is enabled.
	 */
	public function validate( array $rules, array $selectable_currencies, bool $geo_enabled ): GeoRoutingValidationResult {
		$errors   = array();
		$warnings = array();

		if ( count( $rules ) > GeoRoutingRule::MAX_RULES ) {
			$errors[] = sprintf(
				/* translators: %d: maximum number of routing rules */
				__( 'Geo routing supports at most %d rules.', 'universal-multicurrency' ),
				GeoRoutingRule::MAX_RULES
			);

			return new GeoRoutingValidationResult( $errors, $warnings );
		}

		$countries_seen = array();
		$regions_seen   = array();
		$other_count    = 0;
		$other_index    = null;
		$valid_count    = 0;

		foreach ( $rules as $index => $rule ) {
			$position = $index + 1;

			if ( GeoRoutingRule::TYPE_COUNTRY === $rule->type() ) {
				if ( isset( $countries_seen[ $rule->value() ] ) ) {
					$errors[] = sprintf(
						/* translators: 1: country code, 2: rule position */
						__( 'Duplicate country rule for %1$s at position %2$d.', 'universal-multicurrency' ),
						$rule->value(),
						$position
					);
				}

				$countries_seen[ $rule->value() ] = $position;
			} elseif ( GeoRoutingRule::TYPE_REGION === $rule->type() ) {
				if ( isset( $regions_seen[ $rule->value() ] ) ) {
					$errors[] = sprintf(
						/* translators: 1: region id, 2: rule position */
						__( 'Duplicate region rule for %1$s at position %2$d.', 'universal-multicurrency' ),
						$rule->value(),
						$position
					);
				}

				$regions_seen[ $rule->value() ] = $position;
			} elseif ( GeoRoutingRule::TYPE_OTHER === $rule->type() ) {
				++$other_count;
				$other_index = $index;
			} else {
				$errors[] = sprintf(
					/* translators: %d: rule position */
					__( 'Unsupported rule type at position %d.', 'universal-multicurrency' ),
					$position
				);
			}

			if ( ! $this->currency_selectable( $rule->currency(), $selectable_currencies ) ) {
				$errors[] = sprintf(
					/* translators: 1: currency code, 2: rule position */
					__( 'Currency %1$s at position %2$d is not enabled or has no rate.', 'universal-multicurrency' ),
					$rule->currency(),
					$position
				);
			} else {
				++$valid_count;
			}
		}

		if ( $other_count > 1 ) {
			$errors[] = __( 'Only one Other countries rule is allowed.', 'universal-multicurrency' );
		}

		if ( null !== $other_index && count( $rules ) - 1 !== $other_index ) {
			$errors[] = __( 'The Other countries rule must be the last rule.', 'universal-multicurrency' );
		}

		if ( 0 === $other_count ) {
			$warnings[] = __( 'No Other countries catch-all rule configured. Unmatched countries will use the technical fallback.', 'universal-multicurrency' );
		}

		if ( $geo_enabled && 0 === $valid_count ) {
			$warnings[] = __( 'Geo Detection is enabled but no valid routing rules are configured.', 'universal-multicurrency' );
		}

		$this->analyze_shadows( $rules, $warnings );

		return new GeoRoutingValidationResult( $errors, $warnings );
	}

	/**
	 * Detects country rules shadowed by earlier region rules.
	 *
	 * @param array<int, GeoRoutingRule> $rules    Rules.
	 * @param array<int, string>         $warnings Warning messages (by reference).
	 */
	private function analyze_shadows( array $rules, array &$warnings ): void {
		foreach ( $rules as $index => $rule ) {
			if ( GeoRoutingRule::TYPE_COUNTRY !== $rule->type() ) {
				continue;
			}

			for ( $prior = 0; $prior < $index; ++$prior ) {
				$earlier = $rules[ $prior ];

				if ( GeoRoutingRule::TYPE_REGION !== $earlier->type() ) {
					continue;
				}

				if ( ! $this->regions->contains( $rule->value(), $earlier->value() ) ) {
					continue;
				}

				$warnings[] = sprintf(
					/* translators: 1: country code, 2: region id */
					__( 'The %1$s rule will never be reached because an earlier %2$s rule already matches %1$s.', 'universal-multicurrency' ),
					$rule->value(),
					$earlier->value()
				);
				break;
			}
		}
	}

	/**
	 * Whether a currency is enabled and selectable.
	 *
	 * @param string             $currency   Currency code.
	 * @param array<int, string> $selectable Selectable codes.
	 */
	private function currency_selectable( string $currency, array $selectable ): bool {
		$currency   = strtoupper( trim( $currency ) );
		$selectable = array_map( 'strtoupper', $selectable );

		return 1 === preg_match( '/^[A-Z]{3}$/', $currency ) && in_array( $currency, $selectable, true );
	}
}
