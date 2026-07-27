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

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Unix timestamp of the last fetch attempt.
	 *
	 * @var int
	 */
	private int $last_fetch_at;

	/**
	 * Last fetch status code.
	 *
	 * @var string
	 */
	private string $last_status;

	/**
	 * Last fetch error message, if any.
	 *
	 * @var string
	 */
	private string $last_error;

	/**
	 * Count of consecutive failed fetch attempts.
	 *
	 * @var int
	 */
	private int $consecutive_failures;

	/**
	 * Builds a per-currency operational status read model.
	 *
	 * @param string $code                 Currency code.
	 * @param int    $last_fetch_at        Unix timestamp of the last fetch attempt.
	 * @param string $last_status          Last fetch status code.
	 * @param string $last_error           Last fetch error message.
	 * @param int    $consecutive_failures Count of consecutive failed fetch attempts.
	 */
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

	/**
	 * The currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Unix timestamp of the last fetch attempt.
	 */
	public function last_fetch_at(): int {
		return $this->last_fetch_at;
	}

	/**
	 * The last fetch status code.
	 */
	public function last_status(): string {
		return $this->last_status;
	}

	/**
	 * The last fetch error message.
	 */
	public function last_error(): string {
		return $this->last_error;
	}

	/**
	 * The count of consecutive failed fetch attempts.
	 */
	public function consecutive_failures(): int {
		return $this->consecutive_failures;
	}
}
