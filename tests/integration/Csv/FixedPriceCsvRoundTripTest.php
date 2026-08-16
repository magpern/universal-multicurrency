<?php
/**
 * M25 WP5 acceptance: the semantic round-trip contract (architecture doc §8).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Csv;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCsvIntegration;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\Settings;
use UMC\Tests\Support\WcCsvImportTestTrait;
use WP_UnitTestCase;

/**
 * Deliberately never asserts on the full WooCommerce CSV byte stream, row
 * order, quoting, or unrelated columns (architecture doc §8) — only on: (A)
 * persistence round-trip equivalence (canonical document -> export ->
 * import/update -> canonical document) and (B) UMC projection round-trip
 * equivalence (export A -> import -> export B, comparing only UMC-owned
 * column values for matching rows). Both directions run against the real
 * WC_Product_CSV_Exporter/WC_Product_CSV_Importer classes.
 *
 * @covers \UMC\Pricing\FixedPriceCsvIntegration
 */
final class FixedPriceCsvRoundTripTest extends WP_UnitTestCase {

	use WcCsvImportTestTrait;

	/**
	 * @var FixedPriceRepository
	 */
	private FixedPriceRepository $repository;

	public function set_up(): void {
		parent::set_up();

		update_option( 'woocommerce_currency', 'EUR' );
		( new Settings() )->save(
			array(
				'currencies' => array(
					'SEK' => array(
						'rate'    => '11.50',
						'enabled' => true,
					),
					'GBP' => array(
						'rate'    => '0.85',
						'enabled' => false,
					),
				),
			)
		);

		$settings         = new Settings();
		$registry         = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$this->repository = new FixedPriceRepository( 'EUR' );

		( new FixedPriceCsvIntegration( $this->repository, $registry ) )->register();
	}

	public function tear_down(): void {
		$this->clean_up_csv_temp_files();
		delete_option( Settings::OPTION );
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
	 * Runs the real exporter for a single top-level product ID and returns
	 * its row. Scopes via the woocommerce_product_export_product_query_args
	 * filter (`include`), not `set_product_ids_to_export()` -- that method
	 * does not exist at this plugin's WC 8.2.5 floor (confirmed by the
	 * `floor` CI leg; added to WooCommerce after 8.2).
	 *
	 * @param int $product_id Top-level (simple or variable-parent) product ID.
	 * @return array<string, mixed>
	 */
	private function export_row( int $product_id ): array {
		$scope = static function ( array $args ) use ( $product_id ): array {
			$args['include'] = array( $product_id );
			return $args;
		};

		add_filter( 'woocommerce_product_export_product_query_args', $scope );
		$row = $this->find_exported_row( $product_id );
		remove_filter( 'woocommerce_product_export_product_query_args', $scope );

		return $row;
	}

	/**
	 * Runs the real exporter for a variation and returns its own row.
	 *
	 * The variations-of-matched-parents second-pass query is only
	 * triggered by `!empty( $args['category'] )` at this plugin's WC 8.2.5
	 * floor -- `!empty( $args['include'] )` alone does not trigger it there
	 * (a real cross-version WooCommerce behavior difference; see
	 * WooCommerceCsvExportStatusFilterTest::exported_ids_via_category()'s
	 * docblock for the full characterization). A real category assignment
	 * is therefore the only scoping mechanism that reliably exercises the
	 * variation row on both the floor and current WooCommerce versions.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $parent_id    Its variable parent's ID.
	 * @return array<string, mixed>
	 */
	private function export_variation_row( int $variation_id, int $parent_id ): array {
		$slug = 'umc-e2e-round-trip-' . $parent_id;
		$term = wp_insert_term( $slug, 'product_cat' );
		self::assertIsArray( $term, 'Failed to create the characterization category term.' );
		wp_set_object_terms( $parent_id, array( (int) $term['term_id'] ), 'product_cat' );

		// set_product_category_to_export() expects category SLUGS
		// (it runs sanitize_title_with_dashes() on each entry), not term
		// IDs -- passing the numeric term_id here silently matches nothing.
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_category_to_export( array( $slug ) );
		$exporter->prepare_data_to_export();

		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );

		foreach ( $reflection->getValue( $exporter ) as $row ) {
			if ( (int) $row['id'] === $variation_id ) {
				return $row;
			}
		}

