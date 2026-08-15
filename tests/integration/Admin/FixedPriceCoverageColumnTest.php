<?php
/**
 * M24 WP3 acceptance: the passive Products-list coverage column.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\FixedPriceCoverageColumn;
use UMC\Admin\SettingsPage;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Tests\Support\M20PricingTestCase;

/**
 * @covers \UMC\Admin\FixedPriceCoverageColumn
 */
final class FixedPriceCoverageColumnTest extends M20PricingTestCase {

	public function test_column_is_added_only_when_non_base_currencies_exist(): void {
		$this->activate( array(), 'EUR' );
		$column = new FixedPriceCoverageColumn( new FixedPriceCoverageReport( $this->repository ), $this->registry_for_test() );

		$this->assertArrayNotHasKey( FixedPriceCoverageColumn::COLUMN_ID, $column->add_column( array( 'name' => 'Name' ) ) );

		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$column = new FixedPriceCoverageColumn( new FixedPriceCoverageReport( $this->repository ), $this->registry_for_test() );

		$this->assertArrayHasKey( FixedPriceCoverageColumn::COLUMN_ID, $column->add_column( array( 'name' => 'Name' ) ) );
	}

	public function test_render_column_outputs_a_badge_per_non_base_currency_with_a_prefiltered_link(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'GBP' => array( 'rate' => '0.85' ),
			),
			'EUR'
		);
		$product = $this->simple_product( '100' );
		$this->save_fixed( $product->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' ) );

		$column = new FixedPriceCoverageColumn( new FixedPriceCoverageReport( $this->repository ), $this->registry_for_test() );

		ob_start();
		$column->render_column( FixedPriceCoverageColumn::COLUMN_ID, $product->get_id() );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'SEK: Fixed', $output );
		$this->assertStringContainsString( 'GBP: FX fallback', $output );
		$this->assertStringContainsString( 'section=' . SettingsPage::SECTION_FIXED_PRICING, $output );
		$this->assertStringContainsString( 'umc_fp_currency=SEK', $output );
	}

	public function test_render_column_ignores_other_columns(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );
		$column  = new FixedPriceCoverageColumn( new FixedPriceCoverageReport( $this->repository ), $this->registry_for_test() );

		ob_start();
		$column->render_column( 'name', $product->get_id() );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	private function registry_for_test(): \UMC\CurrencyRegistry {
		$settings = new \UMC\Settings();

		return new \UMC\CurrencyRegistry( $settings, new \UMC\Currency( 'EUR', 2 ) );
	}
}
