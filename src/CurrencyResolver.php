<?php
/**
 * Active-currency resolution.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC;

/**
 * Pure, WordPress-free resolution of the active currency code.
 *
 * Applies the priority order (explicit selection → session → cookie → base) and
 * returns the first candidate that is present in the selectable allow-list.
 * Anything invalid, disabled or rate-less is skipped; the base currency is the
 * final fallback and is always considered selectable.
 */
final class CurrencyResolver {

	/**
	 * Resolves the active currency code from the ordered candidates.
	 *
	 * Candidates are normalized to uppercase before comparison; empty or
	 * malformed values are ignored. GeoIP is never a source.
	 *
	 * @param string|null        $explicit   Explicitly selected code (this request), or null.
	 * @param string|null        $session    Code stored in the WC session, or null.
	 * @param string|null        $cookie     Code stored in the guest cookie, or null.
	 * @param string             $base       Base currency code (always selectable).
	 * @param array<int, string> $selectable Uppercase codes that may be activated (enabled and rated).
	 */
	public function resolve( ?string $explicit, ?string $session, ?string $cookie, string $base, array $selectable ): string {
		$base       = strtoupper( $base );
		$selectable = array_map( 'strtoupper', $selectable );

		foreach ( array( $explicit, $session, $cookie ) as $candidate ) {
			$code = is_string( $candidate ) ? strtoupper( trim( $candidate ) ) : '';

			if ( '' === $code ) {
				continue;
			}

			if ( $code === $base || in_array( $code, $selectable, true ) ) {
				return $code;
			}
		}

		return $base;
	}
}
