<?php
/**
 * M25 WP1: empirical confirmation that the six extension hooks named in
 * ADR-0030 / architecture doc §2 exist, fire, and carry the expected
 * arguments in the bundled WooCommerce version, via real export/import runs.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Csv;

use UMC\Tests\Support\WcCsvImportTestTrait;
use WP_UnitTestCase;

/**
 * @covers \WC_Product_CSV_Exporter
 * @covers \WC_Product_CSV_Importer_Controller
 * @covers \WC_Product_CSV_Importer
 */
final class WooCommerceCsvHookCharacterizationTest extends WP_UnitTestCase {

	use WcCsvImportTestTrait;

	public function tear_down(): void {
		$this->clean_up_csv_temp_files();
		remove_all_filters( 'woocommerce_product_export_column_names' );
		remove_all_filters( 'woocommerce_product_export_product_default_columns' );
		remove_all_filters( 'woocommerce_product_export_row_data' );
		remove_all_filters( 'woocommerce_csv_product_import_mapping_options' );
		remove_all_filters( 'woocommerce_csv_product_import_mapping_default_columns' );
		remove_all_filters( 'woocommerce_product_import_pre_insert_product_object' );
		remove_all_actions( 'woocommerce_product_import_inserted_product_object' );
		parent::tear_down();
	}

	/**
	 * `woocommerce_product_export_column_names` fires with the column map
	 * and the exporter instance, and a value added by the filter reaches
	 * WC_Product_CSV_Exporter::get_column_names()'s return value.
	 */
	public function test_export_column_names_hook_fires_with_column_map_and_exporter_instance(): void {
		$captured = array();

		add_filter(
			'woocommerce_product_export_column_names',
			function ( array $columns, $exporter ) use ( &$captured ) {
				$captured[]                       = array( $columns, $exporter );
				$columns['umc_fixed_regular_sek'] = 'UMC Fixed Regular Price (SEK)';
				return $columns;
			},
			10,
			2
		);

		$exporter = new \WC_Product_CSV_Exporter();
		$names    = $exporter->get_column_names();

		$this->assertNotEmpty( $captured, 'The column_names hook must fire.' );
		$this->assertIsArray( $captured[0][0] );
		$this->assertArrayHasKey( 'id', $captured[0][0], 'The hook receives the real default column map, not an empty one.' );
		$this->assertInstanceOf( \WC_Product_CSV_Exporter::class, $captured[0][1] );
		$this->assertArrayHasKey( 'umc_fixed_regular_sek', $names, 'A value added by the filter reaches get_column_names().' );
	}

	/**
	 * `woocommerce_product_export_product_default_columns` fires with the
	 * default column map, and a value added reaches
	 * get_default_column_names()'s return value — the individually
	 * selectable "narrow the export" surface.
	 */
	public function test_export_default_columns_hook_fires_with_default_column_map(): void {
		$captured = array();

		add_filter(
			'woocommerce_product_export_product_default_columns',
			function ( array $columns ) use ( &$captured ) {
				$captured[]                       = $columns;
				$columns['umc_fixed_regular_sek'] = 'UMC Fixed Regular Price (SEK)';
				return $columns;
			}
		);

		$exporter = new \WC_Product_CSV_Exporter();
		$defaults = $exporter->get_default_column_names();

		$this->assertNotEmpty( $captured );
		$this->assertArrayHasKey( 'regular_price', $captured[0], 'The hook receives WooCommerce\'s real default column set.' );
		$this->assertArrayHasKey( 'umc_fixed_regular_sek', $defaults );
	}

	/**
	 * `woocommerce_product_export_row_data` fires once per exported row with
	 * ($row, $product, $exporter), and a value the filter injects reaches
	 * the final row array WooCommerce writes out.
	 */
	public function test_export_row_data_hook_fires_per_row_with_row_product_and_exporter(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$captured = array();

		add_filter(
			'woocommerce_product_export_row_data',
			function ( array $row, $product_arg, $exporter_arg ) use ( &$captured ) {
				$captured[]                   = array( $row, $product_arg, $exporter_arg );
				$row['umc_fixed_regular_sek'] = '1150.00';
				return $row;
			},
			10,
			3
		);

		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_ids_to_export( array( $product->get_id() ) );
		$exporter->prepare_data_to_export();

		$this->assertNotEmpty( $captured, 'The row_data hook must fire for the exported product.' );
		$this->assertIsArray( $captured[0][0] );
		$this->assertArrayHasKey( 'id', $captured[0][0] );
		$this->assertInstanceOf( \WC_Product::class, $captured[0][1] );
		$this->assertSame( $product->get_id(), $captured[0][1]->get_id() );
		$this->assertInstanceOf( \WC_Product_CSV_Exporter::class, $captured[0][2] );

		// Reach into the exporter's prepared rows (protected) to confirm the
		// filter's injected value survives into what would actually be
		// written to the CSV.
		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );
		$rows = $reflection->getValue( $exporter );

