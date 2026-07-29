<?php
/**
 * Shared checkout policy coordinator.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

use UMC\Integration\GatewayCompatibility;
use UMC\Order\OrderCurrencyContext;

/**
 * Sole orchestrator for checkout currency policy across surfaces.
 */
final class CheckoutPolicyCoordinator {

	/**
	 * Checkout settings repository.
	 *
	 * @var CheckoutSettingsRepository
	 */
	private CheckoutSettingsRepository $settings_repository;

	/**
	 * Pure checkout currency policy.
	 *
	 * @var CheckoutCurrencyPolicy
	 */
	private CheckoutCurrencyPolicy $policy;

	/**
	 * Effective currency provider.
	 *
	 * @var CheckoutEffectiveCurrencyProvider
	 */
	private CheckoutEffectiveCurrencyProvider $effective_currency;

	/**
	 * Gateway compatibility service.
	 *
	 * @var GatewayCompatibility
	 */
	private GatewayCompatibility $gateway_compatibility;

	/**
	 * Cart recalculation service.
	 *
	 * @var CheckoutRecalculationService
	 */
	private CheckoutRecalculationService $recalculation;

	/**
	 * Transition state repository.
	 *
	 * @var CheckoutTransitionStateRepository
	 */
	private CheckoutTransitionStateRepository $transition_repository;

	/**
	 * Notice service.
	 *
	 * @var CheckoutNoticeService
	 */
	private CheckoutNoticeService $notice_service;

	/**
	 * Order-owned currency context.
	 *
	 * @var OrderCurrencyContext
	 */
	private OrderCurrencyContext $order_context;

	/**
	 * Whether coordinator application is in progress.
	 *
	 * @var bool
	 */
	private bool $applying = false;

	/**
	 * Latest transition state for the current request.
	 *
	 * @var CheckoutTransitionState|null
	 */
	private ?CheckoutTransitionState $current_state = null;

	/**
	 * Binds the coordinator to its collaborators.
	 *
	 * @param CheckoutSettingsRepository        $settings_repository   Settings repository.
	 * @param CheckoutCurrencyPolicy            $policy                Pure policy.
	 * @param CheckoutEffectiveCurrencyProvider $effective_currency    Effective currency provider.
	 * @param GatewayCompatibility              $gateway_compatibility Gateway compatibility.
	 * @param CheckoutRecalculationService      $recalculation         Recalculation service.
	 * @param CheckoutTransitionStateRepository $transition_repository Transition repository.
	 * @param CheckoutNoticeService             $notice_service        Notice service.
	 * @param OrderCurrencyContext              $order_context         Order-owned context.
	 */
	public function __construct(
		CheckoutSettingsRepository $settings_repository,
		CheckoutCurrencyPolicy $policy,
		CheckoutEffectiveCurrencyProvider $effective_currency,
		GatewayCompatibility $gateway_compatibility,
		CheckoutRecalculationService $recalculation,
		CheckoutTransitionStateRepository $transition_repository,
		CheckoutNoticeService $notice_service,
		OrderCurrencyContext $order_context
	) {
		$this->settings_repository   = $settings_repository;
		$this->policy                = $policy;
		$this->effective_currency    = $effective_currency;
		$this->gateway_compatibility = $gateway_compatibility;
		$this->recalculation         = $recalculation;
		$this->transition_repository = $transition_repository;
		$this->notice_service        = $notice_service;
		$this->order_context         = $order_context;
	}

	/**
	 * Returns the latest transition state for the current request.
	 */
	public function current_state(): ?CheckoutTransitionState {
		return $this->current_state ?? $this->transition_repository->get();
	}

	/**
	 * Applies checkout policy for a surface.
	 *
	 * @param string $surface Checkout surface identifier.
	 * @param string $phase   Policy phase: presentation or settlement.
	 */
	public function apply( string $surface, string $phase = CheckoutPolicyPhase::PRESENTATION ): void {
		if ( $this->applying || ! $this->should_apply( $surface ) ) {
			return;
		}

		$this->applying = true;

		try {
			$this->run_policy( $surface, $phase );
		} finally {
			$this->applying = false;
			$this->gateway_compatibility->set_coordinator_active( false );
			$this->gateway_compatibility->set_filter_currency( null );
		}
	}

