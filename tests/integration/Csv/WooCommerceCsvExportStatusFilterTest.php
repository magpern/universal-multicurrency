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
	 * Runs the real exporter's query/row-building step for a fixed set of
	 * product IDs and returns which of those IDs produced an exported row.
	 *
	 * @param array<int, int> $product_ids Product IDs to attempt to export.
	 * @return array<int, int> IDs that appear in the prepared export rows.
	 */
	private function exported_ids( array $product_ids ): array {
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_ids_to_export( $product_ids );
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

		// The variations sub-query only runs when the parent is exported via
		// an include/category filter (architecture doc's own export path);
		// export by explicit product ID list covers the parent.
		$ids = $this->exported_ids( array( $parent->get_id() ) );

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

		$variation_ids = $this->exported_ids( array( $parent->get_id() ) );
		$this->assertNotContains( $future_variation->get_id(), $variation_ids, 'A future-scheduled variation is excluded — the variations sub-query does not include the "future" status.' );
	}
}
