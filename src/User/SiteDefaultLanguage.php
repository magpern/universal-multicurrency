<?php
/**
 * Site-configured WordPress language for Regional Preferences fallbacks.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\User;

/**
 * Resolves the site default without using the current user's UI locale.
 */
final class SiteDefaultLanguage {

	public const FALLBACK_LOCALE = 'en_US';

	/**
	 * Site-configured locale code (WPLANG or en_US).
	 */
	public static function locale(): string {
		$stored = get_option( 'WPLANG', '' );

		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			return self::FALLBACK_LOCALE;
		}

		return trim( $stored );
	}

	/**
	 * Human-readable site-default label.
	 */
	public static function label(): string {
		return sprintf(
			/* translators: %s: locale code */
			__( '%s (site default)', 'universal-multicurrency' ),
			self::locale()
		);
	}
}
