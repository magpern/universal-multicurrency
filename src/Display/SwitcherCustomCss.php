<?php
/**
 * Advanced Custom CSS validation and authority rules.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Validates merchant-authored switcher CSS (ADR-0022, Model A).
 *
 * Custom CSS is not technically scoped: merchants write full selectors. The
 * threat model is hard breakout prevention plus a best-effort denylist behind
 * the `edit_css` capability — not a complete CSS security parser.
 */
final class SwitcherCustomCss {

	public const MAX_LENGTH = 32768;

	public const CAPABILITY = 'edit_css';

	public const REASON_TOO_LONG = 'too_long';

	public const REASON_CONTROL_CHARACTER = 'control_character';

	public const REASON_MARKUP = 'markup';

	public const REASON_ESCAPE_SEQUENCE = 'escape_sequence';

	public const REASON_IMPORT = 'import';

	public const REASON_URL = 'url';

	public const REASON_EXPRESSION = 'expression';

	public const REASON_BEHAVIOR = 'behavior';

	public const REASON_MOZ_BINDING = 'moz_binding';

	/**
	 * Denylist patterns keyed by the rejection reason they produce.
	 *
	 * @var array<string, string>
	 */
	private const DENYLIST = array(
		self::REASON_MARKUP          => '/[<>]/',
		self::REASON_ESCAPE_SEQUENCE => '/\\\\/',
		self::REASON_IMPORT          => '/@\s*import/i',
		self::REASON_URL             => '/\burl\s*\(/i',
		self::REASON_EXPRESSION      => '/\bexpression\s*\(/i',
		self::REASON_BEHAVIOR        => '/(?<![-\w])behavior\s*:/i',
		self::REASON_MOZ_BINDING     => '/-moz-binding/i',
	);

	/**
	 * Whether the current user may author Custom CSS.
	 *
	 * Display-save authority is enforced separately by the settings screen;
	 * this is the additional `edit_css` requirement from ADR-0022.
	 */
	public static function can_edit(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		return (bool) current_user_can( self::CAPABILITY );
	}

	/**
	 * Whether a CSS string is acceptable for storage and storefront output.
	 *
	 * @param string $css Candidate CSS.
	 */
	public static function is_valid( string $css ): bool {
		return null === self::rejection_reason( $css );
	}

	/**
	 * Returns the first rejection reason for a CSS string, or null when valid.
	 *
	 * @param string $css Candidate CSS.
	 */
	public static function rejection_reason( string $css ): ?string {
		if ( strlen( $css ) > self::MAX_LENGTH ) {
			return self::REASON_TOO_LONG;
		}

		if ( 1 === preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $css ) ) {
			return self::REASON_CONTROL_CHARACTER;
		}

		foreach ( self::DENYLIST as $reason => $pattern ) {
			if ( 1 === preg_match( $pattern, $css ) ) {
				return $reason;
			}
		}

		return null;
	}

	/**
	 * Normalizes and validates a stored value, dropping anything unacceptable.
	 *
	 * Pure and WordPress-free so {@see \UMC\Settings::sanitize()} stays testable
	 * without a bootstrap. Capability enforcement belongs to the save path.
	 *
	 * @param mixed $raw Raw stored or submitted value.
	 */
	public static function sanitize( mixed $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$css = self::normalize( $raw );

		return self::is_valid( $css ) ? $css : '';
	}

	/**
	 * Resolves the Custom CSS to persist for one save attempt.
	 *
	 * Unauthorized submissions never mutate stored CSS — forged replacements,
	 * forged clears, and omitted fields all preserve the previous value.
	 *
	 * @param mixed $submitted Submitted Custom CSS, or null when absent.
	 * @param mixed $previous  Previously stored Custom CSS.
	 * @param bool  $can_edit  Whether the current actor holds `edit_css`.
	 */
	public static function resolve_for_save( mixed $submitted, mixed $previous, bool $can_edit ): string {
		$stored = self::sanitize( $previous );

		if ( ! $can_edit || ! is_string( $submitted ) ) {
			return $stored;
		}

		$css = self::normalize( $submitted );

		if ( '' === $css ) {
			return '';
		}

		return self::is_valid( $css ) ? $css : $stored;
	}

	/**
	 * Normalizes line endings and surrounding whitespace.
	 *
	 * @param string $css Raw CSS.
	 */
	private static function normalize( string $css ): string {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $css ) );
	}
}
