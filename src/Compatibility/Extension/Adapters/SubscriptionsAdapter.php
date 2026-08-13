<?php
/**
 * WooCommerce Subscriptions compatibility adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

use UMC\Compatibility\Extension\ExtensionCompatibilityContext;

/**
 * Isolates subscription renewal monetary context from browsing currency (E2).
 *
 * At E2 evidence tier this adapter suppresses browsing-currency product-price
 * conversion while renewal context is active. It does not select renewal FX
 * rates, enter OrderCurrencyContext, or rewrite WCS subscription amounts — those
 * semantics require E3 real-extension validation.
 *
 * @see docs/adr/0024-third-party-extension-compatibility-contract.md
 */
final class SubscriptionsAdapter extends AbstractExtensionAdapter {

	/**
	 * {@inheritDoc}
	 */
	public function extension_id(): string {
		return 'woocommerce_subscriptions';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'umc_should_convert_product_price', array( $this, 'filter_should_convert' ), 10, 1 );

		// UMC-owned E2 test-double seam only. Real WCS renewal hook timing is not
		// registered until E3 validates the authoritative before/during hook contract.
		add_action( 'umc_test_extension_subscriptions_renewal_start', array( $this, 'enter_renewal_from_test_double' ), 10, 1 );
		add_action( 'umc_test_extension_subscriptions_renewal_end', array( $this, 'exit_renewal_context' ), 10, 0 );
	}

	/**
	 * Suppresses browsing-currency conversion during renewal-owned amounts.
	 *
	 * @param bool $should Whether to convert product prices.
	 */
	public function filter_should_convert( bool $should ): bool {
		if ( ExtensionCompatibilityContext::should_suppress_browsing_conversion() ) {
			return false;
		}

		return $should;
	}

	/**
	 * Enters renewal context from the UMC test-double action.
	 *
	 * @param mixed $subscription Subscription object or currency code from test double.
	 */
	public function enter_renewal_from_test_double( $subscription ): void {
		$currency = is_string( $subscription ) ? strtoupper( $subscription ) : '';
		if ( '' === $currency && is_object( $subscription ) && method_exists( $subscription, 'get_currency' ) ) {
			$currency = strtoupper( (string) $subscription->get_currency() );
		}

		if ( '' !== $currency ) {
			ExtensionCompatibilityContext::enter_renewal_context( $currency );
		}
	}

	/**
	 * Clears renewal isolation context.
	 */
	public function exit_renewal_context(): void {
		ExtensionCompatibilityContext::exit_renewal_context();
	}
}
