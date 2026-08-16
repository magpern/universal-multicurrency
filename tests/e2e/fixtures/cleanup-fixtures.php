<?php
/**
 * M25 browser-acceptance fixture cleanup. Run only via WP-CLI on an authorized DEV environment.
 *
 * Usage: wp eval-file tests/e2e/fixtures/cleanup-fixtures.php <run_id>
 *
 * Deletes ONLY products whose SKU starts with "m25e2e-<run_id>-" --
 * scoped strictly to this run's own disposable fixtures. Never touches any
 * other product, regardless of name or SKU pattern.
 *
 * @package UniversalMulticurrency
 */

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required, e.g.: wp eval-file cleanup-fixtures.php abc123' );
}

$prefix = 'm25e2e-' . $run_id . '-';

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

WP_CLI::line(
	(string) wp_json_encode(
		array(
			'run_id'       => $run_id,
			'deleted_skus' => $deleted,
		)
	)
);
