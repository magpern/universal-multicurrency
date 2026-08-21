<?php
/**
 * V1.1.1 variable price-range browser fixture setup (DEV only).
 *
 * Usage: wp eval-file setup-v111-fixtures.php <run_id>
 *
 * @package UniversalMulticurrency
 */

use UMC\Converter;
use UMC\Settings;

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required.' );
}

$prefix = 'v111e2e-' . $run_id;
$base   = get_option( 'woocommerce_currency', 'EUR' );

$parent = new WC_Product_Variable();
$parent->set_name( "V111 E2E Variable {$run_id}" );
$parent->set_sku( "{$prefix}-variable" );
$parent->set_status( 'publish' );
$parent->set_catalog_visibility( 'hidden' );
$parent_id = $parent->save();

$attr = new WC_Product_Attribute();
$attr->set_name( 'strength' );
$attr->set_options( array( '10mg', '20mg' ) );
$attr->set_visible( true );
$attr->set_variation( true );
$parent = wc_get_product( $parent_id );
$parent->set_attributes( array( $attr ) );
$parent->save();

$low = new WC_Product_Variation();
$low->set_parent_id( $parent_id );
$low->set_regular_price( '35.99' );
$low->set_attributes( array( 'strength' => '10mg' ) );
$low->set_status( 'publish' );
$low_id = $low->save();

$high = new WC_Product_Variation();
$high->set_parent_id( $parent_id );
$high->set_regular_price( '65.99' );
$high->set_attributes( array( 'strength' => '20mg' ) );
$high->set_status( 'publish' );
$high_id = $high->save();

WC_Product_Variable::sync( $parent_id );
wc_delete_product_transients( $parent_id );

$settings = new Settings();
$dkk_rate = $settings->get_rate( 'DKK' );

if ( null === $dkk_rate || '' === $dkk_rate ) {
	WP_CLI::error( 'DKK has no usable rate on this DEV site — cannot build expected amounts.' );
}

$expected_min = Converter::apply_rate( '35.99', $dkk_rate, 2 );
$expected_max = Converter::apply_rate( '65.99', $dkk_rate, 2 );

$payload = array(
	'run_id'           => $run_id,
	'base_currency'    => $base,
	'parent'           => array(
		'id'  => $parent_id,
		'sku' => "{$prefix}-variable",
		'url' => get_permalink( $parent_id ),
	),
	'variation_low'    => array(
		'id'    => $low_id,
		'price' => '35.99',
	),
	'variation_high'   => array(
		'id'    => $high_id,
		'price' => '65.99',
	),
	'dkk_rate'         => $dkk_rate,
	'expected_dkk_min' => $expected_min,
	'expected_dkk_max' => $expected_max,
);

echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) . "\n";
