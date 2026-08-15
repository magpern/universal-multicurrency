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

		// Run the order suite against High-Performance Order Storage. Tables are
		// created here, before any test transaction, so no runtime DDL (which
		// would implicitly commit) happens mid-test.
		update_option( 'woocommerce_feature_custom_order_tables_enabled', 'yes' );

		$umc_sync = \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class;
		if ( function_exists( 'wc_get_container' ) && class_exists( $umc_sync ) ) {
			wc_get_container()->get( $umc_sync )->create_database_tables();
			update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
			update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		}

		$GLOBALS['wp_roles'] = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
);

require_once $umc_tests_dir . '/includes/bootstrap.php';

// Shared test base classes. Required explicitly because they extend
// WP_UnitTestCase, which only exists once the bootstrap above has run, and
// because PHPUnit only autoloads files matching the *Test.php suffix.
require_once __DIR__ . '/StoreApi/StoreApiTestCase.php';

// Global-namespace WP_CLI stub, so PricesCommand's `\WP_CLI::...` calls
// resolve when tests exercise it directly (not via the WP-CLI runtime). Does
// not define the WP_CLI constant, so Plugin::init()'s own CLI registration
// block still correctly stays inactive in the test environment.
require_once dirname( __DIR__ ) . '/Support/WpCliTestStub.php';
require_once dirname( __DIR__ ) . '/Support/WpCliUtilsTestStub.php';
