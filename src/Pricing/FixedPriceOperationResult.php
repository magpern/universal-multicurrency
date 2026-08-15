<?php
/**
 * Result of one catalog-wide fixed-price seed or clear operation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Immutable outcome of {@see FixedPriceCatalogOperationsService::seed()} or
 * {@see FixedPriceCatalogOperationsService::clear()}.
 *
 * Either the whole operation is aborted before any writes (unknown/base
 * currency, or — for seed — no rate available), or it completes with
 * per-product succeeded/skipped/failed outcomes and, for seed, the single
 * rate actually used for every write in the operation.
 */
final class FixedPriceOperationResult {

	public const ABORT_BASE_CURRENCY    = 'base_currency_rejected';
	public const ABORT_UNKNOWN_CURRENCY = 'unknown_currency';
	public const ABORT_NO_RATE          = 'no_rate_available';

	/**
	 * Captures one operation outcome.
	 *
	 * @param array<int, int>    $succeeded  Product/variation IDs written successfully.
	 * @param array<int, string> $skipped    Product/variation ID => skip reason.
	 * @param array<int, string> $failed     Product/variation ID => failure reason.
	 * @param string|null        $rate_used  Rate actually applied (seed only), or null.
	 * @param string|null        $abort_reason One of the ABORT_* constants, or null when not aborted.
	 */
	private function __construct(
		private array $succeeded,
		private array $skipped,
		private array $failed,
		private ?string $rate_used,
		private ?string $abort_reason
	) {
	}

	/**
	 * Builds an aborted result — no product was written.
	 *
	 * @param string $reason One of the ABORT_* constants.
	 */
	public static function aborted( string $reason ): self {
		return new self( array(), array(), array(), null, $reason );
	}

	/**
	 * Builds a completed result.
	 *
	 * @param array<int, int>    $succeeded Product/variation IDs written successfully.
	 * @param array<int, string> $skipped   Product/variation ID => skip reason.
	 * @param array<int, string> $failed    Product/variation ID => failure reason.
	 * @param string|null        $rate_used Rate actually applied (seed only), or null for clear.
	 */
	public static function completed( array $succeeded, array $skipped, array $failed, ?string $rate_used = null ): self {
		return new self( $succeeded, $skipped, $failed, $rate_used, null );
	}

	/**
	 * Whether the operation was aborted before any writes.
	 */
	public function is_aborted(): bool {
		return null !== $this->abort_reason;
	}

	/**
	 * Abort reason, or null when the operation completed.
	 */
	public function abort_reason(): ?string {
		return $this->abort_reason;
	}

	/**
	 * Product/variation IDs written successfully.
	 *
	 * @return array<int, int>
	 */
	public function succeeded(): array {
		return $this->succeeded;
	}

	/**
	 * Product/variation ID => skip reason.
	 *
	 * @return array<int, string>
	 */
	public function skipped(): array {
		return $this->skipped;
	}

	/**
	 * Product/variation ID => failure reason.
	 *
	 * @return array<int, string>
	 */
	public function failed(): array {
		return $this->failed;
	}

	/**
	 * The single rate applied to every write in this operation (seed only).
	 */
	public function rate_used(): ?string {
		return $this->rate_used;
	}

	/**
	 * Total number of products/variations processed (succeeded + skipped + failed).
	 */
	public function total_processed(): int {
		return count( $this->succeeded ) + count( $this->skipped ) + count( $this->failed );
	}
}
