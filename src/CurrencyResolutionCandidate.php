<?php
/**
 * One shopper-currency candidate evaluation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Immutable evaluation of a single resolver candidate.
 */
final class CurrencyResolutionCandidate {

	public const STATUS_EMPTY    = 'empty';
	public const STATUS_ACCEPTED = 'accepted';
	public const STATUS_REJECTED = 'rejected';

	public const REJECT_MALFORMED      = 'malformed';
	public const REJECT_NOT_SELECTABLE = 'not_selectable';

	/**
	 * Creates a candidate evaluation.
	 *
	 * @param string      $source         Candidate source id (explicit|session|cookie).
	 * @param string|null $raw_value      Raw input value.
	 * @param string|null $normalized     Normalized uppercase code, if any.
	 * @param string      $status         empty|accepted|rejected.
	 * @param string|null $reject_reason  Rejection reason code, if rejected.
	 */
	public function __construct(
		private string $source,
		private ?string $raw_value,
		private ?string $normalized,
		private string $status,
		private ?string $reject_reason = null
	) {
	}

	/**
	 * Candidate source identifier.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Raw input value.
	 */
	public function raw_value(): ?string {
		return $this->raw_value;
	}

	/**
	 * Normalized currency code, if present.
	 */
	public function normalized(): ?string {
		return $this->normalized;
	}

	/**
	 * Evaluation status.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Rejection reason, when rejected.
	 */
	public function reject_reason(): ?string {
		return $this->reject_reason;
	}

	/**
	 * Array representation for tests and explanation payloads.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'source'        => $this->source,
			'raw_value'     => $this->raw_value,
			'normalized'    => $this->normalized,
			'status'        => $this->status,
			'reject_reason' => $this->reject_reason,
		);
	}
}
