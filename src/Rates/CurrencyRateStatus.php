<?php
/**
 * Per-currency operational rate status.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Read model for admin diagnostics and Site Health.
 */
final class CurrencyRateStatus {

	private string $code;

	private int $last_fetch_at;

	private string $last_status;

	private string $last_error;

	private int $consecutive_failures;

	public function __construct(
		string $code,
		int $last_fetch_at,
		string $last_status,
		string $last_error,
		int $consecutive_failures
	) {
		$this->code                 = strtoupper( $code );
		$this->last_fetch_at        = max( 0, $last_fetch_at );
		$this->last_status          = $last_status;
		$this->last_error           = $last_error;
		$this->consecutive_failures = max( 0, $consecutive_failures );
	}

	public function code(): string {
		return $this->code;
	}

	public function last_fetch_at(): int {
		return $this->last_fetch_at;
	}

	public function last_status(): string {
		return $this->last_status;
	}

	public function last_error(): string {
		return $this->last_error;
	}

	public function consecutive_failures(): int {
		return $this->consecutive_failures;
	}
}
