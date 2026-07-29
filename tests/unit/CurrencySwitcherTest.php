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
