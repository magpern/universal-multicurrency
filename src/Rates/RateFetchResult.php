<?php
/**
 * Batch result from an exchange-rate provider fetch.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Immutable fetch outcome for one provider request.
 */
final class RateFetchResult {

	/**
	 * Successful quotes returned by the provider.
	 *
	 * @var RateQuote[]
	 */
	private array $quotes;

	/**
	 * Per-target failure reasons keyed by currency code.
	 *
	 * @var array<string, string>
	 */
	private array $failures;

	/**
	 * Batch metadata when the response carried a body.
	 *
	 * @var ProviderMetadata|null
	 */
	private ?ProviderMetadata $metadata;

	/**
	 * Unix timestamp of the fetch attempt.
	 *
	 * @var int
	 */
	private int $fetched_at;

	/**
	 * Whether the provider returned HTTP 304 Not Modified.
	 *
	 * @var bool
	 */
	private bool $not_modified;

	/**
	 * Provider identifier for this fetch.
	 *
	 * @var string
	 */
	private string $provider_id;

	/**
	 * Whether the update skipped because no automatic targets were selected.
	 *
	 * @var bool
	 */
	private bool $no_automatic_targets;

	/**
	 * Builds a fetch result value object.
	 *
	 * @param RateQuote[]           $quotes                Successful quotes.
	 * @param array<string, string> $failures              Per-target failure reasons.
	 * @param ProviderMetadata|null $metadata              Batch metadata (null when not modified).
	 * @param int                   $fetched_at            Unix timestamp of the fetch attempt.
	 * @param bool                  $not_modified          Whether the provider returned HTTP 304.
	 * @param string                $provider_id           Provider identifier.
	 * @param bool                  $no_automatic_targets  Whether no automatic targets were available.
	 */
	private function __construct(
		array $quotes,
		array $failures,
		?ProviderMetadata $metadata,
		int $fetched_at,
		bool $not_modified,
		string $provider_id,
		bool $no_automatic_targets = false
	) {
		$this->quotes               = $quotes;
		$this->failures             = $failures;
		$this->metadata             = $metadata;
		$this->fetched_at           = $fetched_at;
		$this->not_modified         = $not_modified;
		$this->provider_id          = $provider_id;
		$this->no_automatic_targets = $no_automatic_targets;
	}

	/**
	 * Creates a successful fetch result with quotes and/or failures.
	 *
	 * @param RateQuote[]           $quotes     Successful quotes.
	 * @param array<string, string> $failures   Per-target failure reasons.
	 * @param ProviderMetadata      $metadata   Batch metadata.
	 * @param int                   $fetched_at Unix timestamp of the fetch attempt.
	 */
	public static function success(
		array $quotes,
		array $failures,
		ProviderMetadata $metadata,
		int $fetched_at
	): self {
		return new self(
			$quotes,
			$failures,
			$metadata,
			$fetched_at,
			false,
			$metadata->provider_id()
		);
	}

	/**
	 * Creates a not-modified fetch result for HTTP 304 responses.
	 *
	 * Distinct from {@see self::no_automatic_targets()}: this preserves 304
	 * semantics for conditional HTTP caching and store write ceilings.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param int    $fetched_at  Unix timestamp of the fetch attempt.
	 */
	public static function not_modified( string $provider_id, int $fetched_at ): self {
		return new self(
			array(),
			array(),
			null,
			$fetched_at,
			true,
			$provider_id
		);
	}

	/**
	 * Creates a result when a refresh had no automatic currency targets.
	 *
	 * Distinct from HTTP 304 {@see self::not_modified()} so callers can message
	 * “no automatic currencies” without conflating conditional caching.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param int    $fetched_at  Unix timestamp of the attempt.
	 */
	public static function no_automatic_targets( string $provider_id, int $fetched_at ): self {
		return new self(
			array(),
			array(),
			null,
			$fetched_at,
			false,
			$provider_id,
			true
		);
	}

	/**
	 * Successful quotes returned by the provider.
	 *
	 * @return RateQuote[]
	 */
	public function quotes(): array {
		return $this->quotes;
	}

	/**
	 * Per-target failure reasons keyed by currency code.
	 *
	 * @return array<string, string>
	 */
	public function failures(): array {
		return $this->failures;
	}

	/**
	 * Batch metadata when the response carried a body.
	 */
	public function metadata(): ?ProviderMetadata {
		return $this->metadata;
	}

	/**
	 * Unix timestamp of the fetch attempt.
	 */
	public function fetched_at(): int {
		return $this->fetched_at;
	}

	/**
	 * Provider identifier for this fetch.
	 */
	public function provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * Whether the provider returned HTTP 304 Not Modified.
	 */
	public function is_not_modified(): bool {
		return $this->not_modified;
	}

	/**
	 * Whether the refresh found no automatic currency targets.
	 */
	public function is_no_automatic_targets(): bool {
		return $this->no_automatic_targets;
	}

	/**
	 * Whether every targeted currency succeeded with no failures.
	 */
	public function is_complete_success(): bool {
		return ! $this->not_modified
			&& ! $this->no_automatic_targets
			&& array() !== $this->quotes
			&& array() === $this->failures;
	}

	/**
	 * Whether some targets succeeded and some failed.
	 */
	public function is_partial_failure(): bool {
		return ! $this->not_modified
			&& ! $this->no_automatic_targets
			&& array() !== $this->quotes
			&& array() !== $this->failures;
	}

	/**
	 * Whether every targeted currency failed.
	 */
	public function is_total_failure(): bool {
		return ! $this->not_modified
			&& ! $this->no_automatic_targets
			&& array() === $this->quotes
			&& array() !== $this->failures;
	}
}
