<?php
/**
 * Checkout transition notice service.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Builds and renders checkout transition notices for classic and Blocks surfaces.
 */
final class CheckoutNoticeService {

	/**
	 * Transition state repository.
	 *
	 * @var CheckoutTransitionStateRepository
	 */
	private CheckoutTransitionStateRepository $state_repository;

	/**
	 * Binds the notice service to transition state storage.
	 *
	 * @param CheckoutTransitionStateRepository $state_repository Transition repository.
	 */
	public function __construct( CheckoutTransitionStateRepository $state_repository ) {
		$this->state_repository = $state_repository;
	}

	/**
	 * Builds the structured notice payload for Store API extension data.
	 *
	 * @param CheckoutTransitionState|null $state    Current transition state.
	 * @param CheckoutSettings             $settings Checkout settings.
	 * @return array<string, mixed>
	 */
	public function build_payload( ?CheckoutTransitionState $state, CheckoutSettings $settings ): array {
		if ( null === $state || ! $settings->show_notice() || ! $state->has_transition() ) {
			return array(
				'show' => false,
			);
		}

		return array(
			'show'      => true,
			'status'    => 'info',
			'signature' => $state->notice_signature(),
			'message'   => $this->build_message( $state ),
		);
	}

	/**
	 * Renders a classic checkout notice when appropriate.
	 *
	 * @param CheckoutTransitionState $state    Current transition state.
	 * @param CheckoutSettings        $settings Checkout settings.
	 */
	public function render_classic_notice( CheckoutTransitionState $state, CheckoutSettings $settings ): void {
		if ( ! $settings->show_notice() || ! $state->has_transition() ) {
			return;
		}

		if ( ! function_exists( 'wc_add_notice' ) || ( function_exists( 'WC' ) && WC()->is_rest_api_request() ) ) {
			return;
		}

		$signature = $state->notice_signature();

		if ( $this->state_repository->last_notice_signature() === $signature ) {
			return;
		}

		$message = $this->build_message( $state );

		if ( wc_has_notice( $message, 'notice' ) ) {
			$this->state_repository->remember_notice_signature( $signature );
			return;
		}

		wc_add_notice( $message, 'notice' );
		$this->state_repository->remember_notice_signature( $signature );
	}

	/**
	 * Builds the translated notice message for a transition.
	 *
	 * @param CheckoutTransitionState $state Current transition state.
	 */
	public function build_message( CheckoutTransitionState $state ): string {
		$shopper    = $state->shopper_currency();
		$effective    = $state->effective_currency();
		$settlement = $state->settlement_currency();

		if ( CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED === $state->reason() ) {
			return sprintf(
				/* translators: 1: shopper currency code, 2: effective checkout currency code. */
				esc_html__( 'No payment method is available in %1$s. Checkout continues in %2$s.', 'universal-multicurrency' ),
				esc_html( $shopper ),
				esc_html( $effective )
			);
		}

		if ( CheckoutTransitionState::REASON_SETTLE_BASE === $state->reason() ) {
			return sprintf(
				/* translators: 1: displayed shopper currency code, 2: settlement store currency code. */
				esc_html__( 'Checkout shows prices in %1$s. Payment and your order will be processed in %2$s.', 'universal-multicurrency' ),
				esc_html( $shopper ),
				esc_html( $settlement )
			);
		}

		return sprintf(
			/* translators: 1: effective checkout currency code, 2: shopper currency code. */
			esc_html__( 'Checkout continues in %1$s. Your selected currency (%2$s) is still used when browsing the store.', 'universal-multicurrency' ),
			esc_html( $effective ),
			esc_html( $shopper )
		);
	}
}
