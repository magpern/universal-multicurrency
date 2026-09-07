<?php
/**
 * Static CSS contract for UML-family floating presentations.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;

/**
 * Pins UML v1.12.0 visual tokens on the UMC-owned stylesheet.
 */
final class SwitcherUmlFamilyCssContractTest extends TestCase {

	/**
	 * Stylesheet contents under test.
	 *
	 * @var string
	 */
	private string $css;

	protected function setUp(): void {
		$this->css = (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/css/switcher.css' );
	}

	public function test_family_tokens_match_uml_v1120_visual_reference(): void {
		$this->assertStringContainsString( '--um-edge-control-size: 2.75rem;', $this->css );
		$this->assertStringContainsString( '--um-edge-stack-gap: 0.5rem;', $this->css );
		$this->assertStringContainsString( '--um-edge-z-index: 1000;', $this->css );
		$this->assertStringContainsString( '--umc-fs-bg: #111;', $this->css );
		$this->assertStringContainsString( '--umc-fs-fg: #fff;', $this->css );
		$this->assertStringContainsString( '--umc-fs-muted: rgba(255, 255, 255, 0.72);', $this->css );
		$this->assertStringContainsString( '--umc-fs-radius: 0.75rem;', $this->css );
		$this->assertStringContainsString( '--umc-fs-motion: 200ms ease;', $this->css );
	}

	public function test_family_uses_781_782_visibility_boundary(): void {
		$this->assertMatchesRegularExpression(
			'/@media \(max-width: 781px\) \{\s*\n\s*\.umc-switcher--uml-family\.umc-switcher--hide-mobile/',
			$this->css
		);
		$this->assertMatchesRegularExpression(
			'/@media \(min-width: 782px\) \{\s*\n\s*\.umc-switcher--uml-family\.umc-switcher--hide-desktop/',
			$this->css
		);
	}

	public function test_admin_bar_and_safe_area_tokens_are_present(): void {
		$this->assertStringContainsString( 'var(--wp-admin--admin-bar--height, 32px)', $this->css );
		$this->assertStringContainsString( 'var(--wp-admin--admin-bar--height, 46px)', $this->css );
		$this->assertStringContainsString( 'env(safe-area-inset-top, 0px)', $this->css );
		$this->assertStringContainsString( 'env(safe-area-inset-bottom, 0px)', $this->css );
		$this->assertStringContainsString( 'env(safe-area-inset-left, 0px)', $this->css );
		$this->assertStringContainsString( 'env(safe-area-inset-right, 0px)', $this->css );
	}

	public function test_same_edge_offset_is_ancestor_rooted_and_uses_slot_formula(): void {
		$this->assertStringContainsString(
			'body:has([data-um-edge-control]:not([data-um-edge-control="currency"])[data-um-edge="right"]) .umc-switcher[data-um-edge-control="currency"][data-um-edge="right"]',
			$this->css
		);
		$this->assertStringContainsString(
			'body:has([data-um-edge-control]:not([data-um-edge-control="currency"])[data-um-edge="left"]) .umc-switcher[data-um-edge-control="currency"][data-um-edge="left"]',
			$this->css
		);
		$this->assertStringContainsString(
			'--um-edge-slot-offset: calc(var(--um-edge-control-size, 2.75rem) + var(--um-edge-stack-gap, 0.5rem));',
			$this->css
		);
	}

	public function test_slot_offset_directions(): void {
		$this->assertStringContainsString(
			'.umc-switcher--uml-family.umc-switcher--align-top',
			$this->css
		);
		$this->assertStringContainsString(
			'top: calc(0.75rem + env(safe-area-inset-top, 0px) + var(--um-edge-slot-offset));',
			$this->css
		);
		$this->assertStringContainsString(
			'bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px) + var(--um-edge-slot-offset));',
			$this->css
		);
		$this->assertStringContainsString(
			'transform: translateY(calc(-50% + var(--um-edge-slot-offset)));',
			$this->css
		);
	}

	public function test_reduced_motion_disables_family_panel_transition(): void {
		$this->assertStringContainsString( '@media (prefers-reduced-motion: reduce)', $this->css );
		$this->assertMatchesRegularExpression(
			'/@media \(prefers-reduced-motion: reduce\) \{[\s\S]*?\.umc-switcher--uml-family\[data-umc-enhanced="1"\] \.umc-switcher__panel \{\s*transition: none;/',
			$this->css
		);
	}

	public function test_family_trigger_is_exempt_from_surface_important_reset(): void {
		$this->assertStringContainsString(
			'.umc-switcher:not(.umc-switcher--uml-family) button.umc-switcher__trigger',
			$this->css
		);
		$this->assertStringContainsString(
			'background: var(--umc-fs-bg) !important;',
			$this->css
		);
	}

	public function test_stylesheet_does_not_reference_uml_private_hooks(): void {
		$this->assertStringNotContainsString( '.aiml-', $this->css );
		$this->assertStringNotContainsString( 'data-aiml-', $this->css );
		$this->assertStringNotContainsString( '--aiml-fs-', $this->css );
		$this->assertStringNotContainsString( 'aiml-floating-selector', $this->css );
	}
}
