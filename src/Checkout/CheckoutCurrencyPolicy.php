<?php
/**
 * Pure checkout currency policy.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

use UMC\Integration\GatewayCurrencyEvaluation;

/**
 * Decides checkout effective currency and fallback eligibility.
 *
 * WordPress-free. Never calls WooCommerce or inspects gateway hooks directly.
 */
final class CheckoutCurrencyPolicy {

	/**
	 * Evaluates pass one for the configured checkout mode.
	 *
	 * @param CheckoutSettings          $settings            Checkout settings.
	 * @param string                    $shopper_currency    Shopper currency code.
	 * @param string                    $store_currency      Store currency code.
	 * @param bool                      $payment_required    Whether payment is required.
	 * @param bool                      $fallback_attempted  Whether fallback was already attempted.
	 * @param GatewayCurrencyEvaluation $evaluation          Gateway evaluation snapshot.
	 */
	public function decide_pass_one(
		CheckoutSettings $settings,
		string $shopper_currency,
		string $store_currency,
		bool $payment_required,
		bool $fallback_attempted,
		GatewayCurrencyEvaluation $evaluation
	): CheckoutCurrencyDecision {
		$shopper = strtoupper( $shopper_currency );
		$store   = strtoupper( $store_currency );

		if ( $settings->is_store_mode() ) {
			$reason = ( $shopper !== $store )
				? CheckoutTransitionState::REASON_STORE_CURRENCY
				: '';

			return new CheckoutCurrencyDecision( $store, $reason );
		}

		$should_fallback = $this->is_fallback_eligible(
			$settings,
			$shopper,
			$store,
			$payment_required,
			$fallback_attempted,
			$evaluation
		);

		return new CheckoutCurrencyDecision( $shopper, '', $should_fallback );
	}

	/**
	 * Evaluates pass two after a currency-caused gateway fallback.
	 *
	 * @param string $shopper_currency Shopper currency code.
	 * @param string $store_currency   Store currency code.
	 */
	public function decide_pass_two(
		string $shopper_currency,
		string $store_currency
	): CheckoutCurrencyDecision {
		return new CheckoutCurrencyDecision(
			strtoupper( $store_currency ),
			CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED,
			false,
			true
		);
	}

	/**
	 * Whether selected-mode fallback to store currency is strictly eligible.
	 *
	 * @param CheckoutSettings          $settings            Checkout settings.
	 * @param string                    $shopper_currency    Shopper currency code.
	 * @param string                    $store_currency      Store currency code.
	 * @param bool                      $payment_required    Whether payment is required.
	 * @param bool                      $fallback_attempted  Whether fallback was already attempted.
	 * @param GatewayCurrencyEvaluation $evaluation          Gateway evaluation snapshot.
	 */
	private function is_fallback_eligible(
		CheckoutSettings $settings,
		string $shopper_currency,
		string $store_currency,
		bool $payment_required,
		bool $fallback_attempted,
		GatewayCurrencyEvaluation $evaluation
	): bool {
		if ( ! $settings->is_selected_mode() ) {
			return false;
		}

		if ( ! $payment_required ) {
			return false;
		}

		if ( $shopper_currency === $store_currency ) {
			return false;
		}

		if ( $fallback_attempted ) {
			return false;
		}

		if ( $evaluation->beforeUmcCount() <= 0 ) {
			return false;
		}

		if ( $evaluation->unknownSupportCount() > 0 ) {
			return false;
		}

		if ( $evaluation->retainedCount() > 0 ) {
			return false;
		}

		if ( $evaluation->removedForCurrencyCount() !== $evaluation->beforeUmcCount() ) {
			return false;
		}

		if ( $evaluation->afterUmcCount() !== 0 ) {
			return false;
		}

		return $evaluation->umcCausedEmpty();
	}
}
