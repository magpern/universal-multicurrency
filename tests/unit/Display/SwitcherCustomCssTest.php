<?php
/**
 * Unit tests for advanced Custom CSS validation and authority.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherCustomCss;

/**
 * Covers the ADR-0022 denylist, length cap, and unauthorized-save preservation.
 */
final class SwitcherCustomCssTest extends TestCase {

	private const SAFE_CSS = ".umc-switcher__trigger {\n\tborder-radius: 999px;\n}";

	public function test_safe_css_is_accepted(): void {
		$this->assertTrue( SwitcherCustomCss::is_valid( self::SAFE_CSS ) );
		$this->assertSame( self::SAFE_CSS, SwitcherCustomCss::sanitize( self::SAFE_CSS ) );
	}

	public function test_media_queries_are_accepted(): void {
		$css = "@media (max-width: 767px) {\n\t.umc-switcher__name { display: none; }\n}";

		$this->assertTrue( SwitcherCustomCss::is_valid( $css ) );
	}

	/**
	 * @dataProvider rejected_css_provider
	 *
	 * @param string $css    Candidate CSS.
	 * @param string $reason Expected rejection reason.
	 */
	public function test_denylisted_payloads_are_rejected( string $css, string $reason ): void {
		$this->assertSame( $reason, SwitcherCustomCss::rejection_reason( $css ) );
		$this->assertFalse( SwitcherCustomCss::is_valid( $css ) );
		$this->assertSame( '', SwitcherCustomCss::sanitize( $css ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function rejected_css_provider(): array {
		return array(
			'nul byte'        => array( ".umc-switcher {\0}", SwitcherCustomCss::REASON_CONTROL_CHARACTER ),
			'style breakout'  => array( '</style><script>alert(1)</script>', SwitcherCustomCss::REASON_MARKUP ),
			'angle bracket'   => array( '.umc-switcher > a { color: red; }', SwitcherCustomCss::REASON_MARKUP ),
			'escape sequence' => array( '.umc-switcher { background: u\\72 l(x); }', SwitcherCustomCss::REASON_ESCAPE_SEQUENCE ),
			'import'          => array( '@import "https://example.test/x.css";', SwitcherCustomCss::REASON_IMPORT ),
			'spaced import'   => array( '@  import "x.css";', SwitcherCustomCss::REASON_IMPORT ),
			'url'             => array( '.umc-switcher { background: url(/x.png); }', SwitcherCustomCss::REASON_URL ),
			'uppercase url'   => array( '.umc-switcher { background: URL (/x.png); }', SwitcherCustomCss::REASON_URL ),
			'data url'        => array( '.umc-switcher { background: url("data:image/gif;base64,AA"); }', SwitcherCustomCss::REASON_URL ),
			'expression'      => array( '.umc-switcher { width: expression(alert(1)); }', SwitcherCustomCss::REASON_EXPRESSION ),
			'behavior'        => array( '.umc-switcher { behavior: htcFile; }', SwitcherCustomCss::REASON_BEHAVIOR ),
			'moz binding'     => array( '.umc-switcher { -moz-binding: something; }', SwitcherCustomCss::REASON_MOZ_BINDING ),
		);
	}

	public function test_transition_behavior_property_is_not_mistaken_for_breakout(): void {
		$css = '.umc-switcher__trigger { transition-behavior: allow-discrete; }';

		$this->assertNull( SwitcherCustomCss::rejection_reason( $css ) );
	}

	public function test_length_cap_is_enforced(): void {
		$css = str_repeat( 'a', SwitcherCustomCss::MAX_LENGTH + 1 );

		$this->assertSame( SwitcherCustomCss::REASON_TOO_LONG, SwitcherCustomCss::rejection_reason( $css ) );
		$this->assertSame( 32768, SwitcherCustomCss::MAX_LENGTH );
	}

	public function test_non_string_values_sanitize_to_empty_string(): void {
		$this->assertSame( '', SwitcherCustomCss::sanitize( null ) );
		$this->assertSame( '', SwitcherCustomCss::sanitize( array( 'x' ) ) );
		$this->assertSame( '', SwitcherCustomCss::sanitize( 42 ) );
	}

	public function test_line_endings_are_normalized(): void {
		$this->assertSame(
			".umc-switcher {\n\tcolor: red;\n}",
			SwitcherCustomCss::sanitize( "\r\n.umc-switcher {\r\n\tcolor: red;\r\n}\r\n" )
		);
	}

	public function test_unauthorized_replacement_preserves_stored_css(): void {
		$this->assertSame(
			self::SAFE_CSS,
			SwitcherCustomCss::resolve_for_save( '.umc-switcher { color: red; }', self::SAFE_CSS, false )
		);
	}

	public function test_unauthorized_clear_preserves_stored_css(): void {
		$this->assertSame( self::SAFE_CSS, SwitcherCustomCss::resolve_for_save( '', self::SAFE_CSS, false ) );
	}

	public function test_omitted_field_preserves_stored_css_for_authorized_actor(): void {
		$this->assertSame( self::SAFE_CSS, SwitcherCustomCss::resolve_for_save( null, self::SAFE_CSS, true ) );
	}

	public function test_authorized_actor_may_replace_and_clear(): void {
		$replacement = '.umc-switcher__link { color: #333333; }';

		$this->assertSame( $replacement, SwitcherCustomCss::resolve_for_save( $replacement, self::SAFE_CSS, true ) );
		$this->assertSame( '', SwitcherCustomCss::resolve_for_save( '', self::SAFE_CSS, true ) );
	}

	public function test_authorized_invalid_submission_preserves_stored_css(): void {
		$this->assertSame(
			self::SAFE_CSS,
			SwitcherCustomCss::resolve_for_save( '@import "evil.css";', self::SAFE_CSS, true )
		);
	}

	public function test_previously_stored_invalid_css_is_not_reflected_back(): void {
		$this->assertSame( '', SwitcherCustomCss::resolve_for_save( null, '@import "evil.css";', true ) );
	}

	public function test_capability_check_requires_edit_css(): void {
		$this->assertSame( 'edit_css', SwitcherCustomCss::CAPABILITY );
		$this->assertFalse( SwitcherCustomCss::can_edit(), 'Without WordPress loaded no actor holds edit_css.' );
	}
}
