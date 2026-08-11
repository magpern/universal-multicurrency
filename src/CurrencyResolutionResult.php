<?php
/**
 * Structured shopper-currency resolution result.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Immutable outcome of {@see CurrencyResolver::evaluate()}.
 *
 * Winning source is truthful to resolver inputs only: explicit|session|cookie|base.
 * Visitor Location provenance is never a winning_source here.
 */
final class CurrencyResolutionResult {

	public const SOURCE_EXPLICIT = 'explicit';
	public const SOURCE_SESSION  = 'session';
	public const SOURCE_COOKIE   = 'cookie';
	public const SOURCE_BASE     = 'base';

	/**
	 * Creates a resolution result.
	 *
	 * @param string                                  $currency              Resolved currency code.
	 * @param string                                  $winning_source        explicit|session|cookie|base.
	 * @param array<int, CurrencyResolutionCandidate> $candidates       Ordered candidate evaluations.
	 * @param bool                                    $was_fallback_to_base  Whether base was used as fallback.
	 */
	public function __construct(
		private string $currency,
		private string $winning_source,
		private array $candidates,
		private bool $was_fallback_to_base
	) {
		$this->currency = strtoupper( $currency );
	}

	/**
	 * Resolved currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Winning source as seen by the resolver.
	 */
	public function winning_source(): string {
		return $this->winning_source;
	}

	/**
	 * Ordered candidate evaluations.
	 *
	 * @return array<int, CurrencyResolutionCandidate>
	 */
	public function candidates(): array {
		return $this->candidates;
	}

	/**
	 * Whether resolution fell through to the store base currency.
	 */
	public function was_fallback_to_base(): bool {
		return $this->was_fallback_to_base;
	}

	/**
	 * Array representation for tests and explanation payloads.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'currency'             => $this->currency,
			'winning_source'       => $this->winning_source,
			'was_fallback_to_base' => $this->was_fallback_to_base,
			'candidates'           => array_map(
				static fn( CurrencyResolutionCandidate $candidate ): array => $candidate->to_array(),
				$this->candidates
			),
		);
	}
}
