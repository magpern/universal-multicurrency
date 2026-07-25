<?php

/**
 * Cart recalculation on currency / rate change.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Cart;

use UMC\CurrencyContext;

/**
 * Keeps the cart's cached totals in step with the active currency and rate.
 *
 * The cart itself stores product references, never prices, so every
 * `calculate_totals()` recomputes line totals from base prices in the active
 * currency — a converted value is never reused as conversion input. What can go
 * stale is the *cached* totals WooCommerce persists in the session after a
 * calculation: after a currency switch (or an admin rate edit) they still hold
 * the previous currency until something forces a recalculation.
 *
 * This service stamps the cart session with the current rate identity and, when
 * it no longer matches, forces one recalculation so mini-cart and fragment
 * output cannot show a stale-currency total. Because it keys on the rate
 * identity (code + rate), a changed rate for the same code also triggers it.
 */
final class CartRecalculation {

	/**
	 * Cart-session key holding the rate identity the totals were last computed for.
	 */
	public const SESSION_KEY = 'umc_cart_signature';

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the service to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the recalculation check.
	 *
	 * Runs once the cart has loaded from the session, which is after the switch
	 * handler has persisted any new selection, so the active currency is settled.
	 */
	public function register(): void {
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_recalculate' ), 20 );
	}

	/**
	 * Recalculates cart totals when the active rate identity has changed.
	 *
	 * A no-op on non-frontend requests and when the identity is unchanged. Runs
	 * even when the base currency becomes active again, so switching *back* to
	 * base clears a previously converted total.
	 */
	public function maybe_recalculate(): void {
		if ( ! $this->context->is_convertible_request() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		$current = $this->context->get_currency_signature();
		$stored  = WC()->session->get( self::SESSION_KEY );

		if ( is_string( $stored ) && $stored === $current ) {
			return;
		}

		WC()->session->set( self::SESSION_KEY, $current );
		WC()->cart->calculate_totals();

		/**
		 * Fires after the cart is recalculated because the currency or rate changed.
		 *
		 * Lets integrations refresh their own currency-specific caches or fragments.
		 *
		 * @since 0.3.0
		 *
		 * @param string      $current New rate identity the totals were computed for.
		 * @param string|null $stored  Previous rate identity, or null on first load.
		 */
		do_action( 'umc_cart_recalculated', $current, is_string( $stored ) ? $stored : null );
	}
}
