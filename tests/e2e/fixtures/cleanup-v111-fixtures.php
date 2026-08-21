<?php
/**
 * Cleanup for V1.1.1 variable price-range fixtures.
 *
 * Usage: wp eval-file cleanup-v111-fixtures.php <run_id>
 *
 * @package UniversalMulticurrency
 */

$run_id = $args[0] ?? null;

if ( ! is_string( $run_id ) || '' === $run_id ) {
	WP_CLI::error( 'A run id argument is required.' );
}

$prefix = 'v111e2e-' . $run_id;
$ids    = get_posts(
	array(
		'post_type'      => array( 'product', 'product_variation' ),
		'post_status'    => 'any',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		's'              => $prefix,
	)
);

foreach ( $ids as $id ) {
	$product = wc_get_product( $id );
	if ( $product ) {
		$product->delete( true );
	} else {
		wp_delete_post( (int) $id, true );
	}
}

WP_CLI::success( 'Removed ' . count( $ids ) . ' v111 fixture post(s) for run ' . $run_id );