		$this->fail( "No exported row found for variation #{$variation_id}." );
	}

	/**
	 * Runs the real exporter (assumed already scoped by a caller-installed
	 * query-args filter) and returns the row whose own id column equals
	 * $find_id.
	 *
	 * @param int $find_id ID to locate among the resulting rows.
	 * @return array<string, mixed>
	 */
	private function find_exported_row( int $find_id ): array {
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->prepare_data_to_export();

		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );

		foreach ( $reflection->getValue( $exporter ) as $row ) {
			if ( (int) $row['id'] === $find_id ) {
				return $row;
			}
		}

		$this->fail( "No exported row found for product #{$find_id}." );
	}

	/**
	 * Reimports one product's exported UMC columns via the real importer,
	 * using the internal column ids directly as CSV headers (bypassing
	 * auto-mapping, which is exercised separately elsewhere).
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $row        Row previously produced by export_row().
	 */
	private function reimport_umc_columns( int $product_id, array $row ): void {
		$umc_keys = array_values(
			array_filter(
				array_keys( $row ),
				static fn ( string $key ): bool => 1 === preg_match( '/^umc_fixed_(regular|sale)_/', $key )
			)
		);

		$header = array_merge( array( 'id' ), $umc_keys );
		$values = array_merge(
			array( (string) $product_id ),
			array_map( static fn ( string $key ): string => (string) $row[ $key ], $umc_keys )
		);

		$result = $this->run_csv_import( $header, array( $values ) );

		self::assertSame( array(), $result['skipped'] ?? array(), 'Reimport must not skip the row.' );
		self::assertSame( array(), $result['failed'] ?? array(), 'Reimport must not fail the row.' );
	}

	// -----------------------------------------------------------------
	// A. Persistence round trip.
	// -----------------------------------------------------------------

	public function test_persistence_round_trip_simple_product_multiple_currencies_regular_and_sale(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$original = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1150.00',
					'sale'    => '900.00',
				),
				'GBP' => array(
					'regular' => '8.50',
				),
			),
			'EUR'
		);
		$this->repository->save( $product->get_id(), $original );

		$row = $this->export_row( $product->get_id() );
		$this->reimport_umc_columns( $product->get_id(), $row );

		$fresh     = new FixedPriceRepository( 'EUR' );
		$resulting = $fresh->get( $product->get_id() );

		self::assertSame( '1150.00', $resulting->get_currency( 'SEK' )->regular() );
		self::assertSame( '900.00', $resulting->get_currency( 'SEK' )->sale() );
		self::assertSame( '8.50', $resulting->get_currency( 'GBP' )->regular() );
		self::assertSame( '', $resulting->get_currency( 'GBP' )->sale() );
	}

	public function test_persistence_round_trip_variation(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( '20' );
		$variation->save();

		$original = FixedPriceDocument::from_array(
			array( 'SEK' => array( 'regular' => '230.00' ) ),
			'EUR'
		);
		$this->repository->save( $variation->get_id(), $original );

		$row = $this->export_variation_row( $variation->get_id(), $parent->get_id() );
		$this->reimport_umc_columns( $variation->get_id(), $row );

		$fresh     = new FixedPriceRepository( 'EUR' );
		$resulting = $fresh->get( $variation->get_id() );

		self::assertSame( '230.00', $resulting->get_currency( 'SEK' )->regular() );

		$parent_document = $fresh->get( $parent->get_id() );
		self::assertTrue( $parent_document->is_empty(), 'The variable parent must never receive a fixed-price document.' );
	}

	public function test_persistence_round_trip_preserves_disabled_currency(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$original = FixedPriceDocument::from_array(
			array( 'GBP' => array( 'regular' => '8.50' ) ), // GBP is disabled-but-configured (set_up()).
			'EUR'
		);
		$this->repository->save( $product->get_id(), $original );

		$row = $this->export_row( $product->get_id() );

		self::assertSame( '8.50', $row['umc_fixed_regular_gbp'], 'Export must include the disabled currency\'s retained value, not blank it.' );

		$this->reimport_umc_columns( $product->get_id(), $row );

		$fresh     = new FixedPriceRepository( 'EUR' );
		$resulting = $fresh->get( $product->get_id() );

		self::assertSame( '8.50', $resulting->get_currency( 'GBP' )->regular(), 'The disabled currency\'s value must survive the round trip.' );
	}

	public function test_persistence_round_trip_empty_document_stays_empty(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$row = $this->export_row( $product->get_id() );

		self::assertSame( '', $row['umc_fixed_regular_sek'] );
		self::assertSame( '', $row['umc_fixed_sale_sek'] );

		$this->reimport_umc_columns( $product->get_id(), $row );

		$fresh     = new FixedPriceRepository( 'EUR' );
		$resulting = $fresh->get( $product->get_id() );

		self::assertTrue( $resulting->is_empty() );
	}

	// -----------------------------------------------------------------
	// B. UMC projection round trip (export A -> import -> export B).
	// -----------------------------------------------------------------

	public function test_projection_round_trip_is_stable_across_two_export_runs(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$original = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1150.00',
					'sale'    => '900.00',
				),
			),
			'EUR'
		);
		$this->repository->save( $product->get_id(), $original );

		$export_a = $this->export_row( $product->get_id() );
		$this->reimport_umc_columns( $product->get_id(), $export_a );
		$export_b = $this->export_row( $product->get_id() );

		foreach ( array_keys( $export_a ) as $key ) {
			if ( 1 === preg_match( '/^umc_fixed_(regular|sale)_/', $key ) ) {
				self::assertSame(
					$export_a[ $key ],
					$export_b[ $key ],
					"UMC column '{$key}' must be identical across export A and export B."
				);
			}
		}
	}

	public function test_projection_round_trip_stable_when_only_a_subset_of_columns_is_reimported(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$original = FixedPriceDocument::from_array(
			array(
				'SEK' => array( 'regular' => '1150.00' ),
				'GBP' => array( 'regular' => '8.50' ),
			),
			'EUR'
		);
		$this->repository->save( $product->get_id(), $original );

		$export_a = $this->export_row( $product->get_id() );

		// Reimport only the SEK columns -- GBP must survive untouched.
		$result = $this->run_csv_import(
			array( 'id', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), $export_a['umc_fixed_regular_sek'] ) )
		);
		self::assertSame( array(), $result['skipped'] ?? array() );

		$export_b = $this->export_row( $product->get_id() );

		self::assertSame( $export_a['umc_fixed_regular_sek'], $export_b['umc_fixed_regular_sek'] );
		self::assertSame( $export_a['umc_fixed_regular_gbp'], $export_b['umc_fixed_regular_gbp'], 'GBP column not mapped in the reimport -- must remain untouched.' );
	}
}
