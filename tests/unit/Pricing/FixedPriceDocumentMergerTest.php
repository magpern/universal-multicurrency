<?php
/**
 * Unit tests for the shared fixed-price document mutation algorithm.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceDocumentMerger;
use UMC\Pricing\FixedPriceRepository;

/**
 * Exercises {@see FixedPriceDocumentMerger::merge()} directly — the pure,
 * WooCommerce-agnostic algorithm extracted from
 * {@see \UMC\Admin\ProductFixedPricesPanel::persist_submission()} (ADR-0030)
 * — without any WordPress/repository dependency. Persistence
 * (`merge_and_save()`) is covered separately in
 * tests/integration/Pricing/FixedPriceDocumentMergerTest.php, since it
 * requires a real `FixedPriceRepository` backed by WordPress post meta.
 *
 * @covers \UMC\Pricing\FixedPriceDocumentMerger
 */
final class FixedPriceDocumentMergerTest extends TestCase {

	/**
	 * @var FixedPriceDocumentMerger
	 */
	private FixedPriceDocumentMerger $merger;

	protected function setUp(): void {
		parent::setUp();

		// merge() never touches the repository; the constructor dependency
		// exists only for merge_and_save(), which is not exercised here.
		$this->merger = new FixedPriceDocumentMerger( new FixedPriceRepository( 'EUR' ) );
	}

