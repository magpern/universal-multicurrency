<?php
/**
 * Presentation CSS emission for the storefront switcher.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Composes the two CSS payloads the switcher emits (ADR-0022).
 *
 * Structured overrides travel as inline custom properties on each switcher
 * root, so they are scoped to the instance without a generated stylesheet.
 * Advanced Custom CSS is appended after the base stylesheet and is therefore
 * last in the documented precedence chain. Both helpers are pure so unit tests
 * can assert the payloads without WordPress; the storefront-only and
 * never-in-wp-admin guards live in {@see SwitcherAssets}.
 */
final class SwitcherPresentationCss {

	/**
	 * Provenance banner prepended to merchant Custom CSS in page source.
	 */
	public const CUSTOM_CSS_BANNER = '/* Universal Multicurrency: Display -> Advanced Custom CSS. */';

	/**
	 * Serializes CSS custom properties for an inline style attribute.
	 *
	 * @param array<string, string> $variables CSS custom properties.
	 */
	public static function style_attribute( array $variables ): string {
		$parts = array();

		foreach ( $variables as $name => $value ) {
			$parts[] = $name . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Returns the storefront inline stylesheet payload, or an empty string.
	 *
	 * The stored value is re-validated here so a Custom CSS payload that was
	 * persisted by an older release, or edited outside the settings screen,
	 * can never reach the storefront unchecked.
	 *
	 * @param SwitcherSettings $settings Normalized display settings.
	 */
	public static function storefront_custom_css( SwitcherSettings $settings ): string {
		$css = SwitcherCustomCss::sanitize( $settings->custom_css() );

		if ( '' === $css ) {
			return '';
		}

		return self::CUSTOM_CSS_BANNER . "\n" . $css;
	}
}
