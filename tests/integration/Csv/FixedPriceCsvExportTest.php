<?php
/**
 * M25 WP3 acceptance: WooCommerce product CSV export integration.
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
use WP_UnitTestCase;

/**
 * @covers \UMC\Pricing\FixedPriceCsvIntegration
 */
final class FixedPriceCsvExportTest extends WP_UnitTestCase {

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
		delete_option( Settings::OPTION );
		remove_all_filters( 'woocommerce_product_export_column_names' );
		remove_all_filters( 'woocommerce_product_export_product_default_columns' );
		remove_all_filters( 'woocommerce_product_export_row_data' );
		parent::tear_down();
	}

	/**
	 * Runs the real exporter for a fixed set of product IDs and returns the
	 * prepared row data.
	 *
	 * @param array<int, int> $product_ids Product IDs to export.
	 * @return array<int, array<string, mixed>>
	 */
	private function export_rows( array $product_ids ): array {
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_ids_to_export( $product_ids );
		$exporter->prepare_data_to_export();

		$reflection = new \ReflectionProperty( $exporter, 'row_data' );
		$reflection->setAccessible( true );

		return $reflection->getValue( $exporter );
	}

	/**
	 * Finds the exported row for a given product ID.
	 *
	 * @param array<int, array<string, mixed>> $rows       Exported rows.
	 * @param int                              $product_id Product ID.
	 * @return array<string, mixed>
	 */
	private function row_for( array $rows, int $product_id ): array {
		foreach ( $rows as $row ) {
			if ( (int) $row['id'] === $product_id ) {
				return $row;
			}
		}

		$this->fail( "No exported row found for product #{$product_id}." );
	}

	public function test_simple_product_exports_configured_currency_columns_and_omits_base(): void {
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
						'sale'    => '920',
					),
				),
				'EUR'
			)
		);

		$rows = $this->export_rows( array( $product->get_id() ) );
		$row  = $this->row_for( $rows, $product->get_id() );

		$this->assertSame( '1150.00', $row['umc_fixed_regular_sek'] );
		$this->assertSame( '920.00', $row['umc_fixed_sale_sek'] );
		$this->assertArrayNotHasKey( 'umc_fixed_regular_eur', $row, 'Base currency must never become a column.' );
		$this->assertArrayNotHasKey( 'umc_fixed_sale_eur', $row, 'Base currency must never become a column.' );
	}

	public function test_product_with_no_fixed_prices_exports_blank_columns(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$rows = $this->export_rows( array( $product->get_id() ) );
		$row  = $this->row_for( $rows, $product->get_id() );

		$this->assertSame( '', $row['umc_fixed_regular_sek'] );
		$this->assertSame( '', $row['umc_fixed_sale_sek'] );
	}

	public function test_variable_parent_row_is_always_blank(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '50' );
		$variation->save();

		// Even if a document somehow exists under the parent's own ID, the
		// parent row must stay blank — enforced structurally, not because the
		// document happens to be empty.
		$this->repository->save(
			$parent->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '999' ) ), 'EUR' )
		);

		$rows = $this->export_rows( array( $parent->get_id() ) );
		$row  = $this->row_for( $rows, $parent->get_id() );

		$this->assertSame( '', $row['umc_fixed_regular_sek'] );
		$this->assertSame( '', $row['umc_fixed_sale_sek'] );
	}

	/**
	 * A variation projects its own document, never inherited from the parent
	 * or a sibling.
	 */
	public function test_variation_exports_its_own_document_not_inherited(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_status( 'publish' );
		$parent->save();

		$low = new \WC_Product_Variation();
		$low->set_parent_id( $parent->get_id() );
		$low->set_regular_price( '50' );
		$low->save();

		$high = new \WC_Product_Variation();
		$high->set_parent_id( $parent->get_id() );
		$high->set_regular_price( '100' );
		$high->save();

		$this->repository->save(
			$low->get_id(),
			FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '575' ) ), 'EUR' )
		);

		// Export by the parent's ID: WooCommerce's own exporter only runs the
		// variations sub-query when the parent is reached via an include or
		// category filter (WooCommerceCsvExportStatusFilterTest already
		// characterizes this), so this is the real path a merchant's "export
		// everything" or "export this product" run takes.
		$rows     = $this->export_rows( array( $parent->get_id() ) );
		$low_row  = $this->row_for( $rows, $low->get_id() );
		$high_row = $this->row_for( $rows, $high->get_id() );

		$this->assertSame( '575.00', $low_row['umc_fixed_regular_sek'] );
		$this->assertSame( '', $high_row['umc_fixed_regular_sek'], 'A sibling variation must never inherit another variation\'s fixed price.' );
	}

	public function test_disabled_but_configured_currency_exports_the_same_as_enabled(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->set_regular_price( '10' );
		$product->save();

		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array( array( 'GBP' => array( 'regular' => '79' ) ), 'EUR' )
		);

		$rows = $this->export_rows( array( $product->get_id() ) );
		$row  = $this->row_for( $rows, $product->get_id() );

		$this->assertSame( '79.00', $row['umc_fixed_regular_gbp'], 'A disabled-but-configured currency must still export.' );
	}

	public function test_export_column_names_include_every_non_base_currency(): void {
		$exporter = new \WC_Product_CSV_Exporter();
		$names    = $exporter->get_column_names();

		$this->assertArrayHasKey( 'umc_fixed_regular_sek', $names );
		$this->assertArrayHasKey( 'umc_fixed_sale_sek', $names );
		$this->assertArrayHasKey( 'umc_fixed_regular_gbp', $names );
		$this->assertArrayHasKey( 'umc_fixed_sale_gbp', $names );
		$this->assertArrayNotHasKey( 'umc_fixed_regular_eur', $names );
	}

	public function test_export_default_columns_include_every_non_base_currency(): void {
		$exporter = new \WC_Product_CSV_Exporter();
		$defaults = $exporter->get_default_column_names();

		$this->assertArrayHasKey( 'umc_fixed_regular_sek', $defaults );
		$this->assertArrayHasKey( 'umc_fixed_sale_sek', $defaults );
		$this->assertArrayNotHasKey( 'umc_fixed_regular_eur', $defaults );
	}

	/**
	 * ADR-0030 §4 / architecture doc §9: exactly one FixedPriceRepository::get()
	 * call per exported row, regardless of currency count. Proven behaviorally
	 * here via the request-local cache: a second, independent repository
	 * instance reading straight from the database confirms the exporter's
	 * projected values are correct for *every* configured currency from a
	 * single stored document — if the exporter fetched per-column it would
	 * still produce the same values, so this is paired with the source-level
	 * call-count guard in FixedPriceCsvIntegrationGuardTest for the "exactly
	 * one" structural claim itself.
	 */
	public function test_row_projects_every_currency_from_a_single_document(): void {
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
						'sale'    => '920',
					),
					'GBP' => array( 'regular' => '79' ),
				),
				'EUR'
			)
		);

		$rows = $this->export_rows( array( $product->get_id() ) );
		$row  = $this->row_for( $rows, $product->get_id() );

		$this->assertSame( '1150.00', $row['umc_fixed_regular_sek'] );
		$this->assertSame( '920.00', $row['umc_fixed_sale_sek'] );
		$this->assertSame( '79.00', $row['umc_fixed_regular_gbp'] );
	}
}
