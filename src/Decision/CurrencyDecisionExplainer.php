<?php
/**
 * Composes structured currency decision explanations.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Decision;

use UMC\Checkout\CheckoutCurrencyPolicy;
use UMC\Checkout\CheckoutNoticeService;
use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\CurrencyResolver;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoRuleEvaluationResult;
use UMC\Integration\GatewayCurrencyEvaluation;

/**
 * On-demand explainer. Does not mutate shopper state or reimplement precedence.
 */
final class CurrencyDecisionExplainer {

	/**
	 * Shopper currency resolver.
	 *
	 * @var CurrencyResolver
	 */
	private CurrencyResolver $resolver;

	/**
	 * Geo decision service (existing simulate/evaluate path).
	 *
	 * @var GeoCurrencyDecisionService
	 */
	private GeoCurrencyDecisionService $geo_decisions;

	/**
	 * Checkout currency policy.
	 *
	 * @var CheckoutCurrencyPolicy
	 */
	private CheckoutCurrencyPolicy $checkout_policy;

	/**
	 * Notice service for eligibility payloads.
	 *
	 * @var CheckoutNoticeService
	 */
	private CheckoutNoticeService $notice_service;

	/**
	 * Constructs the explainer.
	 *
	 * @param CurrencyResolver                $resolver         Resolver.
	 * @param GeoCurrencyDecisionService      $geo_decisions    Geo decisions.
	 * @param CheckoutCurrencyPolicy|null     $checkout_policy  Checkout policy.
	 * @param CheckoutNoticeService|null      $notice_service   Notice service.
	 */
	public function __construct(
		CurrencyResolver $resolver,
		GeoCurrencyDecisionService $geo_decisions,
		?CheckoutCurrencyPolicy $checkout_policy = null,
		?CheckoutNoticeService $notice_service = null
	) {
		$this->resolver        = $resolver;
		$this->geo_decisions   = $geo_decisions;
		$this->checkout_policy = $checkout_policy ?? new CheckoutCurrencyPolicy();
		$this->notice_service  = $notice_service ?? new CheckoutNoticeService( new CheckoutTransitionStateRepository() );
	}

	/**
	 * Builds a structured explanation from deterministic inputs.
	 *
	 * @param DecisionExplanationInput $input Explanation inputs.
	 */
	public function explain( DecisionExplanationInput $input ): CurrencyDecisionExplanation {
		$resolution = $this->resolver->evaluate(
			$input->explicit_currency(),
			$input->session_currency(),
			$input->cookie_currency(),
			$input->base_currency(),
			$input->selectable()
		);

		$stages = array();

		$stages[] = $this->order_context_stage( $input );

		if ( $input->order_context_active() ) {
			$stages[] = new ExplanationStage(
				'shopper_selection',
				ExplanationStage::STATUS_BLOCKED,
				'order_context_active',
				array(
					'resolution' => $resolution->to_array(),
					'origin'     => $input->currency_origin(),
				)
			);
			$stages[] = new ExplanationStage(
				'display_currency',
				ExplanationStage::STATUS_INFO,
				'order_owned',
				array(
					'currency' => $resolution->currency(),
				)
			);

			return new CurrencyDecisionExplanation(
				$resolution->currency(),
				null,
				$resolution,
				$input->currency_origin(),
				$stages
			);
		}

		$stages[] = $this->shopper_stage( $input, $resolution );

		$geo = $this->geo_decisions->simulate(
			array(
				'explicit_currency' => (string) ( $input->explicit_currency() ?? '' ),
				'session_currency'  => (string) ( $input->session_currency() ?? '' ),
				'cookie_currency'   => (string) ( $input->cookie_currency() ?? '' ),
				'selectable'        => $input->selectable(),
				'base_currency'     => $input->base_currency(),
				'country_code'      => $input->country_code(),
				'checkout_locked'   => $input->checkout_locked(),
				'geo_enabled'       => $input->geo_enabled(),
			)
		);

		$stages[] = $this->visitor_location_stage( $input, $resolution, $geo );

		// Match GeoCurrencyDecisionService::simulate() final currency so a geo
		// application candidate becomes the explained display currency even when
		// session has not yet been written in the simulation input.
		$display = is_string( $geo['final_currency'] ?? null )
			? strtoupper( (string) $geo['final_currency'] )
			: $resolution->currency();

		$stages[] = new ExplanationStage(
			'display_currency',
			ExplanationStage::STATUS_WON,
			! empty( $geo['geo_skipped'] ) ? $resolution->winning_source() : 'visitor_location',
			array(
				'currency' => $display,
				'origin'   => $input->currency_origin(),
			)
		);

		$checkout_currency = null;

		if ( $input->include_checkout() ) {
			$checkout_stages   = $this->checkout_stages( $input, $display );
			$checkout_currency = is_string( $checkout_stages['currency'] ?? null ) ? $checkout_stages['currency'] : $display;
			foreach ( $checkout_stages['stages'] as $stage ) {
				$stages[] = $stage;
			}
		}

		return new CurrencyDecisionExplanation(
			$display,
			$checkout_currency,
			$resolution,
			$input->currency_origin(),
			$stages
		);
	}

