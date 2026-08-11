<?php
/**
 * Immutable exchange-rate health read model.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Snapshot consumed by admin, Site Health, Compatibility, and CLI.
 */
final class RateHealthReport {

	/**
	 * Builds a health report from pre-aggregated fields.
	 *
	 * @param string   $provider_id              Configured provider identifier.
	 * @param string   $global_mode              Global rate mode.
	 * @param int      $automatic_target_count   Enabled automatic currencies.
	 * @param int      $manual_target_count      Enabled manual currencies.
	 * @param int      $disabled_count           Disabled currencies.
	 * @param int      $fresh_count              Automatic currencies with fresh rates.
	 * @param int      $aging_count              Automatic currencies with aging rates.
	 * @param int      $stale_count              Automatic currencies with stale rates.
	 * @param int      $unavailable_count        Automatic currencies without a usable refresh status.
	 * @param int      $last_attempt_at          Unix timestamp of the latest fetch attempt.
	 * @param int      $last_success_at          Unix timestamp of the latest successful fetch.
	 * @param int      $last_failure_at          Unix timestamp of the latest failed fetch.
	 * @param string   $last_failure_code        Sanitized failure code.
	 * @param string   $last_failure_detail      Bounded sanitized failure detail.
	 * @param int      $consecutive_failures_max Max consecutive failures across currencies.
	 * @param int|null $next_scheduled_at        Next Action Scheduler run, or null when none.
	 * @param bool     $lock_held                Whether the update lock is held.
	 * @param bool     $scheduler_missing        Automatic targets exist but no AS action is scheduled.
	 * @param bool     $has_automatic_targets    Whether any enabled automatic currency exists.
	 */
	public function __construct(
		private string $provider_id,
		private string $global_mode,
		private int $automatic_target_count,
		private int $manual_target_count,
		private int $disabled_count,
		private int $fresh_count,
		private int $aging_count,
		private int $stale_count,
		private int $unavailable_count,
		private int $last_attempt_at,
		private int $last_success_at,
		private int $last_failure_at,
		private string $last_failure_code,
		private string $last_failure_detail,
		private int $consecutive_failures_max,
		private ?int $next_scheduled_at,
		private bool $lock_held,
		private bool $scheduler_missing,
		private bool $has_automatic_targets
	) {
	}

	/**
	 * Configured provider identifier.
	 */
	public function provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * Global rate mode.
	 */
	public function global_mode(): string {
		return $this->global_mode;
	}

	/**
	 * Count of enabled automatic currencies.
	 */
	public function automatic_target_count(): int {
		return $this->automatic_target_count;
	}

	/**
	 * Count of enabled manual currencies.
	 */
	public function manual_target_count(): int {
		return $this->manual_target_count;
	}

	/**
	 * Count of disabled currencies.
	 */
	public function disabled_count(): int {
		return $this->disabled_count;
	}

	/**
	 * Count of automatic currencies with fresh rates.
	 */
	public function fresh_count(): int {
		return $this->fresh_count;
	}

	/**
	 * Count of automatic currencies with aging rates.
	 */
	public function aging_count(): int {
		return $this->aging_count;
	}

	/**
	 * Count of automatic currencies with stale rates.
	 */
	public function stale_count(): int {
		return $this->stale_count;
	}

	/**
	 * Count of automatic currencies that are unavailable for refresh health.
	 */
	public function unavailable_count(): int {
		return $this->unavailable_count;
	}

	/**
	 * Unix timestamp of the latest fetch attempt.
	 */
	public function last_attempt_at(): int {
		return $this->last_attempt_at;
	}

	/**
	 * Unix timestamp of the latest successful fetch.
	 */
	public function last_success_at(): int {
		return $this->last_success_at;
	}

	/**
	 * Unix timestamp of the latest failed fetch.
	 */
	public function last_failure_at(): int {
		return $this->last_failure_at;
	}

	/**
	 * Sanitized last failure code.
	 */
	public function last_failure_code(): string {
		return $this->last_failure_code;
	}

	/**
	 * Bounded sanitized last failure detail.
	 */
	public function last_failure_detail(): string {
		return $this->last_failure_detail;
	}

	/**
	 * Maximum consecutive failures across currencies.
	 */
	public function consecutive_failures_max(): int {
		return $this->consecutive_failures_max;
	}

	/**
	 * Next Action Scheduler timestamp, or null when none is scheduled.
	 */
	public function next_scheduled_at(): ?int {
		return $this->next_scheduled_at;
	}

	/**
	 * Whether the update lock is currently held.
	 */
	public function lock_held(): bool {
		return $this->lock_held;
	}

	/**
	 * Whether automatic targets exist but Action Scheduler has no next run.
	 */
	public function scheduler_missing(): bool {
		return $this->scheduler_missing;
	}

	/**
	 * Whether any enabled automatic currency exists.
	 */
	public function has_automatic_targets(): bool {
		return $this->has_automatic_targets;
	}

	/**
	 * Array representation for CLI and diagnostics consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'provider_id'              => $this->provider_id,
			'global_mode'              => $this->global_mode,
			'automatic_target_count'   => $this->automatic_target_count,
			'manual_target_count'      => $this->manual_target_count,
			'disabled_count'           => $this->disabled_count,
			'fresh_count'              => $this->fresh_count,
			'aging_count'              => $this->aging_count,
			'stale_count'              => $this->stale_count,
			'unavailable_count'        => $this->unavailable_count,
			'last_attempt_at'          => $this->last_attempt_at,
			'last_success_at'          => $this->last_success_at,
			'last_failure_at'          => $this->last_failure_at,
			'last_failure_code'        => $this->last_failure_code,
			'last_failure_detail'      => $this->last_failure_detail,
			'consecutive_failures_max' => $this->consecutive_failures_max,
			'next_scheduled_at'        => $this->next_scheduled_at,
			'lock_held'                => $this->lock_held,
			'scheduler_missing'        => $this->scheduler_missing,
			'has_automatic_targets'    => $this->has_automatic_targets,
		);
	}
}
