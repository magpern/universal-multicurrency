<?php
/**
 * One stage in a currency decision explanation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Decision;

/**
 * Immutable structured stage. Merchant prose belongs in admin renderers.
 */
final class ExplanationStage {

	public const STATUS_WON        = 'won';
	public const STATUS_CONSIDERED = 'considered';
	public const STATUS_SKIPPED    = 'skipped';
	public const STATUS_BLOCKED    = 'blocked';
	public const STATUS_INFO       = 'info';

	/**
	 * Creates a stage.
	 *
	 * @param string               $id      Stage id.
	 * @param string               $status  Status code.
	 * @param string               $reason  Reason code (may be empty).
	 * @param array<string, mixed> $payload Structured payload.
	 */
	public function __construct(
		private string $id,
		private string $status,
		private string $reason = '',
		private array $payload = array()
	) {
	}

	/**
	 * Stage identifier.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Status code.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Reason code.
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Structured payload.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(): array {
		return $this->payload;
	}

	/**
	 * Array representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'      => $this->id,
			'status'  => $this->status,
			'reason'  => $this->reason,
			'payload' => $this->payload,
		);
	}
}
