<?php
/**
 * Payment-gateway currency compatibility.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Hides payment gateways that do not support a given transaction currency.
 *
 * WooCommerce core gateways accept any store currency, so nothing is hidden by
 * default. A gateway's supported-currency list is declared through the
 * `umc_gateway_supported_currencies` filter (return `null` for "all currencies",
 * or an array of codes). When the currency is not supported the gateway is
 * removed *before* order placement, so the customer is never silently charged in
 * a currency the gateway cannot process. If that leaves no gateway available, an
 * explanatory checkout notice is shown.
 *
 * Classification is delegated to {@see GatewayCurrencyClassifier}. The filter
 * callback stores an immutable {@see GatewayCurrencyEvaluation} for the current
 * request so checkout policy can reason about currency-caused gateway loss
 * without reimplementing WooCommerce availability.
 */
final class GatewayCompatibility {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Pure gateway currency classifier.
	 *
	 * @var GatewayCurrencyClassifier
	 */
	private GatewayCurrencyClassifier $classifier;

	/**
	 * Request-scoped evaluation from the latest filter invocation.
	 *
	 * @var GatewayCurrencyEvaluation|null
	 */
	private ?GatewayCurrencyEvaluation $request_evaluation = null;

	/**
	 * Whether checkout policy owns gateway notices for this request.
	 *
	 * @var bool
	 */
	private bool $coordinator_active = false;

	/**
	 * Binds the service to the context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context    = $context;
		$this->classifier = new GatewayCurrencyClassifier();
	}

	/**
	 * Registers the gateway-availability filter.
	 */
	public function register(): void {
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 10, 1 );
	}

	/**
	 * Marks whether checkout policy owns gateway notices for this request.
	 *
	 * @param bool $active Whether the checkout coordinator is active.
	 */
	public function set_coordinator_active( bool $active ): void {
		$this->coordinator_active = $active;
	}

	/**
	 * Returns the evaluation produced by the latest UMC filter invocation.
	 */
	public function get_request_evaluation(): ?GatewayCurrencyEvaluation {
		return $this->request_evaluation;
	}

	/**
	 * Storefront callback: removes gateways incompatible with the session currency.
	 *
	 * @param mixed $gateways Available gateways keyed by id.
	 * @return mixed
	 */
	public function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || ! $this->context->is_convertible_request() ) {
			return $gateways;
		}

		return $this->filter_gateways_for_currency( $gateways, $this->context->get_active_code() );
	}

	/**
	 * Filters gateways for a specific currency code.
	 *
	 * @param array<string, object> $gateways Available gateways keyed by id.
	 * @param string                $currency Currency code to filter against.
	 * @return array<string, object> Filtered gateways.
	 */
	public function filter_gateways_for_currency( array $gateways, string $currency ): array {
		if ( array() === $gateways ) {
			$this->request_evaluation = new GatewayCurrencyEvaluation(
				strtoupper( $currency ),
				array(),
				array(),
				array(),
				array(),
				array(),
				$this->enabled_gateway_count(),
				false
			);

			return $gateways;
		}

		$result = $this->classifier->apply(
			$gateways,
			$currency,
			array( $this, 'resolve_supported_currencies' ),
			$this->enabled_gateway_count()
		);

		$this->request_evaluation = $result->evaluation();
		$filtered                 = $result->filtered_gateways();

		if ( $result->evaluation()->umc_caused_empty() && ! $this->coordinator_active ) {
			$this->notify_no_gateway( strtoupper( $currency ) );
		}

		foreach ( $result->evaluation()->removed_for_currency_gateway_ids() as $id ) {
			/**
			 * Fires when a gateway is hidden because it does not support the
			 * active currency.
			 *
			 * @since 0.3.0
			 *
			 * @param string $id       Gateway id.
			 * @param string $currency Currency code.
			 */
			do_action( 'umc_gateway_hidden', $id, strtoupper( $currency ) );
		}

		return $filtered;
	}

	/**
	 * The uppercase currency codes a gateway supports, or null for "all".
	 *
	 * @param object $gateway Gateway instance.
	 * @return array<int, string>|null
	 */
	public function resolve_supported_currencies( $gateway ): ?array {
		/**
		 * The currencies a gateway supports. Return null for "all currencies"
		 * (the default) or an array of currency codes to restrict it.
		 *
		 * @since 0.3.0
		 *
		 * @param array<int, string>|null $codes   Supported codes, or null for all.
		 * @param object                  $gateway Gateway instance.
		 */
		$codes = apply_filters( 'umc_gateway_supported_currencies', null, $gateway );

		if ( ! is_array( $codes ) ) {
			return null;
		}

		return array_map( 'strtoupper', array_map( 'strval', $codes ) );
	}

	/**
	 * Counts enabled gateways configured in the store.
	 */
	private function enabled_gateway_count(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return 0;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! is_array( $gateways ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $gateways as $gateway ) {
			if ( is_object( $gateway ) && isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Adds a single explanatory notice when no gateway is available.
	 *
	 * @param string $active Active currency code.
	 */
	private function notify_no_gateway( string $active ): void {
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC()->is_rest_api_request() ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: active currency code. */
			esc_html__( 'No payment method is available for %s. Please choose a different currency to continue.', 'universal-multicurrency' ),
			esc_html( $active )
		);

		if ( ! wc_has_notice( $message, 'error' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
