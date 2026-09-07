<?php
/**
 * Integration tests for authenticated preferred-currency storage and state.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\User;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\User\PreferredCurrency;
use WP_UnitTestCase;

/**
 * Covers authorization, normalization, clearing, and invalid fallback.
 */
final class PreferredCurrencyTest extends WP_UnitTestCase {

	/**
	 * Preference service under test.
	 *
	 * @var PreferredCurrency
	 */
	private PreferredCurrency $preference;

	public function set_up(): void {
		parent::set_up();

		$settings         = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'enabled'     => true,
						'manual_rate' => '11.50',
					),
				),
			)
		);
		$registry         = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$this->preference = new PreferredCurrency( $registry, new ManualRateProvider( $settings, 'EUR' ) );
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_self_can_set_normalized_code_and_clear_meta(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$this->assertTrue( $this->preference->set( $user_id, ' sek ' ) );
		$this->assertSame( 'SEK', get_user_meta( $user_id, PreferredCurrency::META_KEY, true ) );
		$this->assertSame( 'SEK', $this->preference->get( $user_id ) );

		$this->assertTrue( $this->preference->set( $user_id, null ) );
		$this->assertSame( '', get_user_meta( $user_id, PreferredCurrency::META_KEY, true ) );
	}

	public function test_invalid_stored_value_is_retained_and_falls_back_to_base(): void {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, PreferredCurrency::META_KEY, 'GBP' );

		$state = $this->preference->get_state( $user_id );

		$this->assertIsArray( $state );
		$this->assertSame( 'GBP', $state['stored'] );
		$this->assertSame( 'EUR', $state['effective'] );
		$this->assertSame( PreferredCurrency::SOURCE_INVALID_FALLBACK, $state['source'] );
		$this->assertSame( 'GBP', get_user_meta( $user_id, PreferredCurrency::META_KEY, true ) );
	}

	public function test_unauthorized_viewer_cannot_read_stored_value(): void {
		$owner_id  = self::factory()->user->create();
		$viewer_id = self::factory()->user->create();
		update_user_meta( $owner_id, PreferredCurrency::META_KEY, 'SEK' );
		wp_set_current_user( $viewer_id );

		$state = $this->preference->get_state( $owner_id );

		$this->assertNull( $this->preference->get( $owner_id ) );
		$this->assertIsArray( $state );
		$this->assertNull( $state['stored'] );
		$this->assertFalse( $state['editable'] );
	}
}
