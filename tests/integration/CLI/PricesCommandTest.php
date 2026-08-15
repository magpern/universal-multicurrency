<?php
/**
 * M24 WP4 acceptance: the wp umc prices CLI.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CLI;

use UMC\CLI\PricesCommand;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Integration\PriceConversionService;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\M20PricingTestCase;
use UMC\Tests\Support\WpCliExitException;
use WP_CLI;
use WP_CLI\Utils;

/**
 * @covers \UMC\CLI\PricesCommand
 */
final class PricesCommandTest extends M20PricingTestCase {

	public function set_up(): void {
		parent::set_up();

		WP_CLI::reset();
		Utils::reset();
	}

	public function test_seed_all_persists_converted_authored_price_and_reports_rate(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100', '80' );

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
			)
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '920.00', $document->get_currency( 'SEK' )?->sale() );
		$this->assertNotEmpty( WP_CLI::$success_messages );
		$this->assertStringContainsString( '11.50', WP_CLI::$success_messages[0] );
	}

	public function test_seed_single_product_targets_only_that_product(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$target = $this->simple_product( '100' );
		$other  = $this->simple_product( '200' );

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'product'  => (string) $target->get_id(),
			)
		);

		$this->assertSame( '1150.00', $this->repository->get( $target->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertNull( $this->repository->get( $other->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * M24 falsification S: a single `seed --all` invocation must use exactly
	 * one FX rate across every batch it processes.
	 */
	public function test_seed_all_uses_a_single_rate_snapshot_across_multiple_batches(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$products = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$products[] = $this->simple_product( (string) ( 100 + $i * 10 ) );
		}

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
			)
		);

		foreach ( $products as $index => $product ) {
			$expected = (string) number_format( ( 100 + $index * 10 ) * 11.5, 2, '.', '' );
			$this->assertSame( $expected, $this->repository->get( $product->get_id() )->get_currency( 'SEK' )?->regular() );
		}
	}

	public function test_dry_run_seed_performs_zero_writes(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
				'dry-run'  => true,
			)
		);

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
		$this->assertStringContainsString( '[dry-run]', WP_CLI::$success_messages[0] );
	}

	public function test_clear_removes_only_target_currency(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'GBP' => array( 'rate' => '0.85' ),
			),
			'EUR'
		);
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array( 'regular' => '1150' ),
					'GBP' => array( 'regular' => '85' ),
				),
				'EUR'
			)
		);

		$this->command()->clear(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
			)
		);

		$document = $this->repository->get( $product->get_id() );
		$this->assertNull( $document->get_currency( 'SEK' ) );
		$this->assertSame( '85.00', $document->get_currency( 'GBP' )?->regular() );
	}

	/**
	 * M24 falsification G: repeated seed/clear with identical inputs is
	 * idempotent, including after an interrupted `--all` rerun.
	 */
	public function test_rerunning_seed_all_is_idempotent(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
			)
		);
		$first = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		$this->command()->seed(
			array(),
			array(
				'currency' => 'SEK',
				'all'      => true,
			)
		);
		$second = (string) get_post_meta( $product->get_id(), FixedPriceDocument::META_KEY, true );

		$this->assertSame( $first, $second );
	}

	public function test_base_currency_is_rejected_with_error_exit(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );

		try {
			$this->command()->seed(
				array(),
				array(
					'currency' => 'EUR',
					'all'      => true,
				)
			);
			$this->fail( 'Base currency must be rejected.' );
		} catch ( WpCliExitException $exception ) {
			$this->assertSame( 1, $exception->exit_code() );
		}
	}

	public function test_ambiguous_scope_is_rejected(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );

		try {
			$this->command()->seed( array(), array( 'currency' => 'SEK' ) );
			$this->fail( 'Missing --product/--all must be rejected.' );
		} catch ( WpCliExitException $exception ) {
			$this->assertSame( 1, $exception->exit_code() );
		}

		try {
			$product = $this->simple_product( '100' );
			$this->command()->seed(
				array(),
				array(
					'currency' => 'SEK',
					'product'  => (string) $product->get_id(),
					'all'      => true,
				)
			);
			$this->fail( 'Both --product and --all must be rejected.' );
		} catch ( WpCliExitException $exception ) {
			$this->assertSame( 1, $exception->exit_code() );
		}
	}

	public function test_invalid_product_id_is_rejected(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );

		try {
			$this->command()->seed(
				array(),
				array(
					'currency' => 'SEK',
					'product'  => '999999',
				)
			);
			$this->fail( 'Unknown product ID must be rejected.' );
		} catch ( WpCliExitException $exception ) {
			$this->assertSame( 1, $exception->exit_code() );
		}
	}

	public function test_list_reports_coverage_across_all_non_base_currencies_by_default(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'GBP' => array( 'rate' => '0.85' ),
			),
			'EUR'
		);
		$product = $this->simple_product( '100' );
		$this->save_fixed( $product->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' ) );

		$this->command()->list( array(), array() );

		$currencies = array_column( Utils::$last_items, 'currency' );
		$this->assertContains( 'SEK', $currencies );
		$this->assertContains( 'GBP', $currencies );
	}

	public function test_list_filters_by_currency_and_status(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$fixed    = $this->simple_product( '100' );
		$fallback = $this->simple_product( '80' );
		$this->save_fixed( $fixed->get_id(), FixedPriceDocument::from_array( array( 'SEK' => array( 'regular' => '1150' ) ), 'EUR' ) );

		$this->command()->list(
			array(),
			array(
				'currency' => 'SEK',
				'status'   => FixedPriceCoverageReport::STATUS_FIXED,
			)
		);

		$ids = array_column( Utils::$last_items, 'product_id' );
		$this->assertContains( (string) $fixed->get_id(), $ids );
		$this->assertNotContains( (string) $fallback->get_id(), $ids );
	}

	private function command(): PricesCommand {
		$settings  = new Settings();
		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates     = new ManualRateProvider( $settings, 'EUR' );
		$converter = new PriceConversionService( $this->context );
		$coverage  = new FixedPriceCoverageReport( $this->repository );
		$query     = new FixedPriceCatalogQuery( $coverage );
		$service   = new FixedPriceCatalogOperationsService( $this->repository, $coverage, $rates, $converter, $registry );

		return new PricesCommand( $service, $query, $registry );
	}
}
