<?php
/**
 * Loads WooCommerce's product CSV export/import classes.
 *
 * These are only autoloaded by WooCommerce itself inside admin request
 * contexts (via WC_Admin_Exporters / WC_Admin_Importers, hooked to
 * admin_init and similar), not on every request — so the integration test
 * bootstrap does not pull them in. M25's WP1 characterization suite talks to
 * these classes directly, so they must be required explicitly here, in the
 * same dependency order and with the same class_exists() guards WooCommerce's
 * own files use to include each other.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

if ( ! class_exists( 'WC_CSV_Exporter', false ) ) {
	require_once WC_ABSPATH . 'includes/export/abstract-wc-csv-exporter.php';
}

if ( ! class_exists( 'WC_CSV_Batch_Exporter', false ) ) {
	require_once WC_ABSPATH . 'includes/export/abstract-wc-csv-batch-exporter.php';
}

if ( ! class_exists( 'WC_Admin_Exporters', false ) ) {
	require_once WC_ABSPATH . 'includes/admin/class-wc-admin-exporters.php';
}

if ( ! class_exists( 'WC_Product_CSV_Exporter', false ) ) {
	require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
}

if ( ! class_exists( 'WC_Product_Importer', false ) ) {
	require_once WC_ABSPATH . 'includes/import/abstract-wc-product-importer.php';
}

if ( ! class_exists( 'WC_Product_CSV_Importer_Controller', false ) ) {
	require_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';
}

if ( ! class_exists( 'WC_Product_CSV_Importer', false ) ) {
	require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
}
