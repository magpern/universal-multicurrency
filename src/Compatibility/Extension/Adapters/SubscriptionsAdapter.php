<?php
/**
 * WooCommerce Subscriptions compatibility adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

use UMC\Compatibility\Extension\ExtensionCompatibilityContext;
use UMC\CurrencyContext;
use UMC\Integration\PriceConversionService;
use UMC\Order\OrderCurrencyContext;

/**
 * Isolates subscription renewal monetary context from browsing currency.
 *
 * Initial subscription purchases use normal conversion. Renewal generation
 * enters subscription/order currency context so browsing currency, Visitor
 * Location, and session state cannot alter renewal totals.
 *
 * @see docs/adr/0024-third-party-extension-compatibility-contract.md
 */
final class SubscriptionsAdapter extends AbstractExtensionAdapter {

	/**
	 * Historical order context (reserved for future renewal-order entry).
	 *
	 * @var OrderCurrencyContext|null
	 */
	private ?OrderCurrencyContext $order_context;

	/**
	 * Creates the Subscriptions adapter.
	 *
	 * @param PriceConversionService    $service       Conversion seam.
	 * @param CurrencyContext           $context       Request-scoped currency.
	 * @param OrderCurrencyContext|null $order_context Historical order context.
	 */
	public function __construct(
		PriceConversionService $service,
		CurrencyContext $context,
		?OrderCurrencyContext $order_context = null
	) {
		parent::__construct( $service, $context );
		$this->order_context = $order_context;
	}

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

		// UMC-owned test-double seam (E2 evidence).
		add_action( 'umc_test_extension_subscriptions_renewal_start', array( $this, 'enter_renewal_from_test_double' ), 10, 1 );
		add_action( 'umc_test_extension_subscriptions_renewal_end', array( $this, 'exit_renewal_context' ), 10, 0 );

		// Documented WCS hooks — registered only when WCS symbols exist.
		if ( function_exists( 'wcs_get_subscription' ) ) {
			add_action( 'woocommerce_before_subscriptions_create_renewal_order', array( $this, 'before_renewal_order' ), 10, 1 );
			add_action( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'after_renewal_order' ), 10, 2 );
		}
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
			$this->enter_renewal_context( $currency );
		}
	}

	/**
	 * Enters renewal context before WCS creates a renewal order.
	 *
	 * @param mixed $subscription WCS subscription object.
	 */
	public function before_renewal_order( $subscription ): void {
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_currency' ) ) {
			return;
		}

		$this->enter_renewal_context( (string) $subscription->get_currency() );
	}

	/**
	 * Exits renewal context after WCS creates a renewal order.
	 *
	 * @param mixed $renewal_order Renewal order object.
	 * @param mixed $subscription  Subscription object.
	 */
	public function after_renewal_order( $renewal_order, $subscription ): void {
		$this->exit_renewal_context();
	}

	/**
	 * Clears renewal isolation context.
	 */
	public function exit_renewal_context(): void {
		ExtensionCompatibilityContext::exit_renewal_context();
	}

	/**
	 * Enters renewal isolation context using subscription currency and snapshot when available.
	 *
	 * @param string $currency Subscription currency code.
	 */
	private function enter_renewal_context( string $currency ): void {
		$currency = strtoupper( $currency );
		if ( '' === $currency ) {
			return;
		}

		ExtensionCompatibilityContext::enter_renewal_context( $currency );
	}
}
