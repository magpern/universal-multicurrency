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

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Operational rate-update state store.
	 *
	 * @var RateUpdateState
	 */
	private RateUpdateState $state;

	/**
	 * Store base currency code.
	 *
	 * @var string
	 */
	private string $base_currency_code;

	/**
	 * Lock owner token for fetch coordination.
	 *
	 * @var string
	 */
	private string $lock_owner;

	/**
	 * Binds the store to settings, operational state, and the base currency.
	 *
	 * @param Settings        $settings           Settings store.
	 * @param RateUpdateState $state              Operational state store.
	 * @param string          $base_currency_code Store base currency code.
	 * @param string|null     $lock_owner         Lock owner token (generated when omitted).
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

	/**
	 * Returns the current global rate configuration snapshot.
	 */
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
	 * Returns every enabled automatic currency code.
	 *
	 * @return string[]
	 */
	public function get_automatic_currency_codes(): array {
		$codes = array();

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			$enabled = ! isset( $config['enabled'] ) ? true : (bool) $config['enabled'];

			if ( ! $enabled ) {
				continue;
			}

			if ( Settings::RATE_MODE_AUTOMATIC === $this->settings->get_effective_rate_mode( (string) $code ) ) {
				$codes[] = (string) $code;
			}
		}

		sort( $codes );

		return $codes;
	}

	/**
	 * Whether any enabled currency has effective automatic rate mode.
	 */
	public function has_automatic_targets(): bool {
		return array() !== $this->get_automatic_currency_codes();
	}

	/**
	 * Whether the update lock is currently held by any owner.
	 */
	public function is_lock_held(): bool {
		return $this->state->is_lock_held();
	}

	/**
	 * Unix timestamp of the last rate-update run attempt.
	 */
	public function last_run_at(): int {
		return (int) ( $this->state->get()['last_run_at'] ?? 0 );
	}

	/**
	 * Bounded failure history from operational state.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function failure_history(): array {
		$history = $this->state->get()['failure_history'] ?? array();

		return is_array( $history ) ? $history : array();
	}

	/**
	 * Returns metadata from the last successful provider fetch.
	 */
	public function get_last_provider_metadata(): ?ProviderMetadata {
		$raw = $this->state->get()['provider_metadata'] ?? null;

		if ( ! is_array( $raw ) || array() === $raw ) {
			return null;
		}

		return ProviderMetadata::from_array( $raw );
	}

	/**
	 * Returns operational status for one currency.
	 *
	 * @param string $code Currency code.
	 */
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

	/**
	 * Attempts to acquire the update lock for this store instance.
	 */
	public function try_acquire_lock(): bool {
		return $this->state->try_acquire_lock( $this->lock_owner );
	}

	/**
	 * Releases the update lock held by this store instance.
	 */
	public function release_lock(): void {
		$this->state->release_lock();
	}

	/**
	 * Persists settings and operational state from a fetch result.
	 *
	 * @param RateFetchResult $result  Fetch outcome to apply.
	 * @param string[]|null   $targets Automatic currency codes targeted by this fetch; null = all automatic currencies.
	 */
	public function apply_fetch_result( RateFetchResult $result, ?array $targets = null ): void {
		$fetched_at = $result->fetched_at();
		$targets    = $this->normalize_fetch_targets( $targets );

		if ( $result->is_not_modified() ) {
			$this->apply_not_modified_state( $targets, $fetched_at );
			return;
		}

		$this->apply_settings_from_result( $result );
		$this->apply_state_from_result( $result, $targets );
	}

	/**
	 * Normalizes the currency codes affected by one fetch attempt.
	 *
	 * @param string[]|null $targets Requested targets, or null for every automatic currency.
	 * @return string[]
	 */
	private function normalize_fetch_targets( ?array $targets ): array {
		if ( null === $targets ) {
			return $this->get_automatic_currency_codes();
		}

		$allowed = array_fill_keys( $this->get_automatic_currency_codes(), true );
		$clean   = array();

		foreach ( $targets as $code ) {
			$code = strtoupper( trim( (string) $code ) );

			if ( '' === $code || ! isset( $allowed[ $code ] ) ) {
				continue;
			}

			$clean[] = $code;
		}

		sort( $clean );

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Updates operational state after an HTTP 304 not-modified response.
	 *
	 * @param string[] $targets    Automatic currency codes.
	 * @param int      $fetched_at Unix timestamp of the fetch attempt.
	 */
	private function apply_not_modified_state( array $targets, int $fetched_at ): void {
		$state                    = $this->state->get();
		$state['last_run_at']     = $fetched_at;
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

	/**
	 * Writes successful provider quotes into merchant settings.
	 *
	 * @param RateFetchResult $result Fetch outcome to apply.
	 */
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
	 * Updates operational state from a fetch result.
	 *
	 * @param RateFetchResult $result  Fetch outcome to apply.
	 * @param string[]        $targets Automatic currency codes targeted by the fetch.
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
				$row['last_status']           = RateUpdateState::STATUS_SUCCESS;
				$row['last_error']            = '';
				$row['consecutive_failures']  = 0;
				$state['currencies'][ $code ] = $row;
				continue;
			}

			$error = $failures[ $code ] ?? ( $result->is_total_failure() ? 'provider_unavailable' : 'not_returned_by_provider' );

			$row['last_status']           = RateUpdateState::STATUS_FAILED;
			$row['last_error']            = $this->cap_error( (string) $error );
			$row['consecutive_failures']  = (int) ( $row['consecutive_failures'] ?? 0 ) + 1;
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
	 * Prepends one failure entry to the bounded history list.
	 *
	 * @param list<array<string, mixed>> $history Existing history.
	 * @param int                        $at      Unix timestamp of the failure.
	 * @param string                     $scope   Currency code or scope label.
	 * @param string                     $error   Failure reason.
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

	/**
	 * Caps an error string to the supported maximum length.
	 *
	 * @param string $error Raw error string.
	 */
	private function cap_error( string $error ): string {
		if ( strlen( $error ) <= RateUpdateState::ERROR_MAX_LENGTH ) {
			return $error;
		}

		return substr( $error, 0, RateUpdateState::ERROR_MAX_LENGTH );
	}
}
