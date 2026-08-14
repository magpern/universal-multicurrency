<?php
/**
 * Integration tests for the native currency switcher block.
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
use UMC\CurrencySwitcher;
use UMC\Display\AutomaticRenderRegistry;
use UMC\Display\AutomaticSwitcherPlacement;
use UMC\Display\StorefrontRequestContext;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherBlock;
use UMC\Display\SwitcherPresence;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Covers block registration, rendering, coexistence, and selection parity.
 */
final class SwitcherBlockIntegrationTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		SwitcherViewModelFactory::reset_instance_counter();
		parent::tear_down();
	}

	public function test_block_is_registered(): void {
		$this->boot_services();

		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( SwitcherBlock::BLOCK_NAME ) );
	}

	public function test_do_blocks_renders_switcher_markup(): void {
		$services = $this->boot_services(
			array(
				'enabled' => true,
			)
		);

		$html = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'umc-switcher', $html );
		$this->assertStringContainsString( 'umc-switcher__trigger', $html );
		$this->assertStringContainsString( 'wp-block-universal-multicurrency-currency-switcher', $html );
	}

	public function test_block_uses_manual_placement_when_global_floating_is_enabled(): void {
		$this->boot_services(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$html = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'umc-switcher--manual', $html );
		$this->assertStringNotContainsString( 'umc-switcher--floating-side', $html );
	}

	public function test_two_blocks_receive_unique_ids(): void {
		$this->boot_services(
			array(
				'enabled' => true,
			)
		);

		$html = do_blocks(
			'<!-- wp:universal-multicurrency/currency-switcher /--><!-- wp:universal-multicurrency/currency-switcher /-->'
		);

		$this->assertSame( 2, substr_count( $html, 'umc-switcher-trigger-' ) );
		$this->assertSame( 1, substr_count( $html, 'umc-switcher-trigger-1' ) );
		$this->assertSame( 1, substr_count( $html, 'umc-switcher-trigger-2' ) );
	}

	public function test_block_and_shortcode_coexist_with_unique_ids(): void {
		$services = $this->boot_services(
			array(
				'enabled' => true,
			)
		);

		$shortcode = do_shortcode( '[' . SwitcherShortcode::TAG_PRIMARY . ']' );
		$block     = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'umc-switcher-trigger-1', $shortcode );
		$this->assertStringContainsString( 'umc-switcher-trigger-2', $block );
	}

	public function test_block_and_automatic_sticky_footer_coexist(): void {
		$services = $this->boot_services(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_STICKY_FOOTER,
			)
		);

		$block = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		ob_start();
		$services['automatic']->maybe_render();
		$footer = (string) ob_get_clean();

		$this->assertStringContainsString( 'umc-switcher--manual', $block );
		$this->assertSame( 1, substr_count( $footer, 'umc-switcher--floating-bottom' ) );
	}

	public function test_block_selection_persists_like_shortcode(): void {
		$services = $this->boot_services(
			array(
				'enabled'  => true,
				'behavior' => array(
					'remember_selection' => true,
					'active_first'       => true,
				),
			)
		);

		$html = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'currency=SEK', $html );

		$this->persist_without_cookie_notice( $services['switcher'], 'SEK' );

		$this->assertSame( 'SEK', WC()->session->get( CurrencyContext::SESSION_KEY ) );
	}

	public function test_m22_icons_render_through_block(): void {
		$this->boot_services(
			array(
				'enabled' => true,
				'content' => array(
					'trigger'      => array(
						'show_code'   => true,
						'show_symbol' => false,
						'show_name'   => false,
						'show_icon'   => true,
						'order'       => array( 'icon', 'code' ),
					),
					'menu'         => array(
						'show_code'   => true,
						'show_symbol' => false,
						'show_name'   => false,
						'show_icon'   => true,
						'order'       => array( 'icon', 'code' ),
					),
					'show_chevron' => true,
				),
			)
		);

		$html = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'umc-switcher__icon', $html );
		$this->assertStringContainsString( 'data-umc-icon-type="flag"', $html );
	}

	public function test_block_only_page_enqueues_assets_once(): void {
		$this->boot_services(
			array(
				'enabled' => true,
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:universal-multicurrency/currency-switcher /-->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( SwitcherAssets::STYLE_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_script_is( SwitcherAssets::SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * @param array<string, mixed> $display Display overrides.
	 * @return array{
	 *   block: SwitcherBlock,
	 *   automatic: AutomaticSwitcherPlacement,
	 *   switcher: CurrencySwitcher
	 * }
	 */
	private function boot_services( array $display = array() ): array {
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

		$settings  = new Settings();
		$base      = new Currency( 'EUR', 2 );
		$registry  = new CurrencyRegistry( $settings, $base );
		$rates     = new ManualRateProvider( $settings, 'EUR' );
		$context   = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$repo      = new SwitcherSettingsRepository( $settings );
		$factory   = new SwitcherViewModelFactory( $context, new WooCommerceCurrencyProvider(), $repo );
		$renderer  = new SwitcherRenderer();
		$assets    = new SwitcherAssets( new StorefrontRequestContext(), $repo, $context, new SwitcherPresence() );
		$block     = new SwitcherBlock();
		$switcher  = new CurrencySwitcher( $context, $repo );
		$automatic = new AutomaticSwitcherPlacement(
			$repo,
			$factory,
			$renderer,
			$assets,
			new StorefrontRequestContext(),
			new AutomaticRenderRegistry(),
			$context
		);

		$block->bind( $repo, $factory, $renderer, $assets );
		$this->register_block_for_test( $block );

		return array(
			'block'     => $block,
			'automatic' => $automatic,
			'switcher'  => $switcher,
		);
	}

	/**
	 * Ensures the test block instance owns the registered render callback.
	 */
	private function register_block_for_test( SwitcherBlock $block ): void {
		if ( function_exists( 'unregister_block_type' )
			&& \WP_Block_Type_Registry::get_instance()->is_registered( SwitcherBlock::BLOCK_NAME ) ) {
			unregister_block_type( SwitcherBlock::BLOCK_NAME );
		}

		$block->register();
	}

	/**
	 * Persists a selection without failing when PHPUnit bootstrap already sent headers.
	 */
	private function persist_without_cookie_notice( CurrencySwitcher $switcher, string $code ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Suppresses wc_setcookie notices after PHPUnit bootstrap sends headers.
		$previous = set_error_handler(
			static function ( int $errno, string $errstr ): bool {
				if ( E_USER_NOTICE === $errno && str_contains( $errstr, 'cookie cannot be set' ) ) {
					return true;
				}

				return false;
			}
		);

		try {
			$switcher->persist( $code );
		} finally {
			if ( false !== $previous ) {
				restore_error_handler();
			}
		}
	}
}
