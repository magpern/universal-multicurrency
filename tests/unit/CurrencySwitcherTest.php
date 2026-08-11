<?php
/**
 * Unit tests for currency switch persistence policy.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Exercises remember-selection cookie deletion without a full redirect flow.
 */
final class CurrencySwitcherTest extends TestCase {

	/**
	 * @var array<string, string>
	 */
	private array $cookies = array();

	/**
	 * @var array<string, string>
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
		unset( $GLOBALS['umc_currency_switcher_test_cookies'], $GLOBALS['umc_currency_switcher_test_session'] );
		parent::tearDown();
	}

	public function test_persist_writes_cookie_when_remember_selection_enabled(): void {
		$switcher = $this->switcher( true );

		$switcher->persist( 'SEK' );

		$this->assertSame( 'SEK', $this->session[ CurrencyContext::SESSION_KEY ] );
		$this->assertSame( 'SEK', $this->cookies[ CurrencyContext::COOKIE_NAME ] );
	}

	public function test_persist_deletes_existing_cookie_when_remember_selection_disabled(): void {
		$this->cookies[ CurrencyContext::COOKIE_NAME ] = 'SEK';

		$switcher = $this->switcher( false );
		$switcher->persist( 'SEK' );

		$this->assertSame( 'SEK', $this->session[ CurrencyContext::SESSION_KEY ] );
		$this->assertSame( '', $this->cookies[ CurrencyContext::COOKIE_NAME ] );
	}

	public function test_manual_persist_sets_customer_origin(): void {
		$switcher = $this->switcher( true );
		$switcher->persist( 'SEK', true );

		$this->assertSame( CurrencySwitcher::ORIGIN_CUSTOMER, $this->session[ CurrencySwitcher::SESSION_CURRENCY_ORIGIN ] );
		$this->assertSame( '1', $this->session[ CurrencySwitcher::SESSION_MANUAL_SELECTION ] );
	}

	public function test_non_manual_persist_sets_visitor_location_origin(): void {
		$switcher = $this->switcher( true );
		$switcher->persist( 'SEK', false );

		$this->assertSame( CurrencySwitcher::ORIGIN_VISITOR_LOCATION, $this->session[ CurrencySwitcher::SESSION_CURRENCY_ORIGIN ] );
		$this->assertArrayNotHasKey( CurrencySwitcher::SESSION_MANUAL_SELECTION, $this->session );
	}

	public function test_origin_overwrite_follows_latest_persist_without_changing_resolver_outcome(): void {
		$switcher = $this->switcher( true );
		$resolver = new CurrencyResolver();

		$switcher->persist( 'SEK', false );
		$this->assertSame( CurrencySwitcher::ORIGIN_VISITOR_LOCATION, CurrencySwitcher::read_currency_origin() );

		$switcher->persist( 'USD', true );
		$this->assertSame( CurrencySwitcher::ORIGIN_CUSTOMER, CurrencySwitcher::read_currency_origin() );

		$resolved = $resolver->resolve( null, $this->session[ CurrencyContext::SESSION_KEY ], null, 'EUR', array( 'SEK', 'USD' ) );
		$this->assertSame( 'USD', $resolved );

		$this->session[ CurrencySwitcher::SESSION_CURRENCY_ORIGIN ] = CurrencySwitcher::ORIGIN_VISITOR_LOCATION;
		$resolved_with_stale_origin                                 = $resolver->resolve( null, $this->session[ CurrencyContext::SESSION_KEY ], null, 'EUR', array( 'SEK', 'USD' ) );
		$this->assertSame( 'USD', $resolved_with_stale_origin );
	}

	public function test_clear_currency_origin_removes_metadata_only(): void {
		$switcher = $this->switcher( true );
		$switcher->persist( 'SEK', true );
		$switcher->clear_currency_origin();

		$this->assertSame( 'SEK', $this->session[ CurrencyContext::SESSION_KEY ] );
		$this->assertNull( $this->session[ CurrencySwitcher::SESSION_CURRENCY_ORIGIN ] ?? null );
		$this->assertNull( CurrencySwitcher::read_currency_origin() );
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
