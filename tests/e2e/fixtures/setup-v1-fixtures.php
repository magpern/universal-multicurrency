<?php
/**
 * M26 v1.0 release-acceptance fixture setup. Run only via WP-CLI on an
 * authorized DEV environment.
 *
 * Usage: wp eval-file tests/e2e/fixtures/setup-v1-fixtures.php <run_id>
 *
 * Creates disposable, uniquely-prefixed products (and, for the Blocks
 * journey, one disposable page) only -- never touches any existing
 * merchant product or page. Writes fixture identifiers as JSON to stdout
 * so the calling shell can persist them for the Playwright run and for
 * cleanup. Fixed prices are authored through the plugin's own
 * FixedPriceRepository/FixedPriceDocument -- the same persistence path the
 * product editor and CSV import use -- rather than hand-written post meta.
 *
 * @package UniversalMulticurrency
 */

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required, e.g.: wp eval-file setup-v1-fixtures.php abc123' );
}

$prefix = 'v1e2e-' . $run_id;

if ( ! class_exists( FixedPriceRepository::class ) ) {
	WP_CLI::error( 'UMC\Pricing\FixedPriceRepository not found -- is universal-multicurrency active?' );
}

$base_currency = get_option( 'woocommerce_currency', 'EUR' );
$repository    = new FixedPriceRepository( $base_currency );

// -----------------------------------------------------------------
// Converted-price simple product (no fixed price authored) -- core
// purchase and Blocks journeys.
// -----------------------------------------------------------------

$converted = new WC_Product_Simple();
$converted->set_name( "V1 E2E Converted {$run_id}" );
$converted->set_sku( "{$prefix}-converted" );
$converted->set_status( 'publish' );
$converted->set_regular_price( '50' );
$converted->set_catalog_visibility( 'hidden' );
$converted_id = $converted->save();

// -----------------------------------------------------------------
// Fixed-price simple product -- fixed-pricing journey.
// -----------------------------------------------------------------

$fixed_simple = new WC_Product_Simple();
$fixed_simple->set_name( "V1 E2E Fixed Simple {$run_id}" );
$fixed_simple->set_sku( "{$prefix}-fixed-simple" );
$fixed_simple->set_status( 'publish' );
$fixed_simple->set_regular_price( '100' );
$fixed_simple->set_catalog_visibility( 'hidden' );
$fixed_simple_id = $fixed_simple->save();

// Deliberately not a multiple of the SEK rate (11.50) so a storefront
// assertion of the exact displayed amount is unambiguous proof of the
// fixed value, not a coincidentally similar FX conversion.
$repository->save(
	$fixed_simple_id,
	FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '799.00' ) ), $base_currency )
);

// -----------------------------------------------------------------
// Variable product: one variation with an authored SEK fixed price,
// one with none (FX fallback) -- fixed-pricing journey.
// -----------------------------------------------------------------

$parent = new WC_Product_Variable();
$parent->set_name( "V1 E2E Variable {$run_id}" );
$parent->set_sku( "{$prefix}-variable" );
$parent->set_status( 'publish' );
$parent->set_catalog_visibility( 'hidden' );

$attribute = new WC_Product_Attribute();
$attribute->set_id( 0 );
$attribute->set_name( 'V1 E2E Size' );
$attribute->set_options( array( 'Fixed', 'Converted' ) );
$attribute->set_position( 0 );
$attribute->set_visible( true );
$attribute->set_variation( true );
$parent->set_attributes( array( $attribute ) );
$parent_id = $parent->save();

$fixed_variation = new WC_Product_Variation();
$fixed_variation->set_parent_id( $parent_id );
$fixed_variation->set_sku( "{$prefix}-var-fixed" );
$fixed_variation->set_status( 'publish' );
$fixed_variation->set_regular_price( '20' );
$fixed_variation->set_attributes( array( 'v1-e2e-size' => 'Fixed' ) );
$fixed_variation_id = $fixed_variation->save();

$converted_variation = new WC_Product_Variation();
$converted_variation->set_parent_id( $parent_id );
$converted_variation->set_sku( "{$prefix}-var-converted" );
$converted_variation->set_status( 'publish' );
$converted_variation->set_regular_price( '30' );
$converted_variation->set_attributes( array( 'v1-e2e-size' => 'Converted' ) );
$converted_variation_id = $converted_variation->save();

$parent->save(); // Re-sync variation data (children hash, price range).

$repository->save(
	$fixed_variation_id,
	FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '188.00' ) ), $base_currency )
);

// -----------------------------------------------------------------
// Disposable Blocks Cart page and, separately, Blocks Checkout page --
// Blocks journey. Kept as two pages (the WooCommerce-standard shape;
// the Checkout block does not reliably render stacked directly beneath
// a Cart block on one page). The merchant's real cart/checkout pages
// (if any) are never touched.
//
// The Checkout/Cart blocks are NOT self-sufficient dynamic blocks: a bare
// `<!-- wp:woocommerce/checkout /-->` server-renders to an empty string --
// they require their full canonical inner-block scaffold (express payment,
// contact/shipping/billing/payment sections, order summary, etc.), exactly
// as WooCommerce's own installer provisions it for a fresh site. Use that
// same canonical content rather than hand-writing an approximation.
// -----------------------------------------------------------------

$cart_content_method     = new ReflectionMethod( 'WC_Install', 'get_cart_block_content' );
$checkout_content_method = new ReflectionMethod( 'WC_Install', 'get_checkout_block_content' );
$cart_content_method->setAccessible( true );
$checkout_content_method->setAccessible( true );

$blocks_cart_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => "V1 E2E Blocks Cart {$run_id}",
		'post_name'    => "v1e2e-blocks-cart-{$run_id}",
		'post_content' => $cart_content_method->invoke( null ),
	)
);

$blocks_page_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => "V1 E2E Blocks Checkout {$run_id}",
		'post_name'    => "v1e2e-blocks-checkout-{$run_id}",
		'post_content' => $checkout_content_method->invoke( null ),
	)
);

if ( ! is_int( $blocks_page_id ) || $blocks_page_id <= 0 || ! is_int( $blocks_cart_page_id ) || $blocks_cart_page_id <= 0 ) {
	WP_CLI::error( 'Failed to create the disposable Blocks cart/checkout pages.' );
}

$fixtures = array(
	'run_id'               => $run_id,
	'base_currency'        => $base_currency,
	'converted'            => array(
		'id'  => $converted_id,
		'sku' => "{$prefix}-converted",
	),
	'fixed_simple'         => array(
		'id'  => $fixed_simple_id,
		'sku' => "{$prefix}-fixed-simple",
	),
	'variable_parent'      => array(
		'id'  => $parent_id,
		'sku' => "{$prefix}-variable",
	),
	'variation_fixed'      => array(
		'id'  => $fixed_variation_id,
		'sku' => "{$prefix}-var-fixed",
	),
	'variation_converted'  => array(
		'id'  => $converted_variation_id,
		'sku' => "{$prefix}-var-converted",
	),
	'blocks_page_id'       => $blocks_page_id,
	'blocks_page_url'      => get_permalink( $blocks_page_id ),
	'blocks_cart_page_id'  => $blocks_cart_page_id,
	'blocks_cart_page_url' => get_permalink( $blocks_cart_page_id ),
);

WP_CLI::line( (string) wp_json_encode( $fixtures ) );
