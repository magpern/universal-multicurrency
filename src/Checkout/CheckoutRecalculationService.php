<?php
/**
 * Checkout cart recalculation service.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

use UMC\Cart\CartRecalculation;
use UMC\CurrencyContext;

/**
 * Recalculates cart totals when checkout effective currency changes.
 */
final class CheckoutRecalculationService {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Whether recalculation already ran in the current pass.
	 *
	 * @var bool
	 */
	private bool $recalculated_this_pass = false;

	/**
	 * Binds the service to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Resets the per-pass recalculation guard.
	 */
	public function begin_pass(): void {
		$this->recalculated_this_pass = false;
	}

	/**
	 * Recalculates totals when the effective currency changed for this pass.
	 *
	 * @param string $previous_effective Previous effective currency code.
	 * @param string $new_effective    New effective currency code.
	 */
	public function recalculate_if_needed( string $previous_effective, string $new_effective ): void {
		if ( $this->recalculated_this_pass || $previous_effective === $new_effective ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return;
		}

		WC()->session->set( CartRecalculation::SESSION_KEY, null );
		WC()->cart->calculate_totals();
		WC()->session->set( CartRecalculation::SESSION_KEY, $this->context->get_currency_signature() );
		$this->recalculated_this_pass = true;
	}
}
