<?php
/**
 * M25 browser-acceptance fixture setup. Run only via WP-CLI on an authorized DEV environment.
 *
 * Usage: wp eval-file tests/e2e/fixtures/setup-fixtures.php <run_id>
 *
 * Creates disposable, uniquely-prefixed products only -- never touches any
 * existing merchant product. Writes fixture identifiers as JSON to stdout
 * so the calling shell can persist them for the Playwright run and for
 * cleanup.
 *
 * Uses the plugin's own domain classes (FixedPriceRepository,
 * FixedPriceDocument) to author fixed prices -- the same persistence path
 * the product editor and M25 CSV import use -- rather than hand-writing raw
 * post meta, so fixtures are guaranteed canonically shaped.
 *
 * @package UniversalMulticurrency
 */

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required, e.g.: wp eval-file setup-fixtures.php abc123' );
}

$prefix = 'm25e2e-' . $run_id;

if ( ! class_exists( FixedPriceRepository::class ) ) {
	WP_CLI::error( 'UMC\Pricing\FixedPriceRepository not found -- is universal-multicurrency active?' );
}

$base_currency = get_option( 'woocommerce_currency', 'EUR' );
$repository    = new FixedPriceRepository( $base_currency );

// -----------------------------------------------------------------
// Simple product: SEK fixed regular+sale (enabled), USD retained
// regular-only (disabled-but-configured), PLN untouched (no authored
// fixed price -- FX fallback / blank-cell export case).
// -----------------------------------------------------------------

$simple = new WC_Product_Simple();
$simple->set_name( "M25 E2E Simple {$run_id}" );
$simple->set_sku( "{$prefix}-simple" );
$simple->set_status( 'publish' );
$simple->set_regular_price( '100' );
$simple->set_sale_price( '80' ); // Native sale active -- exercises the authored SALE path, not just regular.
$simple->set_catalog_visibility( 'hidden' );
$simple_id = $simple->save();

// Authored amounts are deliberately NOT a multiple of any configured FX
// rate (SEK manual_rate 11.50 -> 100*11.50=1150.00, 80*11.50=920.00; USD
// provider_rate 1.1555 -> 100*1.1555=115.55) so a storefront assertion that
// the displayed price equals the authored figure is unambiguous proof the
// FIXED value was used, not a coincidentally-similar FX conversion.
$repository->save(
	$simple_id,
	FixedPriceDocument::from_array(
		array(
			'SEK' => array(
				'regular' => '799.00',
				'sale'    => '650.00',
			),
			'USD' => array(
				'regular' => '90.00',
			),
			// PLN deliberately omitted: no authored fixed price.
		),
		$base_currency
	)
);

// -----------------------------------------------------------------
// Variable product, two variations, each with its own distinct SEK
// fixed regular price -- proves variation-native isolation on export
// and import.
// -----------------------------------------------------------------

$parent = new WC_Product_Variable();
$parent->set_name( "M25 E2E Variable {$run_id}" );
$parent->set_sku( "{$prefix}-variable" );
$parent->set_status( 'publish' );
$parent->set_catalog_visibility( 'hidden' );

$attribute = new WC_Product_Attribute();
$attribute->set_id( 0 );
$attribute->set_name( 'M25 E2E Size' );
$attribute->set_options( array( 'A', 'B' ) );
$attribute->set_position( 0 );
$attribute->set_visible( true );
$attribute->set_variation( true );
$parent->set_attributes( array( $attribute ) );
$parent_id = $parent->save();

$variation_a = new WC_Product_Variation();
$variation_a->set_parent_id( $parent_id );
$variation_a->set_sku( "{$prefix}-var-a" );
$variation_a->set_status( 'publish' );
$variation_a->set_regular_price( '20' );
$variation_a->set_attributes( array( 'm25-e2e-size' => 'A' ) );
$variation_a_id = $variation_a->save();

$variation_b = new WC_Product_Variation();
$variation_b->set_parent_id( $parent_id );
$variation_b->set_sku( "{$prefix}-var-b" );
$variation_b->set_status( 'publish' );
$variation_b->set_regular_price( '30' );
$variation_b->set_attributes( array( 'm25-e2e-size' => 'B' ) );
$variation_b_id = $variation_b->save();

$parent->save(); // Re-sync variation data (children hash, price range).

// Again deliberately not a multiple of the SEK rate (20*11.50=230.00,
// 30*11.50=345.00) -- distinct, storefront-provable, and distinct from each
// other to prove sibling isolation.
$repository->save(
	$variation_a_id,
	FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '188.00' ) ), $base_currency )
);
$repository->save(
	$variation_b_id,
	FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '277.00' ) ), $base_currency )
);

$fixtures = array(
	'run_id'          => $run_id,
	'base_currency'   => $base_currency,
	'simple'          => array(
		'id'  => $simple_id,
		'sku' => "{$prefix}-simple",
	),
	'variable_parent' => array(
		'id'  => $parent_id,
		'sku' => "{$prefix}-variable",
	),
	'variation_a'     => array(
		'id'  => $variation_a_id,
		'sku' => "{$prefix}-var-a",
	),
	'variation_b'     => array(
		'id'  => $variation_b_id,
		'sku' => "{$prefix}-var-b",
	),
);

WP_CLI::line( (string) wp_json_encode( $fixtures ) );
