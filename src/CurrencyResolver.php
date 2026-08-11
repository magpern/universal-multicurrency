<?php
/**
 * Active-currency resolution.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Pure, WordPress-free resolution of the active currency code.
 *
 * Applies the priority order (explicit selection → session → cookie → base)
 * and returns the first candidate that is present in the selectable allow-list.
 * Anything invalid, disabled or rate-less is skipped; the base currency is the
 * final fallback and is always considered selectable.
 *
 * Visitor Location is not a resolver candidate. When geo writes a currency it
 * persists into session (or cookie); this class later sees `session`/`cookie`.
 */
final class CurrencyResolver {

	/**
	 * Resolves the active currency code from the ordered candidates.
	 *
	 * Candidates are normalized to uppercase before comparison; empty or
	 * malformed values are ignored.
	 *
	 * @param string|null        $explicit   Explicitly selected code (this request), or null.
	 * @param string|null        $session    Code stored in the WC session, or null.
	 * @param string|null        $cookie     Code stored in the guest cookie, or null.
	 * @param string             $base       Base currency code (always selectable).
	 * @param array<int, string> $selectable Uppercase codes that may be activated (enabled and rated).
	 */
	public function resolve( ?string $explicit, ?string $session, ?string $cookie, string $base, array $selectable ): string {
		return $this->evaluate( $explicit, $session, $cookie, $base, $selectable )->currency();
	}

	/**
	 * Evaluates shopper currency candidates and returns a structured result.
	 *
	 * Winning source is the first accepted candidate in priority order. Candidate
	 * statuses describe each input independently so lower-priority valid codes
	 * remain visible as accepted-but-not-winning when a higher source won.
	 *
	 * @param string|null        $explicit   Explicitly selected code (this request), or null.
	 * @param string|null        $session    Code stored in the WC session, or null.
	 * @param string|null        $cookie     Code stored in the guest cookie, or null.
	 * @param string             $base       Base currency code (always selectable).
	 * @param array<int, string> $selectable Uppercase codes that may be activated (enabled and rated).
	 */
	public function evaluate( ?string $explicit, ?string $session, ?string $cookie, string $base, array $selectable ): CurrencyResolutionResult {
		$base       = strtoupper( $base );
		$selectable = array_map( 'strtoupper', $selectable );
		$sources    = array(
			CurrencyResolutionResult::SOURCE_EXPLICIT => $explicit,
			CurrencyResolutionResult::SOURCE_SESSION  => $session,
			CurrencyResolutionResult::SOURCE_COOKIE   => $cookie,
		);

		$candidates     = array();
		$winning_source = null;
		$winning_code   = null;

		foreach ( $sources as $source => $candidate ) {
			$evaluated    = $this->evaluate_candidate( $source, $candidate, $base, $selectable );
			$candidates[] = $evaluated;

			if ( null === $winning_source && CurrencyResolutionCandidate::STATUS_ACCEPTED === $evaluated->status() ) {
				$winning_source = $source;
				$winning_code   = $evaluated->normalized();
			}
		}

		if ( null !== $winning_source && is_string( $winning_code ) && '' !== $winning_code ) {
			return new CurrencyResolutionResult( $winning_code, $winning_source, $candidates, false );
		}

		return new CurrencyResolutionResult(
			$base,
			CurrencyResolutionResult::SOURCE_BASE,
			$candidates,
			true
		);
	}

	/**
	 * Evaluates one candidate against the allow-list.
	 *
	 * Mirrors historic {@see resolve()} acceptance rules: non-empty trimmed
	 * uppercase codes that equal base or appear in selectable are accepted.
	 * Empty values are empty; other non-matching values are not selectable.
	 *
	 * @param string             $source     Source id.
	 * @param string|null        $candidate  Raw candidate.
	 * @param string             $base       Base code.
	 * @param array<int, string> $selectable Selectable codes.
	 */
	private function evaluate_candidate( string $source, ?string $candidate, string $base, array $selectable ): CurrencyResolutionCandidate {
		$raw = is_string( $candidate ) ? $candidate : null;

		if ( null === $raw ) {
			return new CurrencyResolutionCandidate(
				$source,
				null,
				null,
				CurrencyResolutionCandidate::STATUS_EMPTY
			);
		}

		$code = strtoupper( trim( $raw ) );

		if ( '' === $code ) {
			return new CurrencyResolutionCandidate(
				$source,
				$raw,
				null,
				CurrencyResolutionCandidate::STATUS_EMPTY
			);
		}

		if ( $code === $base || in_array( $code, $selectable, true ) ) {
			return new CurrencyResolutionCandidate(
				$source,
				$raw,
				$code,
				CurrencyResolutionCandidate::STATUS_ACCEPTED
			);
		}

		return new CurrencyResolutionCandidate(
			$source,
			$raw,
			$code,
			CurrencyResolutionCandidate::STATUS_REJECTED,
			CurrencyResolutionCandidate::REJECT_NOT_SELECTABLE
		);
	}
}
