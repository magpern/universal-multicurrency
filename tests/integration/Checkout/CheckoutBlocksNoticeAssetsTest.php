<?php
/**
 * Integration tests: Blocks checkout notice asset registration.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Checkout;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\StoreApi\CheckoutBlocksNoticeAssets;
use WP_UnitTestCase;

/**
 * Ensures the Blocks notice consumer script is registered only where needed.
 */
final class CheckoutBlocksNoticeAssetsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		wp_dequeue_script( CheckoutBlocksNoticeAssets::SCRIPT_HANDLE );
		wp_deregister_script( CheckoutBlocksNoticeAssets::SCRIPT_HANDLE );

		parent::tear_down();
	}

	public function test_script_is_registered_with_required_dependencies_on_checkout_block_page(): void {
		$page_id = $this->factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:woocommerce/checkout /-->',
			)
		);

		$this->go_to( get_permalink( $page_id ) );
		$this->boot_assets();

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( CheckoutBlocksNoticeAssets::SCRIPT_HANDLE, 'registered' ) );
		$this->assertTrue( wp_script_is( CheckoutBlocksNoticeAssets::SCRIPT_HANDLE, 'enqueued' ) );

		$script = wp_scripts()->registered[ CheckoutBlocksNoticeAssets::SCRIPT_HANDLE ] ?? null;

		$this->assertNotNull( $script );
		$this->assertContains( 'wp-data', $script->deps );
		$this->assertContains( 'wp-i18n', $script->deps );
		$this->assertContains( 'wc-blocks-data', $script->deps );
		$this->assertStringContainsString( 'checkout-notice.js', (string) $script->src );
	}

	public function test_script_is_not_enqueued_on_non_checkout_pages(): void {
		$page_id = $this->factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $page_id ) );
		$this->boot_assets();

		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( CheckoutBlocksNoticeAssets::SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * Boots a convertible storefront request with notice assets registered.
	 */
	private function boot_assets(): void {
		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save( array( 'currencies' => array( 'SEK' => array( 'rate' => '11.50' ) ) ) );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext(
			$registry,
			new ManualRateProvider( $settings, 'EUR' ),
			new CurrencyResolver()
		);

		( new CheckoutBlocksNoticeAssets( $context ) )->register();
	}
}