	/**
	 * Order context stage.
	 *
	 * @param DecisionExplanationInput $input Inputs.
	 */
	private function order_context_stage( DecisionExplanationInput $input ): ExplanationStage {
		if ( $input->order_context_active() ) {
			return new ExplanationStage(
				'order_context',
				ExplanationStage::STATUS_WON,
				'active',
				array()
			);
		}

		return new ExplanationStage(
			'order_context',
			ExplanationStage::STATUS_INFO,
			'inactive',
			array()
		);
	}

	/**
	 * Shopper selection stage.
	 *
	 * @param DecisionExplanationInput   $input      Inputs.
	 * @param \UMC\CurrencyResolutionResult $resolution Resolution.
	 */
	private function shopper_stage( DecisionExplanationInput $input, \UMC\CurrencyResolutionResult $resolution ): ExplanationStage {
		$status = $resolution->was_fallback_to_base()
			? ExplanationStage::STATUS_INFO
			: ExplanationStage::STATUS_WON;

		return new ExplanationStage(
			'shopper_selection',
			$status,
			$resolution->winning_source(),
			array(
				'resolution'       => $resolution->to_array(),
				'manual_selection' => $input->manual_selection(),
				'origin'           => $input->currency_origin(),
			)
		);
	}

	/**
	 * Visitor Location stage distinguishing candidate vs won.
	 *
	 * @param DecisionExplanationInput     $input      Inputs.
	 * @param \UMC\CurrencyResolutionResult $resolution Shopper resolution.
	 * @param array<string, mixed>         $geo        simulate() output.
	 */
	private function visitor_location_stage( DecisionExplanationInput $input, \UMC\CurrencyResolutionResult $resolution, array $geo ): ExplanationStage {
		$skipped = ! empty( $geo['geo_skipped'] );
		$reason  = is_string( $geo['geo_skip_reason'] ?? null ) ? (string) $geo['geo_skip_reason'] : '';
		/** @var GeoRuleEvaluationResult|null $evaluation */
		$evaluation = $geo['evaluation'] ?? null;
		$candidate  = is_string( $geo['final_currency'] ?? null ) ? strtoupper( (string) $geo['final_currency'] ) : null;

		if ( $input->manual_selection() && $skipped ) {
			$reason = '' !== $reason ? $reason : 'manual_selection';
		}

		$participated = ! $skipped;
		$won          = $participated;

		// When geo was skipped because a higher shopper source exists, it produced
		// a candidate only in the sense of "would have run" — report blocked/skipped.
		if ( $skipped ) {
			$status = ExplanationStage::STATUS_SKIPPED;
			if ( in_array( $reason, array( 'checkout_locked', 'geo_disabled' ), true ) ) {
				$status = ExplanationStage::STATUS_BLOCKED;
			}

			return new ExplanationStage(
				'visitor_location',
				$status,
				$reason,
				array(
					'participated'   => false,
					'won'            => false,
					'candidate'      => $candidate,
					'country_code'   => $input->country_code(),
					'ugc_available'  => $input->ugc_available(),
					'evaluation'     => null,
				)
			);
		}

		$payload = array(
			'participated'  => true,
			'won'           => $won,
			'candidate'     => $candidate,
			'country_code'  => $input->country_code(),
			'ugc_available' => $input->ugc_available(),
			'evaluation'    => null,
		);

		if ( $evaluation instanceof GeoRuleEvaluationResult ) {
			$payload['evaluation'] = array(
				'currency'                => $evaluation->currency(),
				'matched_rule_id'         => $evaluation->matched_rule_id(),
				'matched_rule_type'       => $evaluation->matched_rule_type(),
				'matched_rule_label'      => $evaluation->matched_rule_label(),
				'matched_rule_index'      => $evaluation->matched_rule_index(),
				'catch_all_matched'       => $evaluation->catch_all_matched(),
				'technical_fallback_used' => $evaluation->technical_fallback_used(),
				'warnings'                => $evaluation->warnings(),
				'trace'                   => $evaluation->trace(),
			);
		}

		return new ExplanationStage(
			'visitor_location',
			$won ? ExplanationStage::STATUS_WON : ExplanationStage::STATUS_CONSIDERED,
			$won ? 'applied' : 'candidate_only',
			$payload
		);
	}

