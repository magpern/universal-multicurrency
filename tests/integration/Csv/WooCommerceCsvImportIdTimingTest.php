<?php
/**
 * M25 WP1: empirical confirmation of product-object ID timing across the
 * import hooks — the load-bearing fact behind architecture doc §6's decision
 * to persist M25's structured columns exclusively at
 * `woocommerce_product_import_inserted_product_object`, never at
 * `pre_insert_product_object`.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Csv;

use UMC\Tests\Support\WcCsvImportTestTrait;
use WP_UnitTestCase;

/**
 * @covers \WC_Product_CSV_Importer
 */
final class WooCommerceCsvImportIdTimingTest extends WP_UnitTestCase {

	use WcCsvImportTestTrait;

	/**
	 * ID observed at pre_insert_product_object for the most recent import.
	 *
	 * @var int|null
	 */
	private ?int $pre_insert_id = null;

	/**
	 * ID observed at inserted_product_object for the most recent import.
	 *
	 * @var int|null
	 */
	private ?int $inserted_id = null;

	public function set_up(): void {
		parent::set_up();

		$this->pre_insert_id = null;
		$this->inserted_id   = null;

		add_filter(
			'woocommerce_product_import_pre_insert_product_object',
			function ( $product_object ) {
				$this->pre_insert_id = $product_object->get_id();
				return $product_object;
			}
		);

		add_action(
			'woocommerce_product_import_inserted_product_object',
			function ( $product_object ) {
				$this->inserted_id = $product_object->get_id();
			}
		);
	}

	public function tear_down(): void {
		$this->clean_up_csv_temp_files();
		remove_all_filters( 'woocommerce_product_import_pre_insert_product_object' );
		remove_all_actions( 'woocommerce_product_import_inserted_product_object' );
		parent::tear_down();
	}

	/**
	 * Simple product, no id/sku column: the uncommon, non-round-trip case
	 * the architecture doc explicitly calls out. ID must be genuinely 0 at
	 * pre_insert, and a real, persisted, non-zero ID at inserted.
	 */
	public function test_simple_product_create_without_id_or_sku_column_has_zero_id_at_pre_insert_and_real_id_at_inserted(): void {
		$result = $this->run_csv_import(
			array( 'regular_price' ),
			array( array( '10' ) ),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported'] );
		$this->assertSame( 0, $this->pre_insert_id, 'No id/sku column means the object is genuinely unidentified at pre_insert_product_object.' );
		$this->assertGreaterThan( 0, $this->inserted_id );
		$this->assertSame( $result['imported'][0], $this->inserted_id, 'inserted_product_object sees the same real, final, persisted ID import() reports.' );
		$this->assertNotNull( get_post( $this->inserted_id ), 'The row must already exist in the database by inserted_product_object.' );
	}

	/**
	 * Simple product, updating an existing product by its real id: the
	 * normal round-trip case. ID must already be non-zero at pre_insert.
	 */
	public function test_simple_product_update_with_id_column_has_nonzero_id_at_pre_insert_and_the_same_id_at_inserted(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();
		$product_id = $product->get_id();

		$result = $this->run_csv_import(
			array( 'id', 'regular_price' ),
			array( array( (string) $product_id, '20' ) ),
			array( 'update_existing' => true )
		);

		$this->assertSame( array( $product_id ), $result['updated'] );
		$this->assertSame( $product_id, $this->pre_insert_id, 'A row carrying an id column round-tripping to a real existing product already has a non-zero ID at pre_insert.' );
		$this->assertSame( $product_id, $this->inserted_id );
	}

	/**
	 * Simple product, a fresh (not-yet-existing) id column with
	 * update_existing = false: WooCommerce's own placeholder mechanism
	 * (parse_id_field()) assigns a real, persisted ID during parsing —
	 * before process_item() even runs — so this "create" case still shows a
	 * non-zero ID at pre_insert, unlike the true no-id/no-sku case above.
	 * Documents a real nuance: "has an id column" is the operative
	 * condition, not "is semantically an update".
	 */
	public function test_simple_product_create_with_a_fresh_id_column_already_has_a_nonzero_id_at_pre_insert_via_wc_own_placeholder(): void {
		$result = $this->run_csv_import(
			array( 'id', 'regular_price' ),
			array( array( '999999', '10' ) ),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported'] );
		$this->assertGreaterThan( 0, $this->pre_insert_id, 'WooCommerce\'s own id: placeholder mechanism means even this "create" row has a real ID before pre_insert.' );
		$this->assertSame( $result['imported'][0], $this->pre_insert_id );
		$this->assertSame( $this->pre_insert_id, $this->inserted_id );
	}

	/**
	 * Variation, no id/sku column, only a parent_id: the same non-round-trip
	 * case as the simple-product one, confirmed independently for
	 * variations (architecture doc §6 explicitly claims this holds "for
	 * every create/update/simple/variation combination").
	 */
	public function test_variation_create_without_id_or_sku_column_has_zero_id_at_pre_insert_and_real_id_at_inserted(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$result = $this->run_csv_import(
			array( 'type', 'parent_id', 'regular_price' ),
			array( array( 'variation', 'id:' . $parent->get_id(), '50' ) ),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported_variations'] );
		$this->assertSame( 0, $this->pre_insert_id, 'A variation row with no id/sku column is genuinely unidentified at pre_insert_product_object.' );
		$this->assertGreaterThan( 0, $this->inserted_id );
		$this->assertSame( $result['imported_variations'][0], $this->inserted_id );
	}

	/**
	 * Variation, updating an existing variation by its real id.
	 */
	public function test_variation_update_with_id_column_has_nonzero_id_at_pre_insert_and_the_same_id_at_inserted(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '50' );
		$variation->save();
		$variation_id = $variation->get_id();

		$result = $this->run_csv_import(
			array( 'id', 'type', 'parent_id', 'regular_price' ),
			array( array( (string) $variation_id, 'variation', 'id:' . $parent->get_id(), '75' ) ),
			array( 'update_existing' => true )
		);

		$this->assertSame( array( $variation_id ), $result['updated'] );
		$this->assertSame( $variation_id, $this->pre_insert_id );
		$this->assertSame( $variation_id, $this->inserted_id );
	}
}
