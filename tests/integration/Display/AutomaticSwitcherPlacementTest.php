<?php
/**
 * Integration tests for automatic switcher placement.
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
use UMC\Display\AutomaticSwitcherPlacement;
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
 * Covers automatic footer placement and coexistence with shortcodes.
 */
final class AutomaticSwitcherPlacementTest extends WP_UnitTestCase {

	/**
	 * @var AutomaticRenderRegistry
	 */
	private AutomaticRenderRegistry $registry;

	public function set_up(): void {
		parent::set_up();

		$this->registry = new AutomaticRenderRegistry();
		update_option( 'woocommerce_currency', 'EUR' );
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		SwitcherViewModelFactory::reset_instance_counter();
		parent::tear_down();
	}

	public function test_floating_side_renders_once_in_footer(): void {
		$output = $this->capture_footer(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$this->assertSame( 1, substr_count( $output, 'umc-switcher--floating-side' ) );
	}

	public function test_manual_placement_does_not_auto_render(): void {
		$output = $this->capture_footer(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_MANUAL,
			)
		);

		$this->assertSame( '', trim( $output ) );
	}

	public function test_automatic_output_coexists_with_shortcode_output(): void {
		$services = $this->services(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_STICKY_FOOTER,
			)
		);

		$shortcode = do_shortcode( '[' . SwitcherShortcode::TAG_PRIMARY . ']' );

		ob_start();
		$services['automatic']->maybe_render();
		$services['automatic']->maybe_render();
		$footer = (string) ob_get_clean();

		$this->assertStringContainsString( 'umc-switcher', $shortcode );
		$this->assertSame( 1, substr_count( $footer, 'umc-switcher--floating-bottom' ) );
	}

	public function test_automatic_render_skipped_in_admin_context(): void {
		$this->set_admin_screen( true );

		$output = $this->capture_footer(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$this->assertSame( '', trim( $output ) );
	}

	/**
	 * @param array<string, mixed> $display Display overrides.
	 */
	private function capture_footer( array $display ): string {
		$services = $this->services( $display );

		ob_start();
		$services['automatic']->maybe_render();
		$services['automatic']->maybe_render();

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $display Display overrides.
	 * @return array{automatic: AutomaticSwitcherPlacement, shortcode: SwitcherShortcode}
	 */
	private function services( array $display ): array {
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

		$settings  = new Settings();
		$base      = new Currency( 'EUR', 2 );
		$registry  = new CurrencyRegistry( $settings, $base );
		$rates     = new ManualRateProvider( $settings, 'EUR' );
		$context   = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$repo      = new SwitcherSettingsRepository( $settings );
		$factory   = new SwitcherViewModelFactory( $context, new WooCommerceCurrencyProvider(), $repo );
		$renderer  = new SwitcherRenderer();
		$assets    = new SwitcherAssets( new StorefrontRequestContext(), $repo, $context );
		$automatic = new AutomaticSwitcherPlacement(
			$repo,
			$factory,
			$renderer,
			$assets,
			new StorefrontRequestContext(),
			$this->registry,
			$context
		);
		$shortcode = new SwitcherShortcode( $factory, $renderer, $assets );
		$shortcode->register();

		return array(
			'automatic' => $automatic,
			'shortcode' => $shortcode,
		);
	}

	/**
	 * @param bool $admin Whether to simulate admin.
	 */
	private function set_admin_screen( bool $admin ): void {
		global $current_screen;

		if ( $admin ) {
			set_current_screen( 'dashboard' );
			return;
		}

		$current_screen = null;
	}
}
