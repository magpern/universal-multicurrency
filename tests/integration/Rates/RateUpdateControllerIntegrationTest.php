<?php
/**
 * Integration tests for the manual rate-update request path.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Rates;

use UMC\Admin\RateUpdateController;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\Http\HttpResponse;
use UMC\Rates\Providers\FrankfurterRateSource;
use UMC\Rates\RateUpdateState;
use UMC\Rates\RateUpdateService;
use UMC\Settings;
use UMC\Tests\Support\FakeHttpTransport;
use UMC\Tests\Support\PerformanceMetrics;
use UMC\Tests\Support\RedirectCapturedException;
use WP_UnitTestCase;
use WPDieException;

/**
 * Drives `admin_post_umc_update_rates` end to end: the registered production
 * action reaches the real {@see RateUpdateService} and the real
 * {@see ExchangeRateStore} persistence boundary, with provider HTTP faked.
 */
final class RateUpdateControllerIntegrationTest extends WP_UnitTestCase {

	use PerformanceMetrics;

	private const ACTION = 'admin_post_umc_update_rates';

	private const NONCE = 'umc_update_rates';

	private const URL = 'https://api.frankfurter.dev/v1/latest?base=EUR&symbols=SEK';

	/**
	 * Provider transport for the current test.
	 *
	 * @var FakeHttpTransport|null
	 */
	private ?FakeHttpTransport $transport = null;

	protected function setUp(): void {
		parent::setUp();

		$this->transport = new FakeHttpTransport();

		add_filter(
			'wp_redirect',
			static function ( $location ): string {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow; the message is never rendered.
				throw new RedirectCapturedException( (string) $location );
			}
		);
	}

	protected function tearDown(): void {
		$this->stop_umc_settings_option_metrics();

		remove_all_filters( 'wp_redirect' );
		remove_all_actions( self::ACTION );

		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_GET['scope'], $_GET['code'] );

		delete_option( Settings::OPTION );
		delete_option( RateUpdateState::OPTION );

