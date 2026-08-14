<?php
/**
 * Unit tests for switcher HTML rendering.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherOptionFactory;
use UMC\Display\SwitcherOptionViewModel;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherViewModel;

/**
 * Covers escaped markup, structured elements, and disclosure semantics.
 */
final class SwitcherRendererTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			define( 'UMC_PLUGIN_FILE', dirname( __DIR__, 3 ) . '/universal-multicurrency.php' );
		}

		if ( ! defined( 'UMC_VERSION' ) ) {
			define( 'UMC_VERSION', '0.21.0' );
		}
	}
	public function test_dropdown_uses_disclosure_semantics_not_listbox(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'aria-controls="umc-switcher-menu-1"', $html );
		$this->assertStringContainsString( 'id="umc-switcher-trigger-1"', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
		$this->assertStringNotContainsString( 'role="listbox"', $html );
		$this->assertStringNotContainsString( 'role="option"', $html );
		$this->assertStringNotContainsString( 'aria-activedescendant', $html );
	}

	public function test_trigger_wraps_structured_elements_in_trigger_content(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString(
			'<span class="umc-switcher__trigger-content"><span class="umc-switcher__code">SEK</span><span class="umc-switcher__symbol">kr</span></span>',
			$html
		);
	}

	public function test_menu_links_contain_structured_elements(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString(
			'<span class="umc-switcher__code">EUR</span><span class="umc-switcher__symbol">€</span>',
			$html
		);
		$this->assertStringNotContainsString( 'umc-switcher__option', $html );
	}

	public function test_dropdown_menu_items_use_public_item_class_and_active_state(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString( 'umc-switcher__menu', $html );
		$this->assertStringContainsString( '<li class="umc-switcher__item is-active">', $html );
		$this->assertMatchesRegularExpression( '/<li class="umc-switcher__item">/', $html );
		$this->assertStringNotContainsString( 'umc-switcher--active', $html );
	}

	public function test_root_exposes_placement_and_style_hooks(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringContainsString( 'data-umc-placement="manual"', $html );
		$this->assertStringContainsString( 'data-umc-style="dropdown"', $html );
	}

	public function test_sticky_footer_placement_hook_matches_modifier_class(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model(
				array(
					'placement' => SwitcherSettings::PLACEMENT_STICKY_FOOTER,
				)
			)
		);

		$this->assertStringContainsString( 'umc-switcher--floating-bottom', $html );
		$this->assertStringContainsString( 'data-umc-placement="floating-bottom"', $html );
	}

	public function test_chevron_is_absent_by_default(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringNotContainsString( 'umc-switcher__chevron', $html );
	}

	public function test_chevron_renders_when_enabled(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model(
				array(
					'content' => array( 'show_chevron' => true ),
				)
			)
		);

		$this->assertStringContainsString( '<span class="umc-switcher__chevron" aria-hidden="true"></span>', $html );
	}

	public function test_configured_order_drives_dom_order(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model(
				array(
					'content' => array(
						'trigger' => array(
							'show_code'   => true,
							'show_symbol' => true,
							'order'       => array( 'symbol', 'code' ),
						),
					),
				)
			)
		);

		$this->assertStringContainsString(
			'<span class="umc-switcher__symbol">kr</span><span class="umc-switcher__code">SEK</span>',
			$html
		);
	}

	public function test_horizontal_list_renders_active_link(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model(
				array( 'style' => SwitcherSettings::STYLE_HORIZONTAL_LIST )
			)
		);

		$this->assertStringContainsString( 'umc-switcher--horizontal-list', $html );
		$this->assertStringContainsString( 'umc-switcher__list', $html );
		$this->assertStringContainsString( 'umc-switcher__item is-active', $html );
		$this->assertStringContainsString( 'data-umc-style="horizontal-list"', $html );
		$this->assertStringNotContainsString( 'umc-switcher--active', $html );
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

	public function test_presentation_icon_renders_when_enabled_and_ordered(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model(
				array(
					'content' => array(
						'trigger' => array(
							'show_icon' => true,
							'order'     => array( 'icon', 'code', 'symbol' ),
						),
						'menu'    => array(
							'show_icon' => true,
							'order'     => array( 'icon', 'code', 'symbol' ),
						),
					),
				)
			)
		);

		$this->assertStringContainsString( 'umc-switcher__icon', $html );
		$this->assertStringContainsString( 'data-umc-icon-type="flag"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'SE.svg', $html );
	}

	public function test_presentation_icon_is_omitted_when_disabled(): void {
		$html = ( new SwitcherRenderer() )->render( $this->view_model() );

		$this->assertStringNotContainsString( 'umc-switcher__icon', $html );
	}

	public function test_structured_elements_are_escaped(): void {
		$html = ( new SwitcherRenderer() )->render(
			$this->view_model( array(), 'SEK', '<script>' )
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<span class="umc-switcher__symbol">&lt;script&gt;</span>', $html );
	}

	/**
	 * Builds a two-currency dropdown view model through the real option factory.
	 *
	 * @param array<string, mixed> $display     Display setting overrides.
	 * @param string               $active_code Active currency code.
	 * @param string               $symbol      Active currency symbol.
	 */
	private function view_model( array $display = array(), string $active_code = 'SEK', string $symbol = 'kr' ): SwitcherViewModel {
		$settings = SwitcherSettings::from_array(
			array_replace_recursive(
				array( 'enabled' => true ),
				$display
			)
		);

		$factory = new SwitcherOptionFactory( $settings );
		$active  = $factory->create( $active_code, $symbol, $active_code . ' name', '?currency=' . $active_code, true );
		$other   = $factory->create( 'EUR', '€', 'Euro', '?currency=EUR', false );

		return new SwitcherViewModel( '1', $settings, array( $active, $other ), $active, false );
	}
}
