<?php
/**
 * Behavioural security tests for request handling and admin mutations.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictNotice;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Finding;
use UMC\Diagnostics\NoticeDismissal;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Exercises negative authorization and input-validation paths with real WordPress.
 */
final class SecurityBehaviourTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	private const CURRENCIES = array( 'SEK' => array( 'rate' => '11.50' ) );

	/**
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	protected function setUp(): void {
		parent::setUp();

		if ( null === WC()->session ) {
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		$this->active_plugins_backup = get_option( 'active_plugins', array() );
		$_SERVER['REQUEST_URI']      = '/shop/';

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detector' ) );
	}

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		unset( $_GET[ CurrencyContext::QUERY_VAR ], $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * @param array<string, array<string, mixed>> $manifest Built-in manifest from the registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_fixture_detector( array $manifest ): array {
		$manifest['fixture-a'] = array(
			'label'      => 'Fixture Switcher A',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_A,
				),
			),
		);

		return $manifest;
	}

	private function activate( ?string $active = null ): void {
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_price_num_decimals', 2 );

		( new Settings() )->save( array( 'currencies' => self::CURRENCIES ) );

		$settings      = new Settings();
		$registry      = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates         = new ManualRateProvider( $settings, 'EUR' );
		$this->context = new CurrencyContext( $registry, $rates, new CurrencyResolver() );

		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );

		if ( null !== $active ) {
			$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;
		}
	}

	public function test_poisoned_cookie_is_ignored_when_not_selectable(): void {
		$this->activate( '<script>alert(1)</script>' );

		$this->assertSame( 'EUR', $this->context->get_active_code() );
	}

	public function test_poisoned_session_is_ignored_when_not_selectable(): void {
		$this->activate();
		WC()->session->set( CurrencyContext::SESSION_KEY, 'DROP TABLE' );

		$this->assertSame( 'EUR', $this->context->get_active_code() );
	}

	public function test_malformed_query_currency_falls_back_to_base(): void {
		$this->activate();
		$_GET[ CurrencyContext::QUERY_VAR ] = 'not-a-code';

		$this->assertSame( 'EUR', $this->context->get_active_code() );
	}

	public function test_dismissal_with_wrong_fingerprint_does_not_persist(): void {
		require_once WP_PLUGIN_DIR . '/' . self::FIXTURE_A;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = self::FIXTURE_A;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$detector    = new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() );
		$dismissal   = new NoticeDismissal( $detector );
		$fingerprint = $detector->fingerprint();
		$wrong       = str_repeat( 'a', 16 );

		$this->assertNotSame( $fingerprint, $wrong );

		$_GET[ NoticeDismissal::QUERY_ARG ] = $wrong;
		$_GET['_wpnonce']                   = wp_create_nonce( 'umc_dismiss_' . $wrong );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Harness simulates a signed dismiss request.
		$_REQUEST = array_merge( $_REQUEST, $_GET );

		$this->assertNull( $dismissal->try_dismiss_from_request() );
		$this->assertSame( '', get_user_meta( $user_id, NoticeDismissal::META_KEY, true ) );
	}

	public function test_conflict_notice_settings_url_filter_cannot_open_external_redirect(): void {
		add_filter(
			'umc_conflict_notice_view_model',
			static function ( array $view ): array {
				$view['settings_url'] = 'https://example.com/phish';

				return $view;
			}
		);

		$notice = new ConflictNotice(
			new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() )
		);

		$view = $notice->view_model(
			array(
				new Finding(
					'fixture-a',
					'Fixture Switcher A',
					80,
					Confidence::HIGH,
					array()
				),
			),
			'dashboard',
			false
		);

		$this->assertIsArray( $view );

		$method = new ReflectionMethod( ConflictNotice::class, 'settings_admin_url' );
		$method->setAccessible( true );

		$url = (string) $method->invoke( $notice, $view );

		$this->assertStringContainsString( 'page=wc-settings', $url );
		$this->assertStringNotContainsString( 'example.com', $url );
	}
}
