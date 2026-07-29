<?php
/**
 * Unit tests for switcher HTML rendering.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherOptionViewModel;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherViewModel;

/**
 * Covers escaped markup and disclosure semantics.
 */
final class SwitcherRendererTest extends TestCase {

	public function test_dropdown_uses_disclosure_semantics_not_listbox(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'aria-controls="umc-switcher-menu-1"', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
		$this->assertStringNotContainsString( 'role="listbox"', $html );
		$this->assertStringNotContainsString( 'role="option"', $html );
		$this->assertStringNotContainsString( 'aria-activedescendant', $html );
	}

	public function test_horizontal_list_renders_active_link(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled' => true,
				'style'   => SwitcherSettings::STYLE_HORIZONTAL_LIST,
			)
		);

		$html = ( new SwitcherRenderer() )->render(
			new SwitcherViewModel(
				'2',
				$settings,
				array(
					new SwitcherOptionViewModel( 'EUR', 'EUR €', 'EUR €', '?currency=EUR', true ),
					new SwitcherOptionViewModel( 'SEK', 'SEK kr', 'SEK kr', '?currency=SEK', false ),
				),
				new SwitcherOptionViewModel( 'EUR', 'EUR €', 'EUR €', '?currency=EUR', true ),
				false
			)
		);

		$this->assertStringContainsString( 'umc-switcher--horizontal-list', $html );
		$this->assertStringContainsString( 'umc-switcher__list', $html );
		$this->assertStringContainsString( 'is-active', $html );
	}

	public function test_labels_are_escaped(): void {
		$html = ( new SwitcherRenderer() )->render(
			new SwitcherViewModel(
				'3',
				SwitcherSettings::from_array( array( 'enabled' => true ) ),
				array(
					new SwitcherOptionViewModel( 'EUR', 'EUR <script>', 'EUR <script>', '#', true ),
					new SwitcherOptionViewModel( 'SEK', 'SEK kr', 'SEK kr', '#', false ),
				),
				new SwitcherOptionViewModel( 'EUR', 'EUR <script>', 'EUR <script>', '#', true ),
				true
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'EUR &lt;script&gt;', $html );
	}

	private function view_model(): SwitcherViewModel {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled' => true,
			)
		);

		$active = new SwitcherOptionViewModel( 'SEK', 'SEK kr', 'SEK kr', '?currency=SEK', true );
		$other  = new SwitcherOptionViewModel( 'EUR', 'EUR €', 'EUR €', '?currency=EUR', false );

		return new SwitcherViewModel( '1', $settings, array( $active, $other ), $active, false );
	}
}
