<?php
/**
 * M25 WP4 acceptance: WooCommerce product CSV import integration for
 * structured fixed-price columns (architecture doc §6).
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
use UMC\Tests\Support\UmcCsvImportLogTestTrait;
use UMC\Tests\Support\WcCsvImportTestTrait;
use WP_UnitTestCase;

/**
 * @covers \UMC\Pricing\FixedPriceCsvIntegration
 */
final class FixedPriceCsvImportTest extends WP_UnitTestCase {

	use WcCsvImportTestTrait;
	use UmcCsvImportLogTestTrait;

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
		remove_all_filters( 'woocommerce_csv_product_import_mapping_options' );
		remove_all_filters( 'woocommerce_csv_product_import_mapping_default_columns' );
		remove_all_filters( 'woocommerce_product_import_pre_insert_product_object' );
		remove_all_actions( 'woocommerce_product_import_inserted_product_object' );
		parent::tear_down();
	}

	// -----------------------------------------------------------------
	// Simple product create/update.
	// -----------------------------------------------------------------

	public function test_simple_product_create_sets_mapped_structured_columns(): void {
		$result = $this->run_csv_import(
			array( 'regular_price', 'umc_fixed_regular_sek', 'umc_fixed_sale_sek' ),
			array( array( '10', '1150', '920' ) ),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported'] );
		$id       = $result['imported'][0];
		$document = $this->repository->get( $id );

		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '920.00', $document->get_currency( 'SEK' )?->sale() );
	}

	public function test_simple_product_update_sets_mapped_structured_columns(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$result = $this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '20', '1150' ) )
		);

		$this->assertSame( array( $product->get_id() ), $result['updated'] );
		$this->assertSame( '1150.00', $this->repository->get( $product->get_id() )->get_currency( 'SEK' )?->regular() );
	}

	// -----------------------------------------------------------------
	// Variation create/update, parent/sibling isolation.
	// -----------------------------------------------------------------

	public function test_variation_create_sets_structured_columns(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$result = $this->run_csv_import(
			array( 'type', 'parent_id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( 'variation', 'id:' . $parent->get_id(), '50', '575' ) ),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported_variations'] );
		$variation_id = $result['imported_variations'][0];

		$this->assertSame( '575.00', $this->repository->get( $variation_id )->get_currency( 'SEK' )?->regular() );
		$this->assertNull( $this->repository->get( $parent->get_id() )->get_currency( 'SEK' ), 'The variable parent must never receive a fixed-price document.' );
	}

	public function test_variation_update_sets_structured_columns_and_does_not_touch_parent_or_sibling(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation_a = new \WC_Product_Variation();
		$variation_a->set_parent_id( $parent->get_id() );
		$variation_a->set_regular_price( '50' );
		$variation_a->save();

		$variation_b = new \WC_Product_Variation();
		$variation_b->set_parent_id( $parent->get_id() );
		$variation_b->set_regular_price( '100' );
		$variation_b->save();

		$result = $this->run_csv_import(
			array( 'id', 'type', 'parent_id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $variation_a->get_id(), 'variation', 'id:' . $parent->get_id(), '75', '575' ) )
		);

		$this->assertSame( array( $variation_a->get_id() ), $result['updated'] );
		$this->assertSame( '575.00', $this->repository->get( $variation_a->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertNull( $this->repository->get( $variation_b->get_id() )->get_currency( 'SEK' ) );
		$this->assertNull( $this->repository->get( $parent->get_id() )->get_currency( 'SEK' ) );
	}

	// -----------------------------------------------------------------
	// Blank vs. malformed distinction.
	// -----------------------------------------------------------------

	public function test_genuinely_blank_mapped_cell_clears_the_field(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1150',
						'sale'    => '900',
					),
				),
				'EUR'
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_sale_sek' ),
			array( array( (string) $product->get_id(), '10', '' ) )
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular(), 'Untouched field must be preserved.' );
		$this->assertSame( '', $document->get_currency( 'SEK' )?->sale(), 'A genuinely blank mapped cell must clear the field.' );
	}

	public function test_malformed_non_blank_cell_is_skipped_and_logged_field_stays_at_previous_value(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' )
		);

		$log = $this->new_umc_csv_import_log_entries(
			function () use ( $product ) {
				$this->run_csv_import(
					array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
					array( array( (string) $product->get_id(), '10', '1,234' ) )
				);
			}
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular(), 'A malformed cell must never clear or zero the field.' );
		$this->assertNotSame( '', $log, 'A warning must be logged on the umc-csv-import channel.' );
		$this->assertStringContainsString( (string) $product->get_id(), $log );
		$this->assertStringContainsString( 'SEK', $log );
		$this->assertStringContainsString( 'regular', $log );
	}

	public function test_malformed_cell_on_a_field_with_no_previous_value_is_skipped_and_stays_absent(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '10', 'abc' ) )
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	// -----------------------------------------------------------------
	// Zero valid, negative rejected.
	// -----------------------------------------------------------------

	public function test_zero_is_accepted_as_a_valid_price(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '10', '0' ) )
		);

		$this->assertSame( '0.00', $this->repository->get( $product->get_id() )->get_currency( 'SEK' )?->regular() );
	}

	public function test_negative_is_rejected_and_logged(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$log = $this->new_umc_csv_import_log_entries(
			function () use ( $product ) {
				$this->run_csv_import(
					array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
					array( array( (string) $product->get_id(), '10', '-5' ) )
				);
			}
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
		$this->assertNotSame( '', $log, 'A warning must be logged on the umc-csv-import channel.' );
	}

	// -----------------------------------------------------------------
	// Partial update preserves untouched currencies; explicit clear.
	// -----------------------------------------------------------------

	public function test_partial_update_preserves_untouched_currencies(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array( 'regular' => '1150' ),
					'GBP' => array( 'regular' => '79' ),
				),
				'EUR'
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '10', '1200' ) )
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1200.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '79.00', $document->get_currency( 'GBP' )?->regular(), 'A currency with no mapped column this session must be untouched.' );
	}

	public function test_explicit_clear_of_both_fields_removes_the_currency_entry(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1150',
						'sale'    => '900',
					),
				),
				'EUR'
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek', 'umc_fixed_sale_sek' ),
			array( array( (string) $product->get_id(), '10', '', '' ) )
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	// -----------------------------------------------------------------
	// Sale > regular: atomic per-currency rejection/revert (3 shapes).
	// -----------------------------------------------------------------

	public function test_sale_only_update_that_would_invert_the_pair_is_rejected_atomically(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '100',
						'sale'    => '80',
					),
				),
				'EUR'
			)
		);

		$log = $this->new_umc_csv_import_log_entries(
			function () use ( $product ) {
				$this->run_csv_import(
					array( 'id', 'regular_price', 'umc_fixed_sale_sek' ),
					// New sale (150) would exceed the existing regular (100).
					array( array( (string) $product->get_id(), '10', '150' ) )
				);
			}
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '100.00', $document->get_currency( 'SEK' )?->regular(), 'Rejected pair must revert the whole currency entry.' );
		$this->assertSame( '80.00', $document->get_currency( 'SEK' )?->sale() );
		$this->assertNotSame( '', $log, 'A warning must be logged on the umc-csv-import channel.' );
	}

	public function test_regular_only_update_that_would_invert_the_pair_is_rejected_atomically(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '100',
						'sale'    => '80',
					),
				),
				'EUR'
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			// New regular (50) would be below the existing sale (80).
			array( array( (string) $product->get_id(), '10', '50' ) )
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '100.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '80.00', $document->get_currency( 'SEK' )?->sale() );
	}

	public function test_both_together_inverted_pair_is_rejected_atomically(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '100',
						'sale'    => '80',
					),
				),
				'EUR'
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek', 'umc_fixed_sale_sek' ),
			array( array( (string) $product->get_id(), '10', '50', '150' ) )
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '100.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '80.00', $document->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * A brand-new currency entry (no previous state) that is inverted from
	 * the start must never be created.
	 */
	public function test_inverted_pair_on_a_currency_with_no_previous_entry_is_never_created(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek', 'umc_fixed_sale_sek' ),
			array( array( (string) $product->get_id(), '10', '50', '150' ) )
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	// -----------------------------------------------------------------
	// Currency validity: unconfigured, disabled-but-configured, base drift.
	// -----------------------------------------------------------------

	public function test_unconfigured_currency_is_skipped_and_logged_never_silently_written(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$log = $this->new_umc_csv_import_log_entries(
			function () use ( $product ) {
				$this->run_csv_import(
					array( 'id', 'regular_price', 'umc_fixed_regular_nok' ),
					array( array( (string) $product->get_id(), '10', '1150' ) )
				);
			}
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'NOK' ) );
		$this->assertNotSame( '', $log, 'A warning must be logged on the umc-csv-import channel.' );
		$this->assertStringContainsString( 'NOK', $log );
	}

	public function test_disabled_but_configured_currency_has_full_read_write_parity_with_enabled(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_gbp' ),
			array( array( (string) $product->get_id(), '10', '79' ) )
		);

		$this->assertSame( '79.00', $this->repository->get( $product->get_id() )->get_currency( 'GBP' )?->regular(), 'A disabled-but-configured currency must have full write access, matching the data-layer rule (only the editor UI is read-only for disabled currencies).' );
	}

	/**
	 * A currency that has become the store's base currency since export must
	 * be rejected the same way base currency is always rejected.
	 */
	public function test_currency_that_became_base_since_export_is_rejected(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		// Simulate configuration drift: SEK was foreign at export time, but
		// has since become the store's base currency. Swap in a fresh
		// integration bound to a registry whose base is now SEK — the same
		// live-rebuild-per-request CurrencyRegistry always provides in
		// production, since Plugin.php rebuilds it from woocommerce_currency
		// on every request.
		remove_all_filters( 'woocommerce_csv_product_import_mapping_options' );
		remove_all_filters( 'woocommerce_csv_product_import_mapping_default_columns' );
		remove_all_filters( 'woocommerce_product_import_pre_insert_product_object' );
		remove_all_actions( 'woocommerce_product_import_inserted_product_object' );

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'SEK', 2 ) );
		( new FixedPriceCsvIntegration( $this->repository, $registry ) )->register();

		$log = $this->new_umc_csv_import_log_entries(
			function () use ( $product ) {
				// The raw header spells the internal column id directly (the
				// same fallback-identity mapping WP1 characterized for the
				// meta: bypass), so the cell reaches $data even though this
				// registry no longer offers umc_fixed_regular_sek as an
				// auto-mapped or manually-selectable column.
				$this->run_csv_import(
					array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
					array( array( (string) $product->get_id(), '10', '1150' ) )
				);
			}
		);

		$raw = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );
		$this->assertSame( '', $raw, 'A currency that has become the base currency must never be written as a foreign fixed price.' );
		$this->assertNotSame( '', $log, 'A warning must be logged on the umc-csv-import channel.' );
	}

	// -----------------------------------------------------------------
	// Column mapping (auto-map by header text).
	// -----------------------------------------------------------------

	public function test_export_label_auto_maps_to_the_structured_column_on_import(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$controller = new \WC_Product_CSV_Importer_Controller();
		$reflection = new \ReflectionMethod( $controller, 'auto_map_columns' );
		$reflection->setAccessible( true );

		$mapped = $reflection->invoke( $controller, array( 'id', 'regular_price', 'UMC Fixed Regular Price (SEK)' ) );

		$this->assertSame( 'umc_fixed_regular_sek', $mapped[2] );
	}

	// -----------------------------------------------------------------
	// Performance / idempotency (behavioral half; structural call-count
	// guard lives in FixedPriceCsvIntegrationGuardTest).
	// -----------------------------------------------------------------

	public function test_row_with_no_umc_columns_mapped_never_creates_fixed_price_meta(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price' ),
			array( array( (string) $product->get_id(), '20' ) )
		);

		$this->assertSame( '', (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true ) );
	}

	public function test_repeated_identical_import_does_not_rewrite_the_stored_document(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '10', '1150' ) )
		);
		$first_json = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		$write_count = 0;
		$counter     = function ( $meta_id, $object_id, $meta_key ) use ( &$write_count, $product ) {
			unset( $meta_id );
			if ( (int) $object_id === $product->get_id() && FixedPriceDocument::META_KEY === $meta_key ) {
				++$write_count;
			}
		};
		add_action( 'updated_post_meta', $counter, 10, 3 );
		add_action( 'added_post_meta', $counter, 10, 3 );
		add_action( 'deleted_post_meta', $counter, 10, 3 );

		$this->repository->clear_cache();
		$this->run_csv_import(
			array( 'id', 'regular_price', 'umc_fixed_regular_sek' ),
			array( array( (string) $product->get_id(), '10', '1150' ) )
		);

		remove_action( 'updated_post_meta', $counter, 10 );
		remove_action( 'added_post_meta', $counter, 10 );
		remove_action( 'deleted_post_meta', $counter, 10 );

		$second_json = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );
		$this->assertSame( $first_json, $second_json );
		$this->assertSame( 0, $write_count, 'A repeated, unchanged import must not perform a second meta write for _umc_fixed_prices.' );
	}
}
