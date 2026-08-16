<?php
/**
 * M25 WP1: empirical characterization of WooCommerce's own native CSV
 * importer behavior — no UMC code under test here.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Csv;

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceRepository;
use UMC\Tests\Support\WcCsvImportTestTrait;
use WP_UnitTestCase;

/**
 * ADR-0030 / architecture doc §5 claim: WooCommerce's own generic
 * `meta:`-prefixed CSV import column mechanism writes directly to arbitrary
 * post meta, including underscore-prefixed keys such as `_umc_fixed_prices`,
 * with no `is_protected_meta()` check anywhere in the import write path.
 *
 * This is deliberately a WooCommerce-native-behavior characterization suite,
 * not a UMC regression suite: no UMC defense exists yet at this point in the
 * milestone (that is WP4's job). Every test here is expected to PASS,
 * because passing is what proves the vulnerability is real — a "the bypass
 * writes raw content" assertion succeeding is the empirical finding itself.
 *
 * @covers \WC_Product_CSV_Importer
 */
final class WooCommerceCsvImporterNativeBehaviorTest extends WP_UnitTestCase {

	use WcCsvImportTestTrait;

	public function tear_down(): void {
		$this->clean_up_csv_temp_files();
		parent::tear_down();
	}

	/**
	 * Core bypass proof: a product with no existing `_umc_fixed_prices` meta
	 * ends up with that meta populated verbatim from a `meta:` column, with
	 * zero JSON validation, zero schema check, zero involvement of
	 * FixedPriceValidator/FixedPriceDocument — WooCommerce's importer does
	 * not know or care that the key means anything to this plugin.
	 */
	public function test_meta_prefixed_column_writes_arbitrary_unvalidated_content_to_the_storage_key(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();
		$product_id = $product->get_id();

		$this->assertSame( '', (string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true ), 'Precondition: no existing fixed-price meta.' );

		$malicious_payload = 'not-even-json;arbitrary-unvalidated-content';

		$result = $this->run_csv_import(
			array( 'id', 'regular_price', 'meta:_umc_fixed_prices' ),
			array(
				array( (string) $product_id, '10', $malicious_payload ),
			)
		);

		$this->assertSame( array(), $result['failed'], 'Import must succeed at the WooCommerce level (a bad UMC-shaped value is not a WC import failure).' );
		$this->assertSame(
			$malicious_payload,
			(string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true ),
			'WooCommerce\'s generic meta: importer wrote the raw cell verbatim, with no validation of any kind.'
		);
	}

	/**
	 * The same bypass with a structurally plausible (but semantically
	 * attacker-controlled) JSON payload, proving the smuggled value is not
	 * just unvalidated garbage but can carry a fully-formed, attacker-chosen
	 * fixed-price document that FixedPriceDocument::from_storage() would
	 * happily parse as legitimate on the next read — this is the real-world
	 * shape of the attack, not just a corruption demo.
	 */
	public function test_meta_prefixed_column_can_smuggle_a_fully_formed_attacker_authored_document(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();
		$product_id = $product->get_id();

		$forged = wp_json_encode(
			array(
				'schema_version' => 1,
				'currencies'     => array(
					'SEK' => array(
						'regular' => '0.01',
						'sale'    => '0.01',
					),
				),
			)
		);

		$this->run_csv_import(
			array( 'id', 'regular_price', 'meta:_umc_fixed_prices' ),
			array(
				array( (string) $product_id, '10', $forged ),
			)
		);

		$this->assertSame( $forged, (string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true ) );

		// Confirms the forged document is not merely stored as an inert
		// string but is structurally indistinguishable from a legitimately
		// authored one to the domain layer's own reader.
		$repository = new FixedPriceRepository( 'EUR' );
		$document   = $repository->get( $product_id );
		$this->assertSame( '0.01', $document->get_currency( 'SEK' )?->regular() );
	}

