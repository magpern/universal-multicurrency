<?php
/**
 * Static JS contract for UML-family progressive enhancement.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;

/**
 * Pins storefront JS interaction without introducing currency authority.
 */
final class SwitcherUmlFamilyJsContractTest extends TestCase {

	/**
	 * Storefront controller source under test.
	 *
	 * @var string
	 */
	private string $js;

	protected function setUp(): void {
		$this->js = (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/js/switcher.js' );
	}

	public function test_enhanced_marker_is_set_only_after_init_on_non_preview_roots(): void {
		$this->assertStringContainsString( "root.setAttribute('data-umc-enhanced', '1')", $this->js );
		$this->assertStringContainsString( 'if (!isPreview(root))', $this->js );
	}

	public function test_currency_links_are_not_intercepted_outside_preview(): void {
		$this->assertStringContainsString( 'function bindPreviewLinks(root)', $this->js );
		$this->assertStringContainsString( 'No currency-switch logic lives here', $this->js );

		$preview_start = strpos( $this->js, 'function bindPreviewLinks' );
		$init_start    = strpos( $this->js, 'function init()' );
		$this->assertNotFalse( $preview_start );
		$this->assertNotFalse( $init_start );

		$preview_fn = substr( $this->js, (int) $preview_start, (int) $init_start - (int) $preview_start );
		$this->assertStringContainsString( 'if (!isPreview(root))', $preview_fn );
		$this->assertStringContainsString( 'event.preventDefault();', $preview_fn );
	}

	public function test_js_does_not_become_currency_authority(): void {
		$this->assertStringNotContainsString( 'fetch(', $this->js );
		$this->assertStringNotContainsString( 'XMLHttpRequest', $this->js );
		$this->assertStringNotContainsString( '/wp-json/', $this->js );
		$this->assertStringNotContainsString( 'admin-ajax.php', $this->js );
	}

	public function test_escape_and_outside_click_remain_in_the_controller(): void {
		$this->assertStringContainsString( "event.key === 'Escape'", $this->js );
		$this->assertStringContainsString( 'onDocumentClick', $this->js );
		$this->assertStringContainsString( 'onDocumentKeydown', $this->js );
		$this->assertStringContainsString( "document.addEventListener('keydown', onDocumentKeydown, true)", $this->js );
		$this->assertStringContainsString( 'event.stopPropagation();', $this->js );
	}

	public function test_family_retain_uses_expand_strategy(): void {
		$this->assertStringContainsString( 'function isUmlFamily(root)', $this->js );
		$this->assertStringContainsString( "if (isUmlFamily(root) && behavior === 'retain')", $this->js );
		$this->assertStringContainsString( "return 'expand';", $this->js );
	}

	public function test_admin_dirty_flag_protects_mobile_behavior(): void {
		$admin = (string) file_get_contents( dirname( __DIR__, 3 ) . '/assets/admin/umc-settings.js' );

		$this->assertStringContainsString( 'var mobileBehaviorDirty = false;', $admin );
		$this->assertStringContainsString( 'function applyFamilyMobileRetain()', $admin );
		$this->assertStringContainsString( 'if ( mobileBehaviorDirty )', $admin );
		$this->assertStringContainsString( 'mobileBehaviorDirty = true;', $admin );
		$this->assertStringContainsString( 'var UML_FAMILY_PRESENTATIONS', $admin );
		$this->assertStringContainsString( 'function isUmlFamilyPreview()', $admin );
		$this->assertStringContainsString( "toggleClass( 'umc-switcher--uml-family'", $admin );
		$this->assertMatchesRegularExpression(
			'/function applyFloatingDefaults\(\) \{[\s\S]*?value="retain"/',
			$admin
		);
	}
}
