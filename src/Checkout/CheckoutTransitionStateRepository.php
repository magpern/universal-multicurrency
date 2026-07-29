<?php
/**
 * Checkout transition session repository.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Persists checkout transition state in the WooCommerce session.
 */
final class CheckoutTransitionStateRepository {

	public const SESSION_KEY = 'umc_checkout_transition';

	public const SESSION_NOTICE_KEY = 'umc_checkout_notice_signature';

	/**
	 * Returns the current transition state, if any.
	 */
	public function get(): ?CheckoutTransitionState {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}

		return CheckoutTransitionState::from_array( WC()->session->get( self::SESSION_KEY ) );
	}

	/**
	 * Persists transition state for the checkout session.
	 *
	 * @param CheckoutTransitionState $state Transition state to persist.
	 */
	public function save( CheckoutTransitionState $state ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_KEY, $state->to_array() );
	}

	/**
	 * Whether fallback has already been attempted this checkout session.
	 */
	public function fallback_attempted(): bool {
		$state = $this->get();

		return null !== $state && $state->fallback_attempted();
	}

	/**
	 * Marks fallback as attempted before a second policy pass.
	 *
	 * @param CheckoutTransitionState $state Current transition state.
	 */
	public function mark_fallback_attempted( CheckoutTransitionState $state ): void {
		$this->save(
			new CheckoutTransitionState(
				$state->mode(),
				$state->shopper_currency(),
				$state->effective_currency(),
				$state->reason(),
				$state->fallback_occurred(),
				true,
				$state->settlement_currency()
			)
		);
	}

	/**
	 * Returns the last classic notice signature rendered.
	 */
	public function last_notice_signature(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		$value = WC()->session->get( self::SESSION_NOTICE_KEY );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Records the last classic notice signature rendered.
	 *
	 * @param string $signature Notice signature.
	 */
	public function remember_notice_signature( string $signature ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_NOTICE_KEY, $signature );
	}

	/**
	 * Clears checkout transition state.
	 */
	public function clear(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::SESSION_KEY, null );
		WC()->session->set( self::SESSION_NOTICE_KEY, null );
	}
}
