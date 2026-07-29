<?php
/**
 * Integration tests: gateway fallback causality scenarios.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Checkout;

use UMC\Checkout\CheckoutCurrencyPolicy;
use UMC\Checkout\CheckoutEffectiveCurrencyProvider;
use UMC\Checkout\CheckoutNoticeService;
use UMC\Checkout\CheckoutPolicyCoordinator;
use UMC\Checkout\CheckoutRecalculationService;
use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutSettingsRepository;
use UMC\Checkout\CheckoutSurface;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Integration\ClassicCheckoutPolicyAdapter;
use UMC\Integration\GatewayCompatibility;
use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencyContext;
use UMC\Order\OrderSnapshotReader;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\StoreApi\StoreApiCheckoutPolicyAdapter;
use WP_UnitTestCase;

/**
 * Scenarios 1–10 from the M11 plan using WooCommerce-authoritative gateway maps.
 */
final class GatewayFallbackCausalityTest extends WP_UnitTestCase {

	private const MANAGED_HOOKS = array(
		'woocommerce_available_payment_gateways',
		'umc_gateway_supported_currencies',
		'woocommerce_before_checkout_form',
		'woocommerce_checkout_update_order_review',
		'woocommerce_checkout_create_order',
		'rest_request_before_callbacks',
		'woocommerce_cart_loaded_from_session',
	);

	private GatewayCompatibility $gateway_compat;

	private CheckoutPolicyCoordinator $coordinator;

	private CheckoutTransitionStateRepository $transition_repository;

	private CurrencyContext $context;

	public function set_up(): void {
		parent::set_up();

		$this->enable_gateway( 'bacs' );
		$this->enable_gateway( 'cheque' );
		WC()->payment_gateways()->init();
	}

	public function tear_down(): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		delete_option( 'woocommerce_bacs_settings' );
		delete_option( 'woocommerce_cheque_settings' );

