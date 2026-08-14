<?php
/**
 * Unit tests for structured switcher element composition.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherElementComposer;

/**
 * Covers ordered element markup, escaping, and disambiguation.
 */
final class SwitcherElementComposerTest extends TestCase {

	public function test_html_wraps_each_element_in_its_own_span(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => true,
			)
		);

		$this->assertSame(
			'<span class="umc-switcher__code">SEK</span>'
			. '<span class="umc-switcher__symbol">kr</span>'
			. '<span class="umc-switcher__name">Swedish krona</span>',
			$composer->html( 'SEK', 'kr', 'Swedish krona' )
		);
	}

	public function test_html_follows_configured_order(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => true,
				'order'       => array( 'name', 'symbol', 'code' ),
			)
		);

		$this->assertSame(
			'<span class="umc-switcher__name">Swedish krona</span>'
			. '<span class="umc-switcher__symbol">kr</span>'
			. '<span class="umc-switcher__code">SEK</span>',
			$composer->html( 'SEK', 'kr', 'Swedish krona' )
		);
	}

	public function test_hidden_elements_are_omitted_from_markup(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => true,
				'show_symbol' => false,
				'show_name'   => false,
			)
		);

		$html = $composer->html( 'SEK', 'kr', 'Swedish krona' );

		$this->assertSame( '<span class="umc-switcher__code">SEK</span>', $html );
		$this->assertStringNotContainsString( 'umc-switcher__symbol', $html );
		$this->assertStringNotContainsString( 'umc-switcher__name', $html );
	}

	public function test_values_are_escaped(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => false,
			)
		);

		$html = $composer->html( 'SEK', '<script>alert(1)</script>', 'Swedish krona' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_empty_symbol_is_not_rendered(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => false,
			)
		);

		$this->assertSame(
			'<span class="umc-switcher__code">SEK</span>',
			$composer->html( 'SEK', '', 'Swedish krona' )
		);
	}

	public function test_duplicate_symbol_forces_code_into_parts(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => false,
				'show_symbol' => true,
				'show_name'   => false,
			),
			array( '$' => true )
		);

		$this->assertSame(
			'<span class="umc-switcher__code">USD</span><span class="umc-switcher__symbol">$</span>',
			$composer->html( 'USD', '$', 'US Dollar' )
		);
	}

	public function test_unique_symbol_does_not_force_code(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => false,
				'show_symbol' => true,
				'show_name'   => false,
			),
			array( '$' => true )
		);

		$this->assertSame(
			'<span class="umc-switcher__symbol">€</span>',
			$composer->html( 'EUR', '€', 'Euro' )
		);
	}

	public function test_markup_never_renders_empty(): void {
		$composer = new SwitcherElementComposer(
			array(
				'show_code'   => false,
				'show_symbol' => false,
				'show_name'   => false,
			)
		);

		$this->assertSame(
			'<span class="umc-switcher__code">SEK</span>',
			$composer->html( 'SEK', 'kr', 'Swedish krona' )
		);
	}

	public function test_normalize_order_drops_unknown_and_duplicate_entries(): void {
		$this->assertSame(
			array( 'symbol', 'code', 'name' ),
			SwitcherElementComposer::normalize_order( array( 'symbol', 'flag', 'symbol', 'code' ) )
		);

		$this->assertSame(
			array( 'icon', 'code', 'symbol', 'name' ),
			SwitcherElementComposer::normalize_order( array( 'icon', 'code' ) )
		);
	}

	public function test_normalize_order_falls_back_to_default_sequence(): void {
		$this->assertSame(
			SwitcherElementComposer::DEFAULT_ORDER,
			SwitcherElementComposer::normalize_order( 'not-an-array' )
		);
	}

	public function test_duplicate_symbol_map_ignores_empty_symbols(): void {
		$this->assertSame(
			array( '$' => true ),
			SwitcherElementComposer::duplicate_symbol_map( array( '$', '$', '', '', '€' ) )
		);
	}
}
