<?php
/**
 * Read-only exchange-rate health aggregation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Builds {@see RateHealthReport} snapshots without HTTP or mutations.
 */
final class RateHealthService {

	/**
	 * Binds health aggregation to settings, store, and status evaluator.
	 *
	 * @param Settings            $settings  Merchant settings store.
	 * @param ExchangeRateStore   $store     Rate persistence boundary.
	 * @param RateStatusEvaluator $evaluator Per-currency status evaluator.
	 */
	public function __construct(
		private Settings $settings,
		private ExchangeRateStore $store,
		private RateStatusEvaluator $evaluator
	) {
	}

	/**
	 * Builds the current health report.
	 */
	public function report(): RateHealthReport {
		$config = $this->store->get_configuration();

		$automatic_target_count = 0;
		$manual_target_count    = 0;
		$disabled_count         = 0;
		$fresh_count            = 0;
		$aging_count            = 0;
		$stale_count            = 0;
		$unavailable_count      = 0;

		$last_attempt_at          = $this->store->last_run_at();
		$last_success_at          = 0;
		$last_failure_at          = 0;
		$last_failure_code        = '';
		$last_failure_detail      = '';
		$consecutive_failures_max = 0;

		foreach ( $this->settings->get_currencies() as $code => $row ) {
			$code    = strtoupper( (string) $code );
			$enabled = ! isset( $row['enabled'] ) ? true : (bool) $row['enabled'];
			$mode    = $this->settings->get_effective_rate_mode( $code );
			$status  = $this->store->get_operational_status( $code );

			$fetch_at = $status->last_fetch_at();

			if ( $fetch_at > $last_attempt_at ) {
				$last_attempt_at = $fetch_at;
			}

			if ( $status->consecutive_failures() > $consecutive_failures_max ) {
				$consecutive_failures_max = $status->consecutive_failures();
			}

			if ( RateUpdateState::STATUS_SUCCESS === $status->last_status() && $fetch_at > $last_success_at ) {
				$last_success_at = $fetch_at;
			}

			if ( RateUpdateState::STATUS_FAILED === $status->last_status() && $fetch_at >= $last_failure_at && $fetch_at > 0 ) {
				$last_failure_at     = $fetch_at;
				$last_failure_code   = RateFailureCode::sanitize( $status->last_error() );
				$last_failure_detail = $this->sanitize_failure_detail( $status->last_error() );
			}

			$updated_at = (int) ( $row['rate_updated_at'] ?? 0 );

			if ( $updated_at > $last_success_at && '' !== (string) ( $row['provider_rate'] ?? '' ) ) {
				$last_success_at = $updated_at;
			}

			if ( ! $enabled ) {
				++$disabled_count;
				continue;
			}

			if ( Settings::RATE_MODE_MANUAL === $mode ) {
				++$manual_target_count;
				continue;
			}

			++$automatic_target_count;

			$label = $this->evaluator->label_for_currency( $code );

			match ( $label ) {
				RateStatusEvaluator::LABEL_OK    => ++$fresh_count,
				RateStatusEvaluator::LABEL_AGING => ++$aging_count,
				RateStatusEvaluator::LABEL_STALE => ++$stale_count,
				default                          => ++$unavailable_count,
			};
		}

		foreach ( $this->store->failure_history() as $entry ) {
			$at = (int) ( $entry['at'] ?? 0 );

			if ( $at <= $last_failure_at ) {
				continue;
			}

			$last_failure_at     = $at;
			$raw                 = (string) ( $entry['error'] ?? '' );
			$last_failure_code   = RateFailureCode::sanitize( $raw );
			$last_failure_detail = $this->sanitize_failure_detail( $raw );
		}

		$has_automatic_targets = $this->store->has_automatic_targets();
		$next_scheduled_at     = $this->next_scheduled_at();
		$as_available          = function_exists( 'as_next_scheduled_action' );
		$scheduler_missing     = $has_automatic_targets && null === $next_scheduled_at && $as_available;

		return new RateHealthReport(
			$config->rate_provider(),
			$config->rate_mode(),
			$automatic_target_count,
			$manual_target_count,
			$disabled_count,
			$fresh_count,
			$aging_count,
			$stale_count,
			$unavailable_count,
			$last_attempt_at,
			$last_success_at,
			$last_failure_at,
			$last_failure_code,
			$last_failure_detail,
			$consecutive_failures_max,
			$next_scheduled_at,
			$this->store->is_lock_held(),
			$scheduler_missing,
			$has_automatic_targets
		);
	}

	/**
	 * Next Action Scheduler timestamp for the rate-update hook, if any.
	 */
	private function next_scheduled_at(): ?int {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}

		$next = as_next_scheduled_action( Scheduler::HOOK );

		if ( false === $next ) {
			return null;
		}

		return (int) $next;
	}

	/**
	 * Bounds and sanitizes a failure detail string for display.
	 *
	 * @param string $detail Raw failure detail.
	 */
	private function sanitize_failure_detail( string $detail ): string {
		$detail = trim( $detail );

		if ( strlen( $detail ) > RateUpdateState::ERROR_MAX_LENGTH ) {
			$detail = substr( $detail, 0, RateUpdateState::ERROR_MAX_LENGTH );
		}

		return $detail;
	}
}
