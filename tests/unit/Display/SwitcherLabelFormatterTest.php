<?php
/**
 * Unit tests for switcher label formatting.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherLabelFormatter;

/**
 * Covers label composition, ordering, and duplicate-symbol disambiguation.
 */
final class SwitcherLabelFormatterTest extends TestCase {

	public function test_code_and_symbol_compact_label(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => false,
			)
		);

		$this->assertSame(
			'SEK kr',
			$formatter->format_compact( 'SEK', 'kr', 'Swedish krona' )
		);
	}

	public function test_expanded_label_includes_name_when_enabled(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => true,
			)
		);

		$this->assertSame(
			'SEK kr — Swedish krona',
			$formatter->format( 'SEK', 'kr', 'Swedish krona' )
		);
	}

	public function test_compact_label_drops_name_even_when_enabled(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => true,
			)
		);

		$this->assertSame( 'SEK kr', $formatter->format_compact( 'SEK', 'kr', 'Swedish krona' ) );
	}

	public function test_configured_order_reorders_label(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => false,
				'order'       => array( 'symbol', 'code' ),
			)
		);

		$this->assertSame( 'kr SEK', $formatter->format( 'SEK', 'kr', 'Swedish krona' ) );
	}

	public function test_duplicate_symbol_forces_code_when_symbol_only(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => false,
				'show_symbol' => true,
				'show_name'   => false,
			),
			array( '$' => true )
		);

		$this->assertSame( 'USD $', $formatter->format( 'USD', '$', 'US Dollar' ) );
	}

	public function test_duplicate_symbol_map_detects_repeated_symbols(): void {
		$this->assertSame(
			array( '$' => true ),
			SwitcherLabelFormatter::duplicate_symbol_map( array( '$', '$', '€' ) )
		);
	}

	public function test_name_only_label_falls_back_to_code(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => false,
				'show_symbol' => false,
				'show_name'   => true,
			)
		);

		$this->assertSame( 'Swedish krona', $formatter->format( 'SEK', 'kr', 'Swedish krona' ) );
	}

	public function test_all_toggles_off_falls_back_to_code(): void {
		$formatter = new SwitcherLabelFormatter(
			array(
				'show_code'   => false,
				'show_symbol' => false,
				'show_name'   => false,
			)
		);

		$this->assertSame( 'SEK', $formatter->format( 'SEK', 'kr', 'Swedish krona' ) );
	}
}
