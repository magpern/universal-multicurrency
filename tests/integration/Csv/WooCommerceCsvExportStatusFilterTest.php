<?php
/**
 * M25 WP1: empirical confirmation (or correction) of architecture doc §4's
 * assumption that WooCommerce's own product-export query already decides
 * which rows appear, so UMC's `woocommerce_product_export_row_data`
 * contributor needs no independent status filter of its own.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Csv;

use WP_UnitTestCase;

/**
 * @covers \WC_Product_CSV_Exporter
 */
final class WooCommerceCsvExportStatusFilterTest extends WP_UnitTestCase {

	/**
	 * Runs the real exporter's query/row-building step scoped to a fixed set
	 * of product IDs and returns which of those IDs produced an exported row.
	 *
	 * Scopes via the `woocommerce_product_export_product_query_args` filter
	 * (injecting `include`) rather than `set_product_ids_to_export()`: that
	 * method, and the underlying `product_ids_to_export` property it sets,
	 * do not exist at this plugin's WC 8.2.5 floor at all (confirmed absent
	 * by direct source read at the WC 8.2.3 tag; added to WooCommerce after
	 * 8.2 — the `floor` CI leg caught this as a real regression during M25
	 * development). The query-args filter itself is long-standing and
	 * present at the floor, so this scoping mechanism works identically on
	 * every supported WooCommerce version.
	 *
	 * @param array<int, int> $product_ids Product IDs to attempt to export.
	 * @return array<int, int> IDs that appear in the prepared export rows.
	 */
	private function exported_ids( array $product_ids ): array {
		$inject = static function ( array $args ) use ( $product_ids ): array {
			$args['include'] = $product_ids;
			return $args;
		};

		add_filter( 'woocommerce_product_export_product_query_args', $inject );

		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->prepare_data_to_export();

		remove_filter( 'woocommerce_product_export_product_query_args', $inject );

		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );
		$rows = $reflection->getValue( $exporter );