	/**
	 * Whether checkout policy should run for the surface.
	 *
	 * @param string $surface Checkout surface identifier.
	 */
	private function should_apply( string $surface ): bool {
		if ( $this->order_context->is_active() ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return false;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		unset( $surface );

		return true;
	}

	/**
	 * Runs checkout policy for one phase.
	 *
	 * @param string $surface Checkout surface identifier.
	 * @param string $phase   Policy phase.
	 */
	private function run_policy( string $surface, string $phase ): void {
		unset( $surface );

		$settings = $this->settings_repository->get();

		if ( $settings->is_settle_base_mode() && CheckoutPolicyPhase::SETTLEMENT === $phase ) {
			$this->run_settle_base_settlement( $settings );

			return;
		}

		if ( $settings->is_settle_base_mode() && CheckoutPolicyPhase::PRESENTATION === $phase ) {
			$this->run_settle_base_presentation( $settings );

			return;
		}

		$this->run_standard_policy( $settings );
	}

	/**
	 * Runs settle-base presentation: keep selected display, filter gateways by store currency.
	 *
	 * @param CheckoutSettings $settings Checkout settings.
	 */
	private function run_settle_base_presentation( CheckoutSettings $settings ): void {
		$shopper_currency = $this->effective_currency->shopper_currency();
		$store_currency   = $this->effective_currency->store_currency();
		$payment_required = WC()->cart->needs_payment();

		$this->gateway_compatibility->set_coordinator_active( true );
		$this->gateway_compatibility->set_filter_currency( $store_currency );
		$this->recalculation->begin_pass();
		$this->effective_currency->clear();

		$decision = $this->policy->decide_pass_one(
			$settings,
			$shopper_currency,
			$store_currency,
			$payment_required,
			false,
			$this->evaluate_gateways( $store_currency )
		);

		$state = new CheckoutTransitionState(
			$settings->mode(),
			$shopper_currency,
			$decision->effective_currency(),
			$decision->transition_reason(),
			false,
			false,
			$decision->settlement_currency()
		);

		$this->transition_repository->save( $state );
		$this->current_state = $state;
		$this->notice_service->render_classic_notice( $state, $settings );
	}

	/**
	 * Runs settle-base settlement: switch to store currency before order creation.
	 *
	 * @param CheckoutSettings $settings Checkout settings.
	 */
	private function run_settle_base_settlement( CheckoutSettings $settings ): void {
		$existing_state   = $this->transition_repository->get();
		$shopper_currency = $this->effective_currency->shopper_currency();
		$store_currency   = $this->effective_currency->store_currency();
		$reason           = null !== $existing_state && '' !== $existing_state->reason()
			? $existing_state->reason()
			: CheckoutTransitionState::REASON_SETTLE_BASE;

		$this->gateway_compatibility->set_coordinator_active( true );
		$this->gateway_compatibility->set_filter_currency( $store_currency );
		$this->recalculation->begin_pass();
		$this->effective_currency->apply( $store_currency );
		$this->recalculation->recalculate_if_needed( $shopper_currency, $store_currency );
		$this->evaluate_gateways( $store_currency );

		$state = new CheckoutTransitionState(
			$settings->mode(),
			$shopper_currency,
			$store_currency,
			$reason,
			false,
			false,
			$store_currency
		);

		$this->transition_repository->save( $state );
		$this->current_state = $state;
	}

	/**
	 * Runs the standard two-pass checkout policy flow for selected and store modes.
	 *
	 * @param CheckoutSettings $settings Checkout settings.
	 */
	private function run_standard_policy( CheckoutSettings $settings ): void {
		$existing_state = $this->transition_repository->get();

		if ( null !== $existing_state && $existing_state->fallback_attempted() ) {
			$this->reapply_settled_transition( $existing_state, $settings );

			return;
		}

		$shopper_currency   = $this->effective_currency->shopper_currency();
		$store_currency     = $this->effective_currency->store_currency();
		$payment_required   = WC()->cart->needs_payment();
		$fallback_attempted = false;

		$this->gateway_compatibility->set_coordinator_active( true );
		$this->recalculation->begin_pass();

		$pass_one_effective = $this->effective_currency->resolve_pass_one(
			$settings,
			$shopper_currency,
			$store_currency
		);
		$settlement_currency  = $this->effective_currency->resolve_settlement(
			$settings,
			$shopper_currency,
			$store_currency
		);

		$this->effective_currency->apply( $pass_one_effective );
		$this->recalculation->recalculate_if_needed( $shopper_currency, $pass_one_effective );
		$this->gateway_compatibility->set_filter_currency( $settlement_currency );

		$evaluation = $this->evaluate_gateways( $settlement_currency );
		$decision     = $this->policy->decide_pass_one(
			$settings,
			$shopper_currency,
			$store_currency,
			$payment_required,
			$fallback_attempted,
			$evaluation
		);

		$state = new CheckoutTransitionState(
			$settings->mode(),
			$shopper_currency,
			$decision->effective_currency(),
			$decision->transition_reason(),
			false,
			$fallback_attempted,
			$decision->settlement_currency()
		);

		if ( $decision->should_fallback() ) {
			$this->transition_repository->mark_fallback_attempted( $state );
			$this->recalculation->begin_pass();
			$this->effective_currency->apply( $store_currency );
			$this->recalculation->recalculate_if_needed( $pass_one_effective, $store_currency );
			$this->gateway_compatibility->set_filter_currency( $store_currency );
			$this->evaluate_gateways( $store_currency );

			$decision = $this->policy->decide_pass_two( $shopper_currency, $store_currency );
			$state    = new CheckoutTransitionState(
				$settings->mode(),
				$shopper_currency,
				$decision->effective_currency(),
				$decision->transition_reason(),
				$decision->fallback_occurred(),
				true,
				$decision->settlement_currency()
			);
		}

		$this->transition_repository->save( $state );
		$this->current_state = $state;
		$this->notice_service->render_classic_notice( $state, $settings );
	}

	/**
	 * Reapplies a settled fallback transition without rerunning pass one.
	 *
	 * @param CheckoutTransitionState $state    Persisted transition state.
	 * @param CheckoutSettings        $settings Checkout settings.
	 */
	private function reapply_settled_transition( CheckoutTransitionState $state, CheckoutSettings $settings ): void {
		$this->gateway_compatibility->set_coordinator_active( true );
		$this->gateway_compatibility->set_filter_currency( $state->settlement_currency() );
		$this->recalculation->begin_pass();
		$this->effective_currency->apply( $state->effective_currency() );
		$this->recalculation->recalculate_if_needed(
			$state->shopper_currency(),
			$state->effective_currency()
		);
		$this->evaluate_gateways( $state->settlement_currency() );
		$this->current_state = $state;
		$this->notice_service->render_classic_notice( $state, $settings );
	}

	/**
	 * Invokes WooCommerce gateway availability and returns UMC's evaluation.
	 *
	 * @param string $currency Currency code being evaluated.
	 */
	private function evaluate_gateways( string $currency ): \UMC\Integration\GatewayCurrencyEvaluation {
		unset( $currency );

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			WC()->payment_gateways()->get_available_payment_gateways();
		}

		$evaluation = $this->gateway_compatibility->get_request_evaluation();

		if ( null === $evaluation ) {
			return new \UMC\Integration\GatewayCurrencyEvaluation(
				$this->effective_currency->store_currency(),
				array(),
				array(),
				array(),
				array(),
				array(),
				0,
				false
			);
		}

		return $evaluation;
	}
}