	/**
	 * Characterizes exactly why an unconditional `delete_meta_data()` guard
	 * would be as destructive as the attack (architecture doc §5): the
	 * generic importer's write does not add a duplicate meta row, it mutates
	 * the *existing* WC_Meta_Data entry's value in place, keeping the same
	 * real database meta_id. A legitimate pre-existing document is already
	 * gone from the object's in-memory state by the time any defense hook
	 * could run — this is direct empirical proof of `WC_Data::update_meta_data()`'s
	 * documented "match by key, mutate value, keep meta_id" behavior via a
	 * real product save, not just a read of the WC_Data source.
	 */
	public function test_meta_prefixed_column_overwrites_an_existing_legitimate_document_in_place_same_meta_id(): void {
		global $wpdb;

		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();
		$product_id = $product->get_id();

		$repository = new FixedPriceRepository( 'EUR' );
		$repository->save(
			$product_id,
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1100' ) ), 'EUR' )
		);

		$meta_id_before = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
				$product_id,
				FixedPriceDocument::META_KEY
			)
		);
		$this->assertGreaterThan( 0, $meta_id_before, 'Precondition: the legitimate document has a real meta_id.' );

		$malicious_payload = 'attacker-controlled-overwrite';

		$this->run_csv_import(
			array( 'id', 'regular_price', 'meta:_umc_fixed_prices' ),
			array(
				array( (string) $product_id, '10', $malicious_payload ),
			)
		);

		$meta_id_after = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
				$product_id,
				FixedPriceDocument::META_KEY
			)
		);

		$this->assertSame(
			$malicious_payload,
			(string) get_post_meta( $product_id, FixedPriceDocument::META_KEY, true ),
			'The pre-existing legitimate document was overwritten, not merely appended to.'
		);
		$this->assertSame(
			$meta_id_before,
			$meta_id_after,
			'The write mutated the existing meta row in place (same meta_id) rather than adding a duplicate row — ' .
			'this is exactly why an unconditional delete_meta_data() defense would be indistinguishable from the attack.'
		);
		$this->assertSame(
			1,
			(int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$product_id,
					FixedPriceDocument::META_KEY
				)
			),
			'Exactly one meta row for this key must exist — no duplicate row was added.'
		);
	}

	/**
	 * New product, malicious raw column only, no pre-existing database
	 * value: confirms the "never persists" half of the resync-defense table
	 * (architecture doc §5) is a real, reachable state to defend against —
	 * without a defense, it currently persists exactly as read here.
	 */
	public function test_meta_prefixed_column_on_a_brand_new_product_persists_with_no_prior_database_value(): void {
		$result = $this->run_csv_import(
			array( 'regular_price', 'meta:_umc_fixed_prices' ),
			array(
				array( '10', 'brand-new-product-malicious-value' ),
			),
			array( 'update_existing' => false )
		);

		$this->assertCount( 1, $result['imported'], 'A new product must be created.' );
		$new_id = $result['imported'][0];

		$this->assertSame(
			'brand-new-product-malicious-value',
			(string) get_post_meta( $new_id, FixedPriceDocument::META_KEY, true )
		);
	}

	/**
	 * Architecture doc §5 claim: WooCommerce's own mapping auto-selector
	 * pre-selects the generic-meta import route by default for a column
	 * named `meta:_umc_fixed_prices`, requiring no manual remapping.
	 * Confirmed true, via two independent auto-mapper code paths:
	 *
	 * - WooCommerce's own generic-meta export label, "Meta: <key>" (as
	 *   produced verbatim by WC_Product_CSV_Exporter::prepare_meta_for_export()),
	 *   matches the auto-mapper's special-column regex built from the
	 *   translatable "Meta: %s" string and is rewritten to `meta:<key>`.
	 * - The bare internal-column-id spelling `meta:_umc_fixed_prices` (no
	 *   space) does not match that regex, but is auto-mapped anyway via a
	 *   *different* mechanism: unmatched headers fall back to
	 *   `strtolower($field)` as the mapped key, and this literal string is
	 *   already lowercase, so the fallback identity value happens to equal
	 *   the internal mapped-column id on its own. Either spelling — WC's own
	 *   export label, or the raw internal syntax a spreadsheet-literate
	 *   attacker would type expecting it to "just work" — reaches the
	 *   vulnerable generic-meta import route with zero manual remapping.
	 */
	public function test_wc_native_generic_meta_export_label_auto_maps_to_the_generic_meta_import_route(): void {
		$controller = new \WC_Product_CSV_Importer_Controller();
		$reflection = new \ReflectionMethod( $controller, 'auto_map_columns' );
		$reflection->setAccessible( true );

		$mapped = $reflection->invoke( $controller, array( 'ID', 'Meta: _umc_fixed_prices' ) );

		$this->assertSame(
			'meta:_umc_fixed_prices',
			$mapped[1],
			'A raw header exactly matching WooCommerce\'s own generic-meta export label auto-maps to the ' .
			'generic-meta import route with no manual remapping step.'
		);
	}

	/**
	 * Sibling to the above: the bare internal-column-id spelling (no space
	 * after the colon) also auto-maps to the generic-meta import route, via
	 * the auto-mapper's "unmatched header falls back to its own lowercased
	 * text" default rather than the "Meta: %s" label regex — the CSV author
	 * does not need to know WooCommerce's export label format at all; typing
	 * the storage key's own documented meta:-prefixed syntax is sufficient
	 * on its own.
	 */
	public function test_bare_internal_meta_column_id_spelling_also_auto_maps(): void {
		$controller = new \WC_Product_CSV_Importer_Controller();
		$reflection = new \ReflectionMethod( $controller, 'auto_map_columns' );
		$reflection->setAccessible( true );

		$mapped = $reflection->invoke( $controller, array( 'ID', 'meta:_umc_fixed_prices' ) );

		$this->assertSame(
			'meta:_umc_fixed_prices',
			$mapped[1],
			'The bare "meta:_key" spelling auto-maps too, via the auto-mapper\'s unmatched-header fallback ' .
			'(it lowercases to itself), independent of the "Meta: %s" label regex.'
		);
	}
}
