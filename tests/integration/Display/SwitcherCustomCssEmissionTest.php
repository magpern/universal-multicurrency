<?php
/**
 * Integration tests for storefront Custom CSS emission.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Display;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherPresentationCss;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\StorefrontRequestContext;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Covers the single permitted Custom CSS emission path (ADR-0022).
 */
final class SwitcherCustomCssEmissionTest extends WP_UnitTestCase {

	private const CSS = '.umc-switcher { letter-spacing: 0.02em; }';

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );
	}

	public function tear_down(): void {
		wp_dequeue_style( SwitcherAssets::STYLE_HANDLE );
		wp_deregister_style( SwitcherAssets::STYLE_HANDLE );
		wp_deregister_script( SwitcherAssets::SCRIPT_HANDLE );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	public function test_storefront_enqueue_attaches_custom_css_after_the_stylesheet(): void {
		$this->assets( self::CSS )->ensure_enqueued();

		$inline = $this->inline_styles();

		$this->assertCount( 1, $inline );
		$this->assertStringContainsString( SwitcherPresentationCss::CUSTOM_CSS_BANNER, $inline[0] );
		$this->assertStringContainsString( self::CSS, $inline[0] );
	}

	public function test_custom_css_is_attached_once_per_request(): void {
		$assets = $this->assets( self::CSS );

		$assets->ensure_enqueued();
		$assets->ensure_enqueued();
		$assets->maybe_attach_custom_css();

		$this->assertCount( 1, $this->inline_styles() );
	}

	public function test_no_inline_style_is_attached_without_custom_css(): void {
		$this->assets( '' )->ensure_enqueued();

		$this->assertTrue( wp_style_is( SwitcherAssets::STYLE_HANDLE, 'enqueued' ) );
		$this->assertSame( array(), $this->inline_styles() );
	}

	public function test_stored_css_rejected_by_the_denylist_is_not_emitted(): void {
		$this->assets( '.umc-switcher { background: url(https://evil.test/x.png); }' )->ensure_enqueued();

		$this->assertSame( array(), $this->inline_styles() );
	}

	public function test_custom_css_is_never_attached_in_admin(): void {
		set_current_screen( 'dashboard' );

		$assets = $this->assets( self::CSS );

		$assets->ensure_enqueued();
		$assets->maybe_attach_custom_css();

		$this->assertSame( array(), $this->inline_styles() );

		set_current_screen( 'front' );
	}

	/**
	 * Inline style payloads attached to the switcher stylesheet.
	 *
	 * @return array<int, string>
	 */
	private function inline_styles(): array {
		$attached = wp_styles()->get_data( SwitcherAssets::STYLE_HANDLE, 'after' );

		return is_array( $attached ) ? array_values( array_map( 'strval', $attached ) ) : array();
	}

	/**
	 * Builds asset services with the given stored Custom CSS.
	 *
	 * @param string $custom_css Stored Custom CSS.
	 */
	private function assets( string $custom_css ): SwitcherAssets {
		( new Settings() )->save(
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
							'enabled'    => true,
							'custom_css' => $custom_css,
						)
					),
				)
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new SwitcherAssets(
			new StorefrontRequestContext(),
			new SwitcherSettingsRepository( $settings ),
			$context
		);
	}
}
