<?php
/**
 * Unit tests for the switcher presentation CSS payloads.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherPresentationCss;
use UMC\Display\SwitcherSettings;

/**
 * Covers the inline style attribute and the storefront Custom CSS payload.
 *
 * @covers \UMC\Display\SwitcherPresentationCss
 */
final class SwitcherPresentationCssTest extends TestCase {

	public function test_style_attribute_serializes_custom_properties(): void {
		$this->assertSame(
			'--umc-switcher-surface:#111827;--umc-switcher-radius:12px',
			SwitcherPresentationCss::style_attribute(
				array(
					'--umc-switcher-surface' => '#111827',
					'--umc-switcher-radius'  => '12px',
				)
			)
		);
	}

	public function test_style_attribute_is_empty_without_variables(): void {
		$this->assertSame( '', SwitcherPresentationCss::style_attribute( array() ) );
	}

	public function test_storefront_custom_css_is_empty_when_none_is_stored(): void {
		$this->assertSame(
			'',
			SwitcherPresentationCss::storefront_custom_css( SwitcherSettings::from_array( array() ) )
		);
	}

	public function test_storefront_custom_css_prefixes_a_provenance_banner(): void {
		$settings = SwitcherSettings::from_array(
			array( 'custom_css' => '.umc-switcher { letter-spacing: 0.02em; }' )
		);

		$this->assertSame(
			SwitcherPresentationCss::CUSTOM_CSS_BANNER . "\n.umc-switcher { letter-spacing: 0.02em; }",
			SwitcherPresentationCss::storefront_custom_css( $settings )
		);
	}

	/**
	 * A payload persisted by an older release, or written straight into the
	 * option, must still be re-validated before it reaches the storefront.
	 */
	public function test_storefront_custom_css_drops_denylisted_payloads(): void {
		$settings = SwitcherSettings::from_array(
			array( 'custom_css' => '.umc-switcher { background: url(https://evil.test/x.png); }' )
		);

		$this->assertSame(
			'',
			SwitcherPresentationCss::storefront_custom_css( $settings )
		);
	}
}