	/**
	 * Checkout policy, gateway, and notice stages.
	 *
	 * @param DecisionExplanationInput $input   Inputs.
	 * @param string                   $display Display currency.
	 * @return array{currency: string, stages: array<int, ExplanationStage>}
	 */
	private function checkout_stages( DecisionExplanationInput $input, string $display ): array {
		$settings   = new CheckoutSettings( $input->checkout_mode(), $input->show_notice() );
		$evaluation = $this->gateway_evaluation( $display, $input->gateway_supports_display() );
		$decision   = $this->checkout_policy->decide_pass_one(
			$settings,
			$display,
			$input->base_currency(),
			$input->payment_required(),
			false,
			$evaluation
		);

		if ( $decision->should_fallback() ) {
			$decision = $this->checkout_policy->decide_pass_two( $display, $input->base_currency() );
		}

		$effective = $decision->effective_currency();
		$transition_required = $display !== $effective || '' !== $decision->transition_reason();

		$stages   = array();
		$stages[] = new ExplanationStage(
			'checkout_policy',
			$transition_required ? ExplanationStage::STATUS_WON : ExplanationStage::STATUS_INFO,
			$decision->transition_reason() !== '' ? $decision->transition_reason() : 'retained',
			array(
				'mode'                => $settings->mode(),
				'display_currency'    => $display,
				'effective_currency'  => $effective,
				'settlement_currency' => $decision->settlement_currency(),
				'fallback_occurred'   => $decision->fallback_occurred(),
				'transition_required' => $transition_required,
				'recalculation'       => $transition_required,
			)
		);

		$stages[] = new ExplanationStage(
			'gateway_compatibility',
			$input->gateway_supports_display() ? ExplanationStage::STATUS_INFO : ExplanationStage::STATUS_BLOCKED,
			$input->gateway_supports_display() ? 'supported' : 'unsupported',
			array(
				'supports_display'   => $input->gateway_supports_display(),
				'before_umc_count'   => $evaluation->before_umc_count(),
				'after_umc_count'    => $evaluation->after_umc_count(),
				'umc_caused_empty'   => $evaluation->umc_caused_empty(),
			)
		);

		$state = new CheckoutTransitionState(
			$settings->mode(),
			$display,
			$effective,
			$decision->transition_reason(),
			$decision->fallback_occurred(),
			$decision->fallback_occurred(),
			$decision->settlement_currency()
		);
		$notice = $this->notice_service->build_payload( $state, $settings );

		$stages[] = new ExplanationStage(
			'customer_notice',
			! empty( $notice['show'] ) ? ExplanationStage::STATUS_INFO : ExplanationStage::STATUS_SKIPPED,
			! empty( $notice['show'] ) ? 'would_show' : 'not_shown',
			array(
				'show' => ! empty( $notice['show'] ),
			)
		);

		return array(
			'currency' => $effective,
			'stages'   => $stages,
		);
	}

	/**
	 * Builds a synthetic gateway evaluation for deterministic simulation.
	 *
	 * @param string $currency  Currency code.
	 * @param bool   $supports  Whether gateways support the currency.
	 */
	private function gateway_evaluation( string $currency, bool $supports ): GatewayCurrencyEvaluation {
		if ( $supports ) {
			return new GatewayCurrencyEvaluation(
				$currency,
				array( 'bacs' ),
				array( 'bacs' ),
				array(),
				array(),
				array( 'bacs' ),
				1,
				false
			);
		}

		return new GatewayCurrencyEvaluation(
			$currency,
			array( 'bacs' ),
			array(),
			array( 'bacs' ),
			array(),
			array(),
			1,
			true
		);
	}
}