	/**
	 * A currency absent from $touched is carried through unchanged from the
	 * existing document — the seed-from-existing step.
	 */
	public function test_untouched_currency_is_carried_through_unchanged(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$result = $this->merger->merge( $existing, array(), 'EUR' );

		$this->assertSame( '1100', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '900', $result->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * A touched currency fully replaces its existing entry.
	 */
	public function test_touched_currency_replaces_existing_entry(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$result = $this->merger->merge(
			$existing,
			array(
				'SEK' => array(
					'regular' => '1200',
					'sale'    => '1000',
				),
			),
			'EUR'
		);

		$this->assertSame( '1200', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '1000', $result->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * Atomic reject-and-revert: an invalid final pair (sale > regular)
	 * leaves the currency exactly as it was seeded from the existing
	 * document — never a partial write of the invalid values.
	 */
	public function test_invalid_final_pair_reverts_to_the_previous_stored_value(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$result = $this->merger->merge(
			$existing,
			array(
				'SEK' => array(
					'regular' => '100',
					'sale'    => '200',
				),
			),
			'EUR'
		);

		$this->assertSame(
			'1100',
			$result->get_currency( 'SEK' )?->regular(),
			'The previous stored regular price must survive an invalid pair, not be overwritten with the invalid regular value.'
		);
		$this->assertSame(
			'900',
			$result->get_currency( 'SEK' )?->sale(),
			'The previous stored sale price must survive an invalid pair, not be overwritten with the invalid sale value.'
		);
	}

	/**
	 * Atomic reject when there is no previous value to revert to: an
	 * invalid pair for a currency with no existing entry results in no
	 * entry at all — never a partial write.
	 */
	public function test_invalid_final_pair_with_no_existing_entry_results_in_no_entry(): void {
		$result = $this->merger->merge(
			FixedPriceDocument::empty(),
			array(
				'SEK' => array(
					'regular' => '100',
					'sale'    => '200',
				),
			),
			'EUR'
		);

		$this->assertNull( $result->get_currency( 'SEK' ) );
	}

	/**
	 * Both sub-fields normalizing to blank clears the currency entirely,
	 * matching the product editor's long-standing "blank both fields to
	 * clear" behavior.
	 */
	public function test_both_fields_blank_clears_an_existing_entry(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$result = $this->merger->merge(
			$existing,
			array(
				'SEK' => array(
					'regular' => '',
					'sale'    => '',
				),
			),
			'EUR'
		);

		$this->assertNull( $result->get_currency( 'SEK' ) );
	}

	/**
	 * A null entry explicitly removes the currency — the shape
	 * {@see \UMC\Pricing\FixedPriceCatalogOperationsService::clear()} uses.
	 */
	public function test_null_entry_removes_the_currency(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array( 'regular' => '1100' ),
				'GBP' => array( 'regular' => '85' ),
			),
			'EUR'
		);

		$result = $this->merger->merge( $existing, array( 'SEK' => null ), 'EUR' );

		$this->assertNull( $result->get_currency( 'SEK' ) );
		$this->assertSame( '85', $result->get_currency( 'GBP' )?->regular(), 'An untouched currency must survive a sibling\'s removal.' );
	}

	/**
	 * A non-array, non-null entry is malformed and leaves the currency
	 * exactly as seeded — matches the product editor's existing behavior
	 * for e.g. `$_POST['umc_fixed_prices']['SEK'] = 'not-an-array'`.
	 */
	public function test_malformed_non_array_entry_leaves_the_currency_untouched(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		$result = $this->merger->merge( $existing, array( 'SEK' => 'not-an-array' ), 'EUR' );

		$this->assertSame( '1100', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '900', $result->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * Zero is a valid, explicit price on both sides of the pair.
	 */
	public function test_zero_is_a_valid_explicit_price(): void {
		$result = $this->merger->merge(
			FixedPriceDocument::empty(),
			array(
				'SEK' => array(
					'regular' => '0',
					'sale'    => '0',
				),
			),
			'EUR'
		);

		$this->assertSame( '0', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '0', $result->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * A zero regular price with no sale is valid (sale_less_than_regular()
	 * treats a blank sale as always valid, regardless of regular).
	 */
	public function test_zero_regular_with_no_sale_is_valid(): void {
		$result = $this->merger->merge(
			FixedPriceDocument::empty(),
			array( 'SEK' => array( 'regular' => '0' ) ),
			'EUR'
		);

		$this->assertSame( '0', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '', $result->get_currency( 'SEK' )?->sale() );
	}

	/**
	 * Base-currency defense-in-depth: even if a caller somehow passes the
	 * base currency in $touched (it should never reach this class through
	 * any real caller, all of which already exclude it upstream), the
	 * merger's own upfront skip — and FixedPriceDocument::from_array()'s
	 * independent second-layer strip — both refuse to create a base-currency
	 * entry.
	 */
	public function test_base_currency_is_stripped_even_if_passed_in(): void {
		$result = $this->merger->merge(
			FixedPriceDocument::empty(),
			array( 'EUR' => array( 'regular' => '1' ) ),
			'EUR'
		);

		$this->assertNull( $result->get_currency( 'EUR' ) );
	}

	/**
	 * Base-currency stripping is case-insensitive, matching every other
	 * currency-code comparison in this class.
	 */
	public function test_base_currency_is_stripped_case_insensitively(): void {
		$result = $this->merger->merge(
			FixedPriceDocument::empty(),
			array( 'eur' => array( 'regular' => '1' ) ),
			'EUR'
		);

		$this->assertNull( $result->get_currency( 'EUR' ) );
	}

	/**
	 * A disabled-but-configured currency's entry is preserved exactly like
	 * any other untouched currency — this class has no concept of
	 * "enabled" at all, matching FixedPriceDocument's own "does not filter
	 * out disabled currencies" contract. Simulated here by simply leaving a
	 * currency out of $touched, since enabled/disabled state lives entirely
	 * in CurrencyRegistry, outside this WooCommerce-agnostic class.
	 */
	public function test_a_currency_not_present_in_touched_is_preserved_regardless_of_any_enabled_state(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array( 'regular' => '1100' ),
				'GBP' => array( 'regular' => '79' ), // Simulates a disabled-but-configured currency's retained data.
			),
			'EUR'
		);

		$result = $this->merger->merge(
			$existing,
			array( 'SEK' => array( 'regular' => '1200' ) ),
			'EUR'
		);

		$this->assertSame( '1200', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '79', $result->get_currency( 'GBP' )?->regular() );
	}

	/**
	 * A missing sub-field within a currency that IS actively being touched
	 * defaults to blank — it is never backfilled from that currency's own
	 * prior stored value, matching the product editor's established
	 * `$entry['sale'] ?? ''` behavior.
	 */
	public function test_omitted_sub_field_within_a_touched_currency_defaults_to_blank_not_the_stale_value(): void {
		$existing = FixedPriceDocument::from_array(
			array(
				'SEK' => array(
					'regular' => '1100',
					'sale'    => '900',
				),
			),
			'EUR'
		);

		// Only 'regular' supplied; 'sale' key is entirely absent.
		$result = $this->merger->merge(
			$existing,
			array( 'SEK' => array( 'regular' => '1200' ) ),
			'EUR'
		);

		$this->assertSame( '1200', $result->get_currency( 'SEK' )?->regular() );
		$this->assertSame(
			'',
			$result->get_currency( 'SEK' )?->sale(),
			'An omitted sub-field within an actively-touched currency must default to blank, not fall back to the stale 900 value.'
		);
	}
}
