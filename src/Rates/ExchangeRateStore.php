<?php
/**
 * Sole persistence boundary for exchange-rate data.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Moves rate configuration and operational state between options and services.
 */
final class ExchangeRateStore {

	private Settings $settings;

	private RateUpdateState $state;

	private string $base_currency_code;

	private string $lock_owner;

	/**
	 * @param Settings         $settings           Settings store.
	 * @param RateUpdateState  $state              Operational state store.
	 * @param string           $base_currency_code Store base currency code.
	 * @param string|null      $lock_owner         Lock owner token (generated when omitted).
	 */
	public function __construct(
		Settings $settings,
		RateUpdateState $state,
		string $base_currency_code,
		?string $lock_owner = null
	) {
		$this->settings           = $settings;
		$this->state              = $state;
		$this->base_currency_code = strtoupper( $base_currency_code );
		$this->lock_owner         = $lock_owner ?? wp_generate_password( 12, false );
	}

	public function get_configuration(): RateConfiguration {
		$data     = $this->settings->get();
		$interval = RateUpdateInterval::from_iso8601( (string) ( $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL ) )
			?? RateUpdateInterval::default();

		return new RateConfiguration(
			(string) ( $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL ),
			(string) ( $data['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER ),
			$interval,
			(int) ( $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS )
		);
	}

	/**
	 * @return string[]
	 */
	public function get_automatic_currency_codes(): array {
		$codes = array();

		foreach ( array_keys( $this->settings->get_currencies() ) as $code ) {
			if ( Settings::RATE_MODE_AUTOMATIC === $this->settings->get_effective_rate_mode( $code ) ) {
				$codes[] = $code;
			}
		}

		sort( $codes );

		return $codes;
	}

	public function get_last_provider_metadata(): ?ProviderMetadata {
		$raw = $this->state->get()['provider_metadata'] ?? null;

		if ( ! is_array( $raw ) || array() === $raw ) {
			return null;
		}

		return ProviderMetadata::from_array( $raw );
	}

	public function get_operational_status( string $code ): CurrencyRateStatus {
		$code  = strtoupper( $code );
		$row   = $this->state->get()['currencies'][ $code ] ?? array();
		$state = is_array( $row ) ? $row : array();

		return new CurrencyRateStatus(
			$code,
			(int) ( $state['last_fetch_at'] ?? 0 ),
			(string) ( $state['last_status'] ?? RateUpdateState::STATUS_NEVER ),
			(string) ( $state['last_error'] ?? '' ),
			(int) ( $state['consecutive_failures'] ?? 0 )
		);
	}

	public function try_acquire_lock(): bool {
		return $this->state->try_acquire_lock( $this->lock_owner );
	}

	public function release_lock(): void {
		$this->state->release_lock();
	}

	public function apply_fetch_result( RateFetchResult $result ): void {
		$fetched_at = $result->fetched_at();
		$targets    = $this->get_automatic_currency_codes();

		if ( $result->is_not_modified() ) {
			$this->apply_not_modified_state( $targets, $fetched_at );
			return;
		}

		$this->apply_settings_from_result( $result );
		$this->apply_state_from_result( $result, $targets );
	}

	/**
	 * @param string[] $targets Automatic currency codes.
	 */
	private function apply_not_modified_state( array $targets, int $fetched_at ): void {
		$state                   = $this->state->get();
		$state['last_run_at']    = $fetched_at;
		$state['failure_history'] = is_array( $state['failure_history'] ?? null ) ? $state['failure_history'] : array();

		foreach ( $targets as $code ) {
			$row = $state['currencies'][ $code ] ?? array();
			if ( ! is_array( $row ) ) {
				$row = array();
			}

			$row['last_fetch_at']        = $fetched_at;
			$row['last_status']          = RateUpdateState::STATUS_SUCCESS;
			$row['last_error']           = '';
			$row['consecutive_failures'] = 0;

			$state['currencies'][ $code ] = $row;
		}

		$this->state->save( $state );
	}

	private function apply_settings_from_result( RateFetchResult $result ): void {
		if ( array() === $result->quotes() ) {
			return;
		}

		$data       = $this->settings->get();
		$updated_at = $result->fetched_at();

		foreach ( $result->quotes() as $quote ) {
			if ( $quote->base_code() !== $this->base_currency_code ) {
				continue;
			}

			$code = $quote->target_code();

			if ( ! isset( $data['currencies'][ $code ] ) || ! is_array( $data['currencies'][ $code ] ) ) {
				continue;
			}

			$data['currencies'][ $code ]['provider_rate']   = $quote->rate();
			$data['currencies'][ $code ]['rate_updated_at'] = $updated_at;
		}

		$this->settings->save( $data );
	}

	/**
	 * @param string[] $targets Automatic currency codes targeted by the fetch.
	 */
	private function apply_state_from_result( RateFetchResult $result, array $targets ): void {
		$state                = $this->state->get();
		$fetched_at           = $result->fetched_at();
		$state['last_run_at'] = $fetched_at;

		$metadata = $result->metadata();

		if ( null !== $metadata ) {
			$state['provider_metadata'] = $metadata->to_array();
		}

		$successful = array();

		foreach ( $result->quotes() as $quote ) {
			$successful[ $quote->target_code() ] = true;
		}

		$failures = $result->failures();

		foreach ( $targets as $code ) {
			$row = $state['currencies'][ $code ] ?? array();
			if ( ! is_array( $row ) ) {
				$row = array();
			}

			$row['last_fetch_at'] = $fetched_at;

			if ( isset( $successful[ $code ] ) ) {
				$row['last_status']          = RateUpdateState::STATUS_SUCCESS;
				$row['last_error']           = '';
				$row['consecutive_failures'] = 0;
				$state['currencies'][ $code ] = $row;
				continue;
			}

			$error = $failures[ $code ] ?? ( $result->is_total_failure() ? 'provider_unavailable' : 'not_returned_by_provider' );

			$row['last_status']          = RateUpdateState::STATUS_FAILED;
			$row['last_error']           = $this->cap_error( (string) $error );
			$row['consecutive_failures'] = (int) ( $row['consecutive_failures'] ?? 0 ) + 1;
			$state['currencies'][ $code ] = $row;

			$state['failure_history'] = $this->prepend_failure(
				is_array( $state['failure_history'] ?? null ) ? $state['failure_history'] : array(),
				$fetched_at,
				$code,
				(string) $error
			);
		}

		$this->state->save( $state );
	}

	/**
	 * @param list<array<string, mixed>> $history Existing history.
	 * @return list<array<string, mixed>>
	 */
	private function prepend_failure( array $history, int $at, string $scope, string $error ): array {
		array_unshift(
			$history,
			array(
				'at'    => $at,
				'scope' => $scope,
				'error' => $this->cap_error( $error ),
			)
		);

		return array_slice( $history, 0, RateUpdateState::FAILURE_HISTORY_CAP );
	}

	private function cap_error( string $error ): string {
		if ( strlen( $error ) <= RateUpdateState::ERROR_MAX_LENGTH ) {
			return $error;
		}

		return substr( $error, 0, RateUpdateState::ERROR_MAX_LENGTH );
	}
}