		return array_map( static fn( array $row ): int => (int) $row['id'], $rows );
	}

	/**
	 * Runs the real exporter scoped to a real product category (which the
	 * given parent is assigned to here) and returns which IDs appear as
	 * exported rows.
	 *
	 * The variations-of-matched-parents second-pass query is only
	 * triggered by `!empty( $args['category'] )` at this plugin's WC 8.2.5
	 * floor -- `!empty( $args['include'] )` alone does NOT trigger it there
	 * (confirmed by direct source read; the current/10.9 version's
	 * condition is the more permissive `include OR category`, a real
	 * cross-version WooCommerce behavior difference this suite must not
	 * paper over). A real category assignment is therefore the only
	 * scoping mechanism that reliably exercises the same code path on both
	 * versions -- a synthetic/unassigned category slug in `$args['category']`
	 * would just make the top-level query match nothing.
	 *
	 * @param int $parent_id Variable parent to assign to the category and export.
	 * @return array<int, int> IDs that appear in the prepared export rows.
	 */
	private function exported_ids_via_category( int $parent_id ): array {
		$slug = 'umc-e2e-' . $parent_id;
		$term = wp_insert_term( $slug, 'product_cat' );
		$this->assertIsArray( $term, 'Failed to create the characterization category term.' );
		wp_set_object_terms( $parent_id, array( (int) $term['term_id'] ), 'product_cat' );

		// set_product_category_to_export() expects category SLUGS
		// (it runs sanitize_title_with_dashes() on each entry), not term
		// IDs -- passing the numeric term_id here silently matches nothing.
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_category_to_export( array( $slug ) );
		$exporter->prepare_data_to_export();

		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );
		$rows = $reflection->getValue( $exporter );

		return array_map( static fn( array $row ): int => (int) $row['id'], $rows );
	}

	/**
	 * Confirms the doc's core claim: a trashed product never reaches
	 * `woocommerce_product_export_row_data` at all — WooCommerce's own query
	 * excludes it before any UMC hook could run, so no independent status
	 * guard is needed to keep trashed-product fixed prices out of an export.
	 */
	public function test_trashed_product_produces_no_export_row(): void {
		$published = new \WC_Product_Simple();
		$published->set_status( 'publish' );
		$published->set_regular_price( '10' );
		$published->save();

		$trashed = new \WC_Product_Simple();
		$trashed->set_status( 'publish' );
		$trashed->set_regular_price( '10' );
		$trashed->save();
		wp_trash_post( $trashed->get_id() );

		$ids = $this->exported_ids( array( $published->get_id(), $trashed->get_id() ) );

		$this->assertContains( $published->get_id(), $ids );
		$this->assertNotContains( $trashed->get_id(), $ids, 'A trashed product must never reach the row_data hook.' );
	}

	/**
	 * Draft, pending, and private products DO reach row_data (WooCommerce's
	 * own export explicitly includes these non-published statuses) —
	 * confirming a hypothetical "only published" filter on UMC's side would
	 * be wrong, not merely redundant: WooCommerce's own status contract for
	 * export is broader than "published only".
	 */
	public function test_draft_pending_and_private_products_do_produce_export_rows(): void {
		$statuses = array( 'draft', 'pending', 'private' );
		$ids      = array();

		foreach ( $statuses as $status ) {
			$product = new \WC_Product_Simple();
			$product->set_status( $status );
			$product->set_regular_price( '10' );
			$product->save();
			$ids[ $status ] = $product->get_id();
		}

		$exported = $this->exported_ids( array_values( $ids ) );

		foreach ( $ids as $status => $id ) {
			$this->assertContains( $id, $exported, "A '$status' product must reach the row_data hook — WooCommerce's own export query includes it." );
		}
	}

	/**
	 * A trashed *variation* also never reaches row_data — the same
	 * exclusion holds independently for the variations sub-query
	 * (architecture doc §4's "simple products and variations each project
	 * their own document" claim implicitly assumes this).
	 */
	public function test_trashed_variation_produces_no_export_row(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$kept = new \WC_Product_Variation();
		$kept->set_parent_id( $parent->get_id() );
		$kept->set_regular_price( '50' );
		$kept->save();

		$trashed_variation = new \WC_Product_Variation();
		$trashed_variation->set_parent_id( $parent->get_id() );
		$trashed_variation->set_regular_price( '50' );
		$trashed_variation->save();
		wp_trash_post( $trashed_variation->get_id() );

		$ids = $this->exported_ids_via_category( $parent->get_id() );

		$this->assertContains( $kept->get_id(), $ids );
		$this->assertNotContains( $trashed_variation->get_id(), $ids, 'A trashed variation must never reach the row_data hook.' );
	}

	/**
	 * Correction / precision note (not a contradiction of the doc's
	 * conclusion): WooCommerce's variations sub-query does not repeat the
	 * top-level query's explicit status override, so it falls back to
	 * WC_Product_Query's own default status set, which — unlike the
	 * top-level product query — does not include 'future'. A variation
	 * scheduled for a future publish date is therefore excluded from export
	 * even though a future-scheduled top-level product is included. This
	 * does not change the architecture doc's conclusion (UMC still needs no
	 * independent status filter — WC's query is still the sole authority on
	 * which rows appear), it only documents that the two queries' status
	 * sets are not identical to each other.
	 */
	public function test_future_scheduled_variation_is_excluded_while_future_scheduled_product_is_included(): void {
		$future_product = new \WC_Product_Simple();
		$future_product->set_status( 'future' );
		$future_product->set_regular_price( '10' );
		$future_product->set_date_created( strtotime( '+1 day' ) );
		$future_product->save();
		wp_update_post(
			array(
				'ID'            => $future_product->get_id(),
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
			)
		);

		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$future_variation = new \WC_Product_Variation();
		$future_variation->set_parent_id( $parent->get_id() );
		$future_variation->set_regular_price( '50' );
		$future_variation->save();
		wp_update_post(
			array(
				'ID'            => $future_variation->get_id(),
				'post_status'   => 'future',
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
			)
		);

		$product_ids = $this->exported_ids( array( $future_product->get_id() ) );
		$this->assertContains( $future_product->get_id(), $product_ids, 'A future-scheduled top-level product is included by the exporter\'s explicit status override.' );

		$variation_ids = $this->exported_ids_via_category( $parent->get_id() );
		$this->assertNotContains( $future_variation->get_id(), $variation_ids, 'A future-scheduled variation is excluded — the variations sub-query does not include the "future" status.' );
	}
}