		$this->assertSame( '1150.00', $rows[0]['umc_fixed_regular_sek'] ?? null );
	}

	/**
	 * `woocommerce_csv_product_import_mapping_options` fires per raw column
	 * during the mapping-step UI's option list build, with (options array,
	 * raw header string).
	 */
	public function test_import_mapping_options_hook_fires_with_options_and_raw_header(): void {
		$captured = array();

		add_filter(
			'woocommerce_csv_product_import_mapping_options',
			function ( array $options, $item ) use ( &$captured ) {
				$captured[] = array( $options, $item );
				return $options;
			},
			10,
			2
		);

		$controller = new \WC_Product_CSV_Importer_Controller();
		$reflection = new \ReflectionMethod( $controller, 'get_mapping_options' );
		$reflection->setAccessible( true );
		$options = $reflection->invoke( $controller, 'UMC Fixed Regular Price (SEK)' );

		$this->assertNotEmpty( $captured );
		$this->assertIsArray( $captured[0][0] );
		$this->assertArrayHasKey( 'id', $captured[0][0], 'The hook receives WooCommerce\'s real mapping option list.' );
		$this->assertSame( 'UMC Fixed Regular Price (SEK)', $captured[0][1] );
		$this->assertArrayHasKey( 'meta:UMC Fixed Regular Price (SEK)', $options, 'WooCommerce always exposes a generic "Import as meta data" fallback option for any unrecognized header — this remains true regardless of whether a plugin registers its own option for that header.' );
	}

	/**
	 * `woocommerce_csv_product_import_mapping_default_columns` fires during
	 * auto-mapping with (default column map, raw headers array), and a value
	 * the filter injects is honored by the auto-mapper.
	 */
	public function test_import_mapping_default_columns_hook_fires_and_is_honored_by_auto_mapping(): void {
		$captured = array();

		add_filter(
			'woocommerce_csv_product_import_mapping_default_columns',
			function ( array $columns, $raw_headers ) use ( &$captured ) {
				$captured[]                               = array( $columns, $raw_headers );
				$columns['UMC Fixed Regular Price (SEK)'] = 'umc_fixed_regular_sek';
				return $columns;
			},
			10,
			2
		);

		$controller = new \WC_Product_CSV_Importer_Controller();
		$reflection = new \ReflectionMethod( $controller, 'auto_map_columns' );
		$reflection->setAccessible( true );
		$mapped = $reflection->invoke( $controller, array( 'ID', 'UMC Fixed Regular Price (SEK)' ) );

		$this->assertNotEmpty( $captured );
		$this->assertIsArray( $captured[0][0] );
		$this->assertArrayHasKey( 'id', array_flip( $captured[0][0] ), 'The hook receives WooCommerce\'s real default column-label map.' );
		$this->assertSame( array( 'ID', 'UMC Fixed Regular Price (SEK)' ), $captured[0][1] );
		$this->assertSame( 'umc_fixed_regular_sek', $mapped[1], 'A value injected by the hook is honored by auto-mapping.' );
	}

	/**
	 * `woocommerce_product_import_pre_insert_product_object` fires with
	 * ($object, $data) before $object->save() — confirmed here by observing
	 * that a mutation the filter makes to $object survives into the saved
	 * product, and that $data is the parsed row array.
	 */
	public function test_pre_insert_product_object_hook_fires_before_save_with_object_and_parsed_data(): void {
		$captured = array();

		add_filter(
			'woocommerce_product_import_pre_insert_product_object',
			function ( $product_object, $data ) use ( &$captured ) {
				$captured[] = array( $product_object, $data );
				$product_object->set_name( 'Renamed by pre_insert hook' );
				return $product_object;
			},
			10,
			2
		);

		$result = $this->run_csv_import(
			array( 'regular_price', 'name' ),
			array( array( '10', 'Original name' ) ),
			array( 'update_existing' => false )
		);

		$this->assertNotEmpty( $captured );
		$this->assertInstanceOf( \WC_Product::class, $captured[0][0] );
		$this->assertIsArray( $captured[0][1] );
		$this->assertSame( 'Original name', $captured[0][1]['name'] ?? null, '$data is the parsed row before the object mutation.' );

		$this->assertCount( 1, $result['imported'] );
		$saved = wc_get_product( $result['imported'][0] );
		$this->assertSame( 'Renamed by pre_insert hook', $saved->get_name(), 'A mutation made inside the hook is persisted by the subsequent $object->save().' );
	}

	/**
	 * `woocommerce_product_import_inserted_product_object` fires with
	 * ($object, $data) strictly after $object->save() — confirmed here by
	 * observing a real, non-zero, persisted ID inside the hook.
	 */
	public function test_inserted_product_object_hook_fires_after_save_with_real_persisted_id(): void {
		$captured = array();

		add_action(
			'woocommerce_product_import_inserted_product_object',
			function ( $product_object, $data ) use ( &$captured ) {
				$captured[] = array( $product_object->get_id(), get_post( $product_object->get_id() ), $data );
			},
			10,
			2
		);

		$result = $this->run_csv_import(
			array( 'regular_price' ),
			array( array( '10' ) ),
			array( 'update_existing' => false )
		);

		$this->assertNotEmpty( $captured );
		[ $id_seen_in_hook, $post_row, $data ] = $captured[0];

		$this->assertGreaterThan( 0, $id_seen_in_hook );
		$this->assertNotNull( $post_row, 'The product row must already exist in the database when this hook fires.' );
		$this->assertIsArray( $data );
		$this->assertCount( 1, $result['imported'] );
		$this->assertSame( $result['imported'][0], $id_seen_in_hook );
	}
}
