<?php
/**
 * Checkout Blocks notice asset registration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\CurrencyContext;

/**
 * Enqueues the Blocks checkout notice consumer script.
 */
final class CheckoutBlocksNoticeAssets {

	public const SCRIPT_HANDLE = 'umc-checkout-notice';

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the asset registrar to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * Enqueues the Blocks checkout notice script on checkout block pages.
	 */
	public function enqueue(): void {
		if ( ! $this->context->is_convertible_request() || ! $this->is_checkout_blocks_page() ) {
			return;
		}

		$script_path = plugin_dir_path( UMC_PLUGIN_FILE ) . 'assets/js/checkout-notice.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/js/checkout-notice.js', UMC_PLUGIN_FILE ),
			array( 'wp-data', 'wp-i18n', 'wc-blocks-data' ),
			defined( 'UMC_VERSION' ) ? (string) UMC_VERSION : false,
			true
		);

		wp_enqueue_script( self::SCRIPT_HANDLE );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::SCRIPT_HANDLE, 'universal-multicurrency', plugin_dir_path( UMC_PLUGIN_FILE ) . 'languages' );
		}
	}

	/**
	 * Whether the current request renders Checkout Blocks.
	 */
	public function is_checkout_blocks_page(): bool {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' )
			&& method_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils', 'is_checkout_block' ) ) {
			return (bool) \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block();
		}

		if ( function_exists( 'has_block' ) ) {
			return has_block( 'woocommerce/checkout' );
		}

		return false;
	}
}
