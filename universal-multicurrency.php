<?php
/**
 * Plugin Name: Universal Multicurrency for WooCommerce
 * Plugin URI: https://github.com/magpern/universal-multicurrency
 * Description: Unlimited currencies with manual or automatic exchange rates. WooCommerce remains the single source of truth for products and inventory; currency affects monetary values only.
 * Version: 0.23.0
 * Author: magpern
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: universal-multicurrency
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 10.9
 *
 * @package UniversalMulticurrency
 */

defined( 'ABSPATH' ) || exit;

define( 'UMC_VERSION', '0.23.0' );
define( 'UMC_PLUGIN_FILE', __FILE__ );

// PHP version guard. The "Requires PHP" header stops activation on WP 5.1+,
// but a file-drop install can bypass it, so fail closed with a notice.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Universal Multicurrency requires PHP 8.1 or newer and is inactive.', 'universal-multicurrency' )
			);
		}
	);
	return;
}

$umc_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $umc_autoload ) ) {
	require_once $umc_autoload;
}

/*
 * Compatibility declarations are the ONLY WooCommerce hooks Milestone 0 is
 * allowed to register. No price, currency, cart, checkout, coupon, shipping,
 * tax, fee, order-value or stock filters may be added here.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UMC_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', UMC_PLUGIN_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Universal Multicurrency requires WooCommerce to be installed and active.', 'universal-multicurrency' )
					);
				}
			);
			return;
		}

		if ( ! class_exists( \UMC\Plugin::class ) ) {
			// Autoloader missing (composer install not run); stay inert.
			return;
		}

		\UMC\Plugin::instance()->init();
	}
);