		parent::tear_down();
	}

	public function test_scenario_one_explicit_exclusion_allows_fallback(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->remove_gateway_before_umc( 'cheque' );

		$evaluation = $this->evaluate_gateways();

		$this->assertTrue( $evaluation->umcCausedEmpty() );
		$this->assertTrue( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_two_billing_country_exclusion_absent_from_pre_umc_set(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->remove_gateway_before_umc( 'bacs' );

		$evaluation = $this->evaluate_gateways();

		$this->assertSame( 1, $evaluation->beforeUmcCount() );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_three_shipping_exclusion_absent_from_pre_umc_set(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->remove_gateway_before_umc( 'cheque' );

		$evaluation = $this->evaluate_gateways();

		$this->assertSame( 1, $evaluation->beforeUmcCount() );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_four_unknown_support_retained_disallows_fallback(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$evaluation = $this->evaluate_gateways();

		$this->assertGreaterThan( 0, $evaluation->unknownSupportCount() );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_five_mixed_exclusion_and_unknown_disallows_fallback(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );

		$evaluation = $this->evaluate_gateways();

		$this->assertSame( array( 'bacs' ), $evaluation->removedForCurrencyGatewayIds() );
		$this->assertNotEmpty( $evaluation->unknownSupportGatewayIds() );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_six_all_pre_umc_gateways_explicitly_exclude_shopper_currency(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );

		$evaluation = $this->evaluate_gateways();

		$this->assertTrue( $evaluation->umcCausedEmpty() );
		$this->assertTrue( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_seven_downstream_filter_cannot_inflate_umc_causality(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		add_filter(
			'woocommerce_available_payment_gateways',
			static fn () => array(),
			15
		);

		$evaluation = $this->evaluate_gateways();
		$remaining  = WC()->payment_gateways()->get_available_payment_gateways();

		$this->assertSame( array(), $remaining );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_eight_no_enabled_gateways_disallows_fallback(): void {
		update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'no' ) );
		update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'no' ) );
		WC()->payment_gateways()->init();

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );

		$evaluation = $this->evaluate_gateways();

		$this->assertSame( 0, $evaluation->beforeUmcCount() );
		$this->assertFalse( $evaluation->umcCausedEmpty() );
		$this->assertFalse( $this->fallback_allowed( $evaluation ) );
	}

	public function test_scenario_nine_pass_two_store_currency_still_empty_does_not_loop(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'USD' ) );
		$this->restrict_gateway( 'cheque', array( 'USD' ) );
		$this->add_item_to_cart();

		$this->coordinator->apply( CheckoutSurface::CLASSIC_CHECKOUT );

		$state = $this->transition_repository->get();

		$this->assertNotNull( $state );
		$this->assertTrue( $state->fallback_attempted() );
		$this->assertTrue( $state->fallback_occurred() );
		$this->assertSame( 'EUR', $state->effective_currency() );
		$this->assertSame( CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED, $state->reason() );

		$this->coordinator->apply( CheckoutSurface::CLASSIC_CHECKOUT );

		$this->assertSame( $state->to_array(), $this->transition_repository->get()?->to_array() );
	}

	public function test_scenario_ten_store_api_matches_classic_fallback_decision(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'SEK' );
		$this->restrict_gateway( 'bacs', array( 'EUR' ) );
		$this->restrict_gateway( 'cheque', array( 'EUR' ) );
		$this->add_item_to_cart();

		$this->coordinator->apply( CheckoutSurface::CLASSIC_CHECKOUT );
		$classic = $this->transition_repository->get();

		$this->transition_repository->clear();

		$previous_uri = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
		$GLOBALS['wp']->query_vars['rest_route'] = '/wc/store/v1/checkout';

		try {
			$this->coordinator->apply( CheckoutSurface::STORE_API_CHECKOUT );
			$store_api = $this->transition_repository->get();
		} finally {
			if ( null === $previous_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $previous_uri;
			}
			unset( $GLOBALS['wp']->query_vars['rest_route'] );
		}

		$this->assertNotNull( $classic );
		$this->assertNotNull( $store_api );
		$this->assertSame( $classic->effective_currency(), $store_api->effective_currency() );
		$this->assertSame( $classic->reason(), $store_api->reason() );
		$this->assertSame( $classic->fallback_occurred(), $store_api->fallback_occurred() );
	}

	/**
	 * Boots currency, gateway compatibility, and checkout policy services.
	 *
	 * @param array<string, array<string, mixed>> $currencies Configured currencies.
	 * @param string                              $active     Active currency code.
	 */
	private function activate( array $currencies, string $active ): void {
		foreach ( self::MANAGED_HOOKS as $hook ) {
			remove_all_filters( $hook );
			remove_all_actions( $hook );
		}

		update_option( 'woocommerce_currency', 'EUR' );

		( new Settings() )->save(
			array(
				'currencies' => $currencies,
				'checkout'   => CheckoutSettings::default_array(),
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		$this->context               = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$this->gateway_compat        = new GatewayCompatibility( $this->context );
		$this->gateway_compat->register();
		$this->transition_repository = new CheckoutTransitionStateRepository();

		$checkout_settings = new CheckoutSettingsRepository( $settings );
		$notice_service    = new CheckoutNoticeService( $this->transition_repository );
		$effective         = new CheckoutEffectiveCurrencyProvider( $this->context );
		$recalculation     = new CheckoutRecalculationService( $this->context );
		$reader            = new OrderSnapshotReader();
		$resolver          = new HistoricalFormattingResolver( $registry );
		$order_context     = new OrderCurrencyContext( $reader, $resolver );

		$this->coordinator = new CheckoutPolicyCoordinator(
			$checkout_settings,
			new CheckoutCurrencyPolicy(),
			$effective,
			$this->gateway_compat,
			$recalculation,
			$this->transition_repository,
			$notice_service,
			$order_context
		);

		( new ClassicCheckoutPolicyAdapter( $this->coordinator, $this->context ) )->register();
		( new StoreApiCheckoutPolicyAdapter( $this->coordinator, $this->context ) )->register();

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
	}

	/**
	 * Invokes gateway availability and returns UMC's evaluation snapshot.
	 */
	private function evaluate_gateways(): \UMC\Integration\GatewayCurrencyEvaluation {
		WC()->payment_gateways()->get_available_payment_gateways();

		$evaluation = $this->gateway_compat->get_request_evaluation();

		$this->assertNotNull( $evaluation );

		return $evaluation;
	}

	/**
	 * Whether selected-mode fallback is eligible for an evaluation snapshot.
	 */
	private function fallback_allowed( \UMC\Integration\GatewayCurrencyEvaluation $evaluation ): bool {
		$decision = ( new CheckoutCurrencyPolicy() )->decide_pass_one(
			CheckoutSettings::from_array( CheckoutSettings::default_array() ),
			'SEK',
			'EUR',
			true,
			false,
			$evaluation
		);

		return $decision->should_fallback();
	}

	/**
	 * Restricts a gateway to a set of currency codes.
	 *
	 * @param string             $gateway_id Gateway id.
	 * @param array<int, string> $codes      Supported currency codes.
	 */
	private function restrict_gateway( string $gateway_id, array $codes ): void {
		add_filter(
			'umc_gateway_supported_currencies',
			static function ( $supported, $gateway ) use ( $gateway_id, $codes ) {
				return $gateway_id === $gateway->id ? $codes : $supported;
			},
			10,
			2
		);
	}

	/**
	 * Simulates WooCommerce removing a gateway before UMC's filter runs.
	 *
	 * @param string $gateway_id Gateway id to remove.
	 */
	private function remove_gateway_before_umc( string $gateway_id ): void {
		add_filter(
			'woocommerce_available_payment_gateways',
			static function ( $gateways ) use ( $gateway_id ) {
				if ( ! is_array( $gateways ) ) {
					return $gateways;
				}

				unset( $gateways[ $gateway_id ] );

				return $gateways;
			},
			9
		);
	}

	/**
	 * Enables one of WooCommerce's built-in offline gateways.
	 *
	 * @param string $gateway_id Gateway id.
	 */
	private function enable_gateway( string $gateway_id ): void {
		update_option(
			'woocommerce_' . $gateway_id . '_settings',
			array(
				'enabled' => 'yes',
				'title'   => strtoupper( $gateway_id ),
			)
		);
	}

	/**
	 * Puts a priced product in the cart so checkout policy can run.
	 */
	private function add_item_to_cart(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Product' );
		$product->set_regular_price( '100' );
		$product->set_status( 'publish' );
		$product->save();

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );
	}
}
