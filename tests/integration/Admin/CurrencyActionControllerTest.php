<?php
/**
 * Integration tests for currency admin actions.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\CurrencyActionController;
use UMC\Currency;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Settings;
use UMC\Tests\Support\RedirectCapturedException;
use WP_UnitTestCase;

/**
 * Verifies add/remove/toggle currency admin-post flows.
 */
final class CurrencyActionControllerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );

		add_filter(
			'wp_redirect',
			static function ( $location ): string {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow.
				throw new RedirectCapturedException( (string) $location );
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_redirect' );
		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'], $_GET['code'], $_GET['state'] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_is_addable_rejects_base_and_duplicate_codes(): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'SEK' => array(
							'manual_rate' => '11.5',
						),
					),
				)
			)
		);

		$controller = $this->controller();

		$this->assertFalse( $controller->is_addable( 'EUR' ) );
		$this->assertFalse( $controller->is_addable( 'SEK' ) );
		$this->assertTrue( $controller->is_addable( 'USD' ) );
	}

	public function test_handle_add_persists_defaults_from_woocommerce_metadata(): void {
		$this->authorize();
		$_GET['code']         = 'USD';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'umc_currency_add' );

		try {
			$this->controller()->handle_add();
			$this->fail( 'Expected redirect.' );
		} catch ( RedirectCapturedException $redirect ) {
			$this->assertSame( 'Currency added.', $redirect->query_arg( 'umc_msg' ) );
			$this->assertSame( 'USD', $redirect->query_arg( 'umc_edit' ) );
		}

		$config = ( new Settings() )->get_currency_config( 'USD' );

		$this->assertNotNull( $config );
		$this->assertTrue( $config['enabled'] );
		$this->assertNotSame( '', $config['symbol'] );
	}

	public function test_handle_remove_deletes_configured_currency(): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'USD' => array(
							'manual_rate' => '1.10',
						),
					),
				)
			)
		);

		$this->authorize();
		$_GET['code']         = 'USD';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'umc_currency_remove' );

		try {
			$this->controller()->handle_remove();
			$this->fail( 'Expected redirect.' );
		} catch ( RedirectCapturedException $redirect ) {
			$this->assertSame( 'Currency removed.', $redirect->query_arg( 'umc_msg' ) );
		}

		$this->assertNull( ( new Settings() )->get_currency_config( 'USD' ) );
	}

	public function test_handle_toggle_updates_enabled_flag(): void {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => array(
						'USD' => array(
							'enabled'     => true,
							'manual_rate' => '1.10',
						),
					),
				)
			)
		);

		$this->authorize();
		$_GET['code']         = 'USD';
		$_GET['state']        = '0';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'umc_currency_toggle' );

		try {
			$this->controller()->handle_toggle();
			$this->fail( 'Expected redirect.' );
		} catch ( RedirectCapturedException $redirect ) {
			$this->assertSame( 'Currency disabled.', $redirect->query_arg( 'umc_msg' ) );
		}

		$config = ( new Settings() )->get_currency_config( 'USD' );
		$this->assertNotNull( $config );
		$this->assertFalse( $config['enabled'] );
	}

	private function authorize(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' => 'shop_manager',
				)
			)
		);
	}

	private function controller(): CurrencyActionController {
		return new CurrencyActionController(
			new Settings(),
			new Currency( 'EUR', 2 ),
			new WooCommerceCurrencyProvider()
		);
	}
}