		parent::tearDown();
	}

	public function test_authorized_successful_update_persists_provider_rate_and_state(): void {
		$this->seed_settings( '10.00', 1_600_000_000 );
		$this->authorize();
		$this->transport->register(
			self::URL,
			new HttpResponse(
				200,
				array( 'etag' => 'W/"rate-etag"' ),
				'{"amount":1,"base":"EUR","date":"2026-07-24","rates":{"SEK":11.5}}'
			)
		);

		$store    = $this->boot_controller();
		$redirect = $this->dispatch();

		$this->assertSame( 1, $this->transport->request_count() );
		$this->assertSame( 'Exchange rates updated successfully.', $redirect->query_arg( 'umc_msg' ) );
		$this->assertSame( 'success', $redirect->query_arg( 'umc_typ' ) );

		$persisted = get_option( Settings::OPTION );
		$this->assertSame( '11.5', $persisted['currencies']['SEK']['provider_rate'] );
		$this->assertGreaterThan( 1_600_000_000, (int) $persisted['currencies']['SEK']['rate_updated_at'] );

		$status = $store->get_operational_status( 'SEK' );
		$this->assertSame( RateUpdateState::STATUS_SUCCESS, $status->last_status() );
		$this->assertSame( 0, $status->consecutive_failures() );
		$this->assertSame( 'W/"rate-etag"', $store->get_last_provider_metadata()?->etag() );
	}

	public function test_not_modified_update_reports_success_without_writing_settings(): void {
		$this->seed_settings( '10.00', 1_600_000_000 );
		$this->authorize();
		$this->transport->register( self::URL, new HttpResponse( 304, array(), '' ) );

		$store            = $this->boot_controller();
		$before_effective = ( new Settings() )->get_rate( 'SEK' );

		$this->start_umc_settings_option_metrics();
		$redirect = $this->dispatch();

		$this->assertSame(
			0,
			$this->umc_settings_option_write_count,
			'A not-modified rate update must not write umc_settings.'
		);
		$this->assertSame( 'Rates are already up to date.', $redirect->query_arg( 'umc_msg' ) );

		$persisted = get_option( Settings::OPTION );
		$this->assertSame( '10.00', $persisted['currencies']['SEK']['provider_rate'] );
		$this->assertSame( 1_600_000_000, (int) $persisted['currencies']['SEK']['rate_updated_at'] );
		$this->assertSame( $before_effective, ( new Settings() )->get_rate( 'SEK' ) );

		$this->assertSame( RateUpdateState::STATUS_SUCCESS, $store->get_operational_status( 'SEK' )->last_status() );
	}

	public function test_provider_failure_is_reported_and_preserves_existing_rates(): void {
		$this->seed_settings( '10.00', 1_600_000_000 );
		$this->authorize();
		$this->transport->register( self::URL, new HttpResponse( 503, array(), '' ) );

		$store    = $this->boot_controller();
		$redirect = $this->dispatch();

		$this->assertSame( 'Rate update failed. Last known rates were preserved.', $redirect->query_arg( 'umc_msg' ) );

		$persisted = get_option( Settings::OPTION );
		$this->assertSame( '10.00', $persisted['currencies']['SEK']['provider_rate'] );
		$this->assertSame( 1_600_000_000, (int) $persisted['currencies']['SEK']['rate_updated_at'] );

		$status = $store->get_operational_status( 'SEK' );
		$this->assertSame( RateUpdateState::STATUS_FAILED, $status->last_status() );
		$this->assertSame( 1, $status->consecutive_failures() );
		$this->assertSame( 'provider_unavailable', $status->last_error() );
	}

	public function test_unauthorized_request_cannot_trigger_an_update(): void {
		$this->seed_settings( '10.00', 1_600_000_000 );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->sign_request();

		$store = $this->boot_controller();
		$this->transport->register(
			self::URL,
			new HttpResponse( 200, array(), '{"base":"EUR","date":"2026-07-24","rates":{"SEK":99.9}}' )
		);

		$this->start_umc_settings_option_metrics();

		try {
			do_action( self::ACTION );
			$this->fail( 'Unauthorized rate update must terminate the request.' );
		} catch ( WPDieException $exception ) {
			$this->assertStringContainsString( 'permission', $exception->getMessage() );
		}

		$this->assertSame( 0, $this->transport->request_count(), 'No provider call may be made.' );
		$this->assertSame( 0, $this->umc_settings_option_write_count );
		$this->assertSame( '10.00', get_option( Settings::OPTION )['currencies']['SEK']['provider_rate'] );
		$this->assertSame( RateUpdateState::STATUS_NEVER, $store->get_operational_status( 'SEK' )->last_status() );
	}

	public function test_missing_nonce_cannot_trigger_an_update(): void {
		$this->seed_settings( '10.00', 1_600_000_000 );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET['scope'] = 'all';

		$this->boot_controller();
		$this->transport->register(
			self::URL,
			new HttpResponse( 200, array(), '{"base":"EUR","date":"2026-07-24","rates":{"SEK":99.9}}' )
		);

		try {
			do_action( self::ACTION );
			$this->fail( 'A rate update without a valid nonce must terminate the request.' );
		} catch ( WPDieException $exception ) {
			unset( $exception );
		}

		$this->assertSame( 0, $this->transport->request_count() );
		$this->assertSame( '10.00', get_option( Settings::OPTION )['currencies']['SEK']['provider_rate'] );
	}

	/**
	 * Registers the production controller over real rate services.
	 */
	private function boot_controller(): ExchangeRateStore {
		$store = new ExchangeRateStore( new Settings(), new RateUpdateState(), 'EUR', 'test-lock' );

		( new RateUpdateController(
			new RateUpdateService( new FrankfurterRateSource( $this->transport ), $store, 'EUR' )
		) )->register();

		return $store;
	}

	/**
	 * Fires the registered admin-post action and captures the redirect.
	 */
	private function dispatch(): RedirectCapturedException {
		try {
			do_action( self::ACTION );
		} catch ( RedirectCapturedException $redirect ) {
			return $redirect;
		}

		$this->fail( 'The controller must redirect after handling a rate update.' );
	}

	/**
	 * Persists one automatic SEK currency to `umc_settings`.
	 *
	 * @param string $provider_rate Existing provider rate.
	 * @param int    $updated_at    Existing provider-rate timestamp.
	 */
	private function seed_settings( string $provider_rate, int $updated_at ): void {
		( new Settings() )->save(
			array(
				'rate_mode'  => Settings::RATE_MODE_AUTOMATIC,
				'currencies' => array(
					'SEK' => array(
						'enabled'         => true,
						'rate_mode'       => Settings::RATE_MODE_AUTOMATIC,
						'provider_rate'   => $provider_rate,
						'rate_updated_at' => $updated_at,
					),
				),
			)
		);
	}

	/**
	 * Signs in a user permitted to refresh rates and signs the request.
	 */
	private function authorize(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue(
			current_user_can( 'manage_woocommerce' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			'Administrators must hold manage_woocommerce for this fixture to be meaningful.'
		);

		$this->sign_request();
	}

	/**
	 * Populates the request superglobals the controller reads.
	 */
	private function sign_request(): void {
		$nonce = wp_create_nonce( self::NONCE );

		$_REQUEST['_wpnonce'] = $nonce;
		$_GET['_wpnonce']     = $nonce;
		$_GET['scope']        = 'all';
	}
}
