<?php
/**
 * Integration tests for currency switcher shortcodes.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Display;

use UMC\Currency;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Display\AutomaticRenderRegistry;
use UMC\Display\StorefrontRequestContext;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Covers shortcode registration and rendering behaviour.
 */
final class SwitcherShortcodeTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		SwitcherViewModelFactory::reset_instance_counter();
		parent::tear_down();
	}

	public function test_both_shortcode_tags_are_registered(): void {
		$this->boot_display();

		$this->assertTrue( shortcode_exists( SwitcherShortcode::TAG_PRIMARY ) );
		$this->assertTrue( shortcode_exists( SwitcherShortcode::TAG_LEGACY ) );
	}

	public function test_shortcode_returns_empty_when_disabled(): void {
		$this->boot_display();

		$this->assertSame( '', do_shortcode( '[' . SwitcherShortcode::TAG_PRIMARY . ']' ) );
	}

	public function test_shortcode_renders_markup_when_enabled(): void {
		$this->boot_display(
			array(
				'enabled' => true,
			)
		);

		$html = do_shortcode( '[' . SwitcherShortcode::TAG_PRIMARY . ']' );

		$this->assertStringContainsString( 'umc-switcher', $html );
		$this->assertStringContainsString( 'umc-switcher__trigger', $html );
	}

	public function test_multiple_shortcode_instances_receive_unique_ids(): void {
		$this->boot_display(
			array(
				'enabled' => true,
				'style'   => SwitcherSettings::STYLE_DROPDOWN,
			)
		);

		$html = do_shortcode(
			'[' . SwitcherShortcode::TAG_PRIMARY . '][' . SwitcherShortcode::TAG_LEGACY . ']'
		);

		$this->assertSame( 2, substr_count( $html, 'umc-switcher-trigger-' ) );
		$this->assertSame( 1, substr_count( $html, 'umc-switcher-trigger-1' ) );
		$this->assertSame( 1, substr_count( $html, 'umc-switcher-trigger-2' ) );
	}

	/**
	 * @param array<string, mixed> $display Display overrides.
	 */
	private function boot_display( array $display = array() ): void {
		update_option( 'woocommerce_currency', 'EUR' );

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
						$display
					),
				)
			)
		);

		$settings = new Settings();
		$base     = new Currency( 'EUR', 2 );
		$registry = new CurrencyRegistry( $settings, $base );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$repo     = new SwitcherSettingsRepository( $settings );
		$factory  = new SwitcherViewModelFactory( $context, new WooCommerceCurrencyProvider(), $repo );
		$renderer = new SwitcherRenderer();
		$assets   = new SwitcherAssets( new StorefrontRequestContext(), $repo, $context );

		( new SwitcherShortcode( $factory, $renderer, $assets ) )->register();
	}
}
