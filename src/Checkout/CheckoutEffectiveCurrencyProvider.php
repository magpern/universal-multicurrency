<?php
/**
 * Checkout effective currency provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

use UMC\CurrencyContext;

/**
 * Applies checkout effective-currency overrides without changing shopper preference.
 */
final class CheckoutEffectiveCurrencyProvider {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the provider to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Resolves the pass-one effective currency from checkout settings.
	 *
	 * @param CheckoutSettings $settings         Checkout settings.
	 * @param string           $shopper_currency Shopper currency code.
	 * @param string           $store_currency   Store currency code.
	 */
	public function resolve_pass_one( CheckoutSettings $settings, string $shopper_currency, string $store_currency ): string {
		if ( $settings->is_settle_base_mode() || $settings->is_selected_mode() ) {
			return strtoupper( $shopper_currency );
		}

		return strtoupper( $store_currency );
	}

	/**
	 * Resolves the settlement currency from checkout settings.
	 *
	 * @param CheckoutSettings $settings         Checkout settings.
	 * @param string           $shopper_currency Shopper currency code.
	 * @param string           $store_currency   Store currency code.
	 */
	public function resolve_settlement( CheckoutSettings $settings, string $shopper_currency, string $store_currency ): string {
		unset( $shopper_currency );

		if ( $settings->is_settle_base_mode() || $settings->is_store_mode() ) {
			return strtoupper( $store_currency );
		}

		return strtoupper( $shopper_currency );
	}

	/**
	 * Applies an effective checkout currency override.
	 *
	 * @param string $currency_code Effective checkout currency code.
	 */
	public function apply( string $currency_code ): void {
		$this->context->set_effective_override( $currency_code );
	}

	/**
	 * Clears any checkout effective-currency override.
	 */
	public function clear(): void {
		$this->context->clear_effective_override();
	}

	/**
	 * Returns the store/base currency code.
	 */
	public function store_currency(): string {
		return $this->context->get_base_currency()->code();
	}

	/**
	 * Returns the shopper-selected currency code.
	 */
	public function shopper_currency(): string {
		return $this->context->get_shopper_code();
	}
}
