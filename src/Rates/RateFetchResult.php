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

	/** @var RateQuote[] */
	private array $quotes;

	/** @var array<string, string> */
	private array $failures;

	private ?ProviderMetadata $metadata;

	private int $fetched_at;

	private bool $not_modified;

	private string $provider_id;

	/**
	 * @param RateQuote[]           $quotes       Successful quotes.
	 * @param array<string, string> $failures     Per-target failure reasons.
	 * @param ProviderMetadata|null $metadata     Batch metadata (null when not modified).
	 * @param int                   $fetched_at   Unix timestamp of the fetch attempt.
	 * @param bool                  $not_modified Whether the provider returned HTTP 304.
	 * @param string                $provider_id  Provider identifier.
	 */
	private function __construct(
		array $quotes,
		array $failures,
		?ProviderMetadata $metadata,
		int $fetched_at,
		bool $not_modified,
		string $provider_id
	) {
		$this->quotes       = $quotes;
		$this->failures     = $failures;
		$this->metadata     = $metadata;
		$this->fetched_at   = $fetched_at;
		$this->not_modified = $not_modified;
		$this->provider_id  = $provider_id;
	}

	/**
	 * @param RateQuote[]           $quotes   Successful quotes.
	 * @param array<string, string> $failures Per-target failure reasons.
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
	 * @return RateQuote[]
	 */
	public function quotes(): array {
		return $this->quotes;
	}

	/**
	 * @return array<string, string>
	 */
	public function failures(): array {
		return $this->failures;
	}

	public function metadata(): ?ProviderMetadata {
		return $this->metadata;
	}

	public function fetched_at(): int {
		return $this->fetched_at;
	}

	public function provider_id(): string {
		return $this->provider_id;
	}

	public function is_not_modified(): bool {
		return $this->not_modified;
	}

	public function is_partial_failure(): bool {
		return ! $this->not_modified && array() !== $this->quotes && array() !== $this->failures;
	}

	public function is_total_failure(): bool {
		return ! $this->not_modified && array() === $this->quotes && array() !== $this->failures;
	}
}
