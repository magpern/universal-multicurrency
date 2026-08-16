<?php
/**
 * Integration coverage for FixedPriceDocumentMerger's persistence path.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Pricing;

use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceDocumentMerger;
use UMC\Pricing\FixedPriceRepository;
use WP_UnitTestCase;

/**
 * The pure merge algorithm is covered in
 * tests/unit/Pricing/FixedPriceDocumentMergerTest.php. This file exercises
 * only `merge_and_save()`'s real read-through-repository, write-through-
 * repository behavior, which requires WordPress post meta.
 *
 * @covers \UMC\Pricing\FixedPriceDocumentMerger
 */
final class FixedPriceDocumentMergerTest extends WP_UnitTestCase {

	/**
	 * @var FixedPriceRepository
	 */
	private FixedPriceRepository $repository;

	/**
	 * @var FixedPriceDocumentMerger
	 */
	private FixedPriceDocumentMerger $merger;

	public function set_up(): void {
		parent::set_up();

		$this->repository = new FixedPriceRepository( 'EUR' );
		$this->merger     = new FixedPriceDocumentMerger( $this->repository );
	}

	public function test_merge_and_save_reads_the_existing_document_and_persists_the_merged_result(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$this->repository->save(
			$product_id,
			FixedPriceDocument::from_array( array( 'GBP' => array( 'regular' => '79' ) ), 'EUR' )
		);

		$document = $this->merger->merge_and_save(
			$product_id,
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$this->assertSame( '1100.00', $document->get_currency( 'SEK' )?->regular() );

		$reread = $this->repository->get( $product_id );
		$this->assertSame( '1100.00', $reread->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '900.00', $reread->get_currency( 'SEK' )?->sale() );
		$this->assertSame( '79.00', $reread->get_currency( 'GBP' )?->regular(), 'An untouched currency must survive the write.' );
	}

	public function test_merge_and_save_persists_an_atomic_revert_when_the_merged_pair_is_invalid(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$this->repository->save(
			$product_id,
			FixedPriceDocument::from_array(
				array(
					'SEK' => array(
						'regular' => '1100',
						'sale'    => '900',
					),
				),
				'EUR'
			)
		);

		$this->merger->merge_and_save(
			$product_id,
			array(
				'SEK' => array(
					'regular' => '100',
					'sale'    => '200',
				),
			),
			'EUR'
		);

		$reread = $this->repository->get( $product_id );
		$this->assertSame( '1100.00', $reread->get_currency( 'SEK' )?->regular(), 'The invalid write must not reach storage — the previous value survives.' );
		$this->assertSame( '900.00', $reread->get_currency( 'SEK' )?->sale() );
	}

	public function test_merge_and_save_updates_the_repository_request_cache(): void {
		$product = new \WC_Product_Simple();
		$product->set_status( 'publish' );
		$product->save();
		$product_id = $product->get_id();

		$this->merger->merge_and_save(
			$product_id,
			array( 'SEK' => array( 'regular' => '1100' ) ),
			'EUR'
		);

		// A second read must observe the just-saved value without a stale
		// request-cache entry masking it.
		$this->assertSame( '1100.00', $this->repository->get( $product_id )->get_currency( 'SEK' )?->regular() );
	}
}
