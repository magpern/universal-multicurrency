<?php
/**
 * Integration test bootstrap: loads the WordPress test suite with WooCommerce
 * and this plugin active.
 *
 * The WordPress core install is provisioned by tests/bin/install-wp.sh (or by
 * wp-env, which exports WP_TESTS_DIR). The plugin is loaded through the
 * symlink inside the test install's plugins directory so that WooCommerce's
 * FeaturesController recognizes it as a real plugin.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

$umc_root = dirname( __DIR__, 2 );

require_once $umc_root . '/vendor/autoload.php';

$umc_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $umc_tests_dir ) {
	$umc_tests_dir = $umc_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require_once $umc_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require WP_PLUGIN_DIR . '/universal-multicurrency/universal-multicurrency.php';
	}
);

tests_add_filter(
	'setup_theme',
	function () {
		\WC_Install::install();
		$GLOBALS['wp_roles'] = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
);

require_once $umc_tests_dir . '/includes/bootstrap.php';
