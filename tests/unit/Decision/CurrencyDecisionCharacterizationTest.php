<?php
/**
 * Characterization tests locking currency-decision behaviour for M15.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Decision;

use PHPUnit\Framework\TestCase;
use UMC\Checkout\CheckoutCurrencyPolicy;
use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Integration\GatewayCurrencyEvaluation;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
require_once dirname( __DIR__ ) . '/Doubles/WooCommerceSwitcherDoubles.php';

/**
 * Locks pre-refactor runtime outcomes used by explainability work.
 *
 * @covers \UMC\CurrencyResolver
 * @covers \UMC\Geo\GeoCurrencyDecisionService
 * @covers \UMC\CurrencyContext
 * @covers \UMC\CurrencySwitcher
 * @covers \UMC\Checkout\CheckoutCurrencyPolicy
 */
final class CurrencyDecisionCharacterizationTest extends TestCase {

	/**
	 * @var array<string, string>
	 */
	private array $cookies = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $session = array();

	protected function setUp(): void {
		parent::setUp();

		$this->cookies                                 = array();
		$this->session                                 = array();
		$GLOBALS['umc_currency_switcher_test_cookies'] =& $this->cookies;
		$GLOBALS['umc_currency_switcher_test_session'] =& $this->session;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['umc_currency_switcher_test_cookies'], $GLOBALS['umc_currency_switcher_test_session'], $_GET[ CurrencyContext::QUERY_VAR ] );
		parent::tearDown();
	}

	public function test_resolver_explicit_session_cookie_base_ladder(): void {
		$resolver = new CurrencyResolver();
		$base     = 'EUR';
		$allowed  = array( 'SEK', 'USD' );

		$this->assertSame( 'USD', $resolver->resolve( 'USD', 'SEK', 'JPY', $base, $allowed ) );
		$this->assertSame( 'SEK', $resolver->resolve( null, 'SEK', 'USD', $base, $allowed ) );
		$this->assertSame( 'USD', $resolver->resolve( null, null, 'USD', $base, $allowed ) );
		$this->assertSame( 'EUR', $resolver->resolve( null, null, null, $base, $allowed ) );
		$this->assertSame( 'SEK', $resolver->resolve( 'GBP', null, 'SEK', $base, $allowed ) );
		$this->assertSame( 'EUR', $resolver->resolve( 'GBP', 'NOK', 'CHF', $base, $allowed ) );
		$this->assertSame( 'SEK', $resolver->resolve( ' sek ', null, null, $base, $allowed ) );
		$this->assertSame( 'EUR', $resolver->resolve( 'EUR', 'SEK', null, $base, $allowed ) );
	}

	public function test_geo_simulate_and_resolver_agree_on_shared_shopper_final_currency(): void {
		$resolver = new CurrencyResolver();
		$service  = $this->geo_service(
			array(
				'enabled' => true,
				'rules'   => array(
					array(
						'id'       => 'rule_00000001',
						'type'     => 'country',
						'value'    => 'SE',
						'currency' => 'SEK',
					),
				),
			)
		);

		$cases = array(
			array(
				'explicit' => 'USD',
				'session'  => 'SEK',
				'cookie'   => 'SEK',
			),
			array(
				'explicit' => '',
				'session'  => 'USD',
				'cookie'   => 'SEK',
			),
			array(
				'explicit' => '',
				'session'  => '',
				'cookie'   => 'USD',
			),
			array(
				'explicit' => 'XXX',
				'session'  => 'YYY',
				'cookie'   => 'USD',
			),
		);

		foreach ( $cases as $case ) {
			$selectable = array( 'SEK', 'USD' );
			$resolved   = $resolver->resolve(
				'' !== $case['explicit'] ? $case['explicit'] : null,
				'' !== $case['session'] ? $case['session'] : null,
				'' !== $case['cookie'] ? $case['cookie'] : null,
				'EUR',
				$selectable
			);

			$simulated = $service->simulate(
				array(
					'country_code'      => 'SE',
					'selectable'        => $selectable,
					'base_currency'     => 'EUR',
					'explicit_currency' => $case['explicit'],
					'session_currency'  => $case['session'],
					'cookie_currency'   => $case['cookie'],
				)
			);

			$this->assertSame(
				$resolved,
				$simulated['final_currency'],
				'Shared shopper inputs must agree on final currency before consolidation.'
			);
		}
	}

	/**
	 * Documents a known skip-reason quirk: base as explicit selects shopper currency
	 * but may label skip reason as shopper_currency rather than explicit_currency when
	 * base is absent from the selectable list. Consolidation must preserve this or
	 * leave GeoCurrencyDecisionService untouched.
	 */
	public function test_geo_skip_reason_when_explicit_is_base_not_listed_in_selectable(): void {
		$service = $this->geo_service(
			array(
				'enabled' => true,
				'rules'   => array(
					array(
						'id'       => 'rule_00000001',
						'type'     => 'country',
						'value'    => 'SE',
						'currency' => 'SEK',
					),
				),
			)
		);

		$result = $service->simulate(
			array(
				'country_code'      => 'SE',
				'selectable'        => array( 'SEK' ),
				'base_currency'     => 'EUR',
				'explicit_currency' => 'EUR',
			)
		);

		$this->assertTrue( $result['geo_skipped'] );
		$this->assertSame( 'EUR', $result['final_currency'] );
		$this->assertSame( 'shopper_currency', $result['geo_skip_reason'] );

		$resolver = new CurrencyResolver();
		$this->assertSame( 'EUR', $resolver->resolve( 'EUR', null, null, 'EUR', array( 'SEK' ) ) );
	}

	public function test_context_effective_override_wins_over_shopper_ladder_for_active_only(): void {
		$context = $this->context();

		$_GET[ CurrencyContext::QUERY_VAR ] = 'SEK';
		$this->assertSame( 'SEK', $context->get_shopper_code() );
		$this->assertSame( 'SEK', $context->get_active_code() );

		$context->set_effective_override( 'EUR' );
		$this->assertSame( 'SEK', $context->get_shopper_code() );
		$this->assertSame( 'EUR', $context->get_active_code() );

		$context->clear_effective_override();
		$this->assertSame( 'SEK', $context->get_active_code() );
	}

	public function test_manual_persist_sets_manual_flag_non_manual_does_not(): void {
		$switcher = $this->switcher( true );

		$switcher->persist( 'SEK', false );
		$this->assertSame( 'SEK', $this->session[ CurrencyContext::SESSION_KEY ] );
		$this->assertArrayNotHasKey( CurrencySwitcher::SESSION_MANUAL_SELECTION, $this->session );

		$switcher->persist( 'USD', true );
		$this->assertSame( 'USD', $this->session[ CurrencyContext::SESSION_KEY ] );
		$this->assertSame( '1', $this->session[ CurrencySwitcher::SESSION_MANUAL_SELECTION ] );
	}

	public function test_checkout_store_mode_and_unsupported_fallback_reasons(): void {
		$policy = new CheckoutCurrencyPolicy();

		$store = $policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_STORE, true ),
			'SEK',
			'EUR',
			true,
			false,
			new GatewayCurrencyEvaluation(
				'SEK',
				array( 'bacs' ),
				array(),
				array( 'bacs' ),
				array(),
				array(),
				2,
				true
			)
		);
		$this->assertSame( 'EUR', $store->effective_currency() );
		$this->assertSame( CheckoutTransitionState::REASON_STORE_CURRENCY, $store->transition_reason() );

		$pass_two = $policy->decide_pass_two( 'SEK', 'EUR' );
		$this->assertSame( 'EUR', $pass_two->effective_currency() );
		$this->assertTrue( $pass_two->fallback_occurred() );
		$this->assertSame( CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED, $pass_two->transition_reason() );
	}

	/**
	 * @param array<string, mixed> $geo Geo settings subtree.
	 */
	private function geo_service( array $geo ): GeoCurrencyDecisionService {
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => $geo,
			)
		);

		return new GeoCurrencyDecisionService( new GeoDetectionSettingsRepository( $settings ) );
	}

	private function context(): CurrencyContext {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled' => true,
						'rate'    => '11.50',
					),
					'USD' => array(
						'enabled' => true,
						'rate'    => '1.10',
					),
				),
			)
		);
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );

		return new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );
	}

	private function switcher( bool $remember ): CurrencySwitcher {
		$settings = new Settings(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'manual_rate' => '11.50',
						),
						'USD' => array(
							'manual_rate' => '1.10',
						),
					),
					'display'    => array_merge(
						SwitcherSettings::default_array(),
						array(
							'behavior' => array(
								'remember_selection' => $remember,
								'active_first'       => true,
							),
						)
					),
				)
			)
		);

		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new CurrencySwitcher( $context, new SwitcherSettingsRepository( $settings ) );
	}
}
