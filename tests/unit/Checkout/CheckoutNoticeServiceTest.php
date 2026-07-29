<?php
/**
 * Unit tests for checkout transition notice service.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Checkout;

use PHPUnit\Framework\TestCase;
use UMC\Checkout\CheckoutNoticeService;
use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Checkout\CheckoutTransitionStateRepository;

/**
 * Signature format, messages, and show=false payload cases.
 */
final class CheckoutNoticeServiceTest extends TestCase {

	/**
	 * Notice service under test.
	 *
	 * @var CheckoutNoticeService
	 */
	private CheckoutNoticeService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new CheckoutNoticeService( new CheckoutTransitionStateRepository() );
	}

	public function test_notice_signature_format(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_STORE,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_STORE_CURRENCY
		);

		$this->assertSame(
			'store|SEK|EUR|EUR|store_currency_at_checkout',
			$state->notice_signature()
		);
	}

	public function test_build_message_for_store_currency_transition(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_STORE,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_STORE_CURRENCY
		);

		$message = $this->service->build_message( $state );

		$this->assertStringContainsString( 'EUR', $message );
		$this->assertStringContainsString( 'SEK', $message );
		$this->assertStringContainsString( 'browsing the store', strtolower( $message ) );
	}

	public function test_build_message_for_settle_base_transition(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_SETTLE_BASE,
			'SEK',
			'SEK',
			CheckoutTransitionState::REASON_SETTLE_BASE,
			false,
			false,
			'EUR'
		);

		$message = $this->service->build_message( $state );

		$this->assertStringContainsString( 'SEK', $message );
		$this->assertStringContainsString( 'EUR', $message );
		$this->assertStringContainsString( 'processed in', strtolower( $message ) );
	}

	public function test_build_message_for_unsupported_selected_currency(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_SELECTED,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED,
			true
		);

		$message = $this->service->build_message( $state );

		$this->assertStringContainsString( 'SEK', $message );
		$this->assertStringContainsString( 'EUR', $message );
		$this->assertStringContainsString( 'No payment method is available', $message );
	}

	public function test_build_payload_returns_show_false_when_state_is_null(): void {
		$this->assertSame(
			array( 'show' => false ),
			$this->service->build_payload( null, new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ) )
		);
	}

	public function test_build_payload_returns_show_false_when_notices_disabled(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_STORE,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_STORE_CURRENCY
		);

		$this->assertSame(
			array( 'show' => false ),
			$this->service->build_payload( $state, new CheckoutSettings( CheckoutSettings::MODE_STORE, false ) )
		);
	}

	public function test_build_payload_returns_show_false_when_no_transition(): void {
		$state = new CheckoutTransitionState(
			CheckoutSettings::MODE_SELECTED,
			'EUR',
			'EUR',
			''
		);

		$this->assertSame(
			array( 'show' => false ),
			$this->service->build_payload( $state, new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ) )
		);
	}

	public function test_build_payload_returns_full_notice_when_transition_applies(): void {
		$state    = new CheckoutTransitionState(
			CheckoutSettings::MODE_STORE,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_STORE_CURRENCY
		);
		$settings = new CheckoutSettings( CheckoutSettings::MODE_STORE, true );

		$payload = $this->service->build_payload( $state, $settings );

		$this->assertTrue( $payload['show'] );
		$this->assertSame( 'info', $payload['status'] );
		$this->assertSame( $state->notice_signature(), $payload['signature'] );
		$this->assertSame( $this->service->build_message( $state ), $payload['message'] );
	}

	public function test_signature_changes_when_transition_reason_changes(): void {
		$store_mode = new CheckoutTransitionState(
			CheckoutSettings::MODE_STORE,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_STORE_CURRENCY
		);
		$fallback   = new CheckoutTransitionState(
			CheckoutSettings::MODE_SELECTED,
			'SEK',
			'EUR',
			CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED,
			true
		);

		$this->assertNotSame( $store_mode->notice_signature(), $fallback->notice_signature() );
	}
}
