<?php
/**
 * M26 v1.0 release-acceptance fixture cleanup. Run only via WP-CLI on an
 * authorized DEV environment.
 *
 * Usage: wp eval-file tests/e2e/fixtures/cleanup-v1-fixtures.php <run_id>
 *
 * Deletes ONLY products whose SKU starts with "v1e2e-<run_id>-" and the
 * disposable Blocks cart/checkout pages named "v1e2e-blocks-cart-<run_id>"
 * and "v1e2e-blocks-checkout-<run_id>" -- scoped strictly to this run's own
 * disposable fixtures. Never touches any other product or page, regardless
 * of name or SKU pattern.
 *
 * @package UniversalMulticurrency
 */

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required, e.g.: wp eval-file cleanup-v1-fixtures.php abc123' );
}

$prefix = 'v1e2e-' . $run_id . '-';

$product_ids = get_posts(
	array(
		'post_type'      => array( 'product', 'product_variation' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off disposable acceptance-fixture cleanup, not production code.
			array(
				'key'     => '_sku',
				'value'   => $prefix,
				'compare' => 'LIKE',
			),
		),
	)
);

$deleted = array();

foreach ( $product_ids as $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product instanceof WC_Product ) {
		continue;
	}

	$sku = $product->get_sku();

	if ( 0 !== strpos( $sku, $prefix ) ) {
		continue; // Defense in depth: LIKE above is a prefilter, not the authority.
	}

	$product->delete( true );
	$deleted[] = $sku;
}

$page_slugs    = array( 'v1e2e-blocks-checkout-' . $run_id, 'v1e2e-blocks-cart-' . $run_id );
$pages_deleted = array();

foreach ( $page_slugs as $page_slug ) {
	$page = get_page_by_path( $page_slug, OBJECT, 'page' );

	if ( $page instanceof WP_Post ) {
		wp_delete_post( $page->ID, true );
		$pages_deleted[] = $page_slug;
	}
}

WP_CLI::line(
	(string) wp_json_encode(
		array(
			'run_id'        => $run_id,
			'deleted_skus'  => $deleted,
			'pages_deleted' => $pages_deleted,
		)
	)
);
