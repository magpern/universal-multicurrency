<?php
/**
 * Operational rate-update state store.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Pure sanitize/defaults for the `umc_rate_state` option.
 *
 * Only {@see ExchangeRateStore} reads or writes this option in production.
 */
class RateUpdateState {

	public const OPTION = 'umc_rate_state';

	public const SCHEMA_VERSION = 1;

	public const STATUS_SUCCESS = 'success';

	public const STATUS_FAILED = 'failed';

	public const STATUS_NEVER = 'never';

	public const FAILURE_HISTORY_CAP = 10;

	public const LOCK_TTL_SECONDS = 120;

	public const ERROR_MAX_LENGTH = 200;

	/**
	 * In-memory operational state.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data = null;

	/**
	 * Creates an operational state store.
	 *
	 * @param array<string, mixed>|null $data Optional in-memory state.
	 */
	public function __construct( ?array $data = null ) {
		if ( null !== $data ) {
			$this->data = self::sanitize( $data );
		}
	}

	/**
	 * Returns the default operational state shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'schema_version'    => self::SCHEMA_VERSION,
			'provider_metadata' => null,
			'currencies'        => array(),
			'last_run_at'       => 0,
			'next_run_at'       => 0,
			'failure_history'   => array(),
			'lock'              => array(
				'owner'      => '',
				'expires_at' => 0,
			),
		);
	}

	/**
	 * Sanitizes a persisted operational state payload.
	 *
	 * @param mixed $raw Raw persisted value.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $raw ): array {
		$clean = self::defaults();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		if ( isset( $raw['provider_metadata'] ) && is_array( $raw['provider_metadata'] ) ) {
			$clean['provider_metadata'] = ProviderMetadata::from_array( $raw['provider_metadata'] )->to_array();
		}

		if ( isset( $raw['currencies'] ) && is_array( $raw['currencies'] ) ) {
			foreach ( $raw['currencies'] as $code => $row ) {
				$code = is_string( $code ) ? strtoupper( trim( $code ) ) : '';

				if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) || ! is_array( $row ) ) {
					continue;
				}

				$clean['currencies'][ $code ] = self::sanitize_currency_row( $row );
			}
		}

		$clean['last_run_at'] = self::sanitize_timestamp( $raw['last_run_at'] ?? 0 );
		$clean['next_run_at'] = self::sanitize_timestamp( $raw['next_run_at'] ?? 0 );

		if ( isset( $raw['failure_history'] ) && is_array( $raw['failure_history'] ) ) {
			$clean['failure_history'] = self::sanitize_failure_history( $raw['failure_history'] );
		}

		if ( isset( $raw['lock'] ) && is_array( $raw['lock'] ) ) {
			$clean['lock'] = self::sanitize_lock( $raw['lock'] );
		}

		return $clean;
	}

	/**
	 * Returns the current operational state, loading from the option when needed.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		if ( null === $this->data ) {
			$this->data = self::sanitize( get_option( self::OPTION, false ) );
		}

		return $this->data;
	}

	/**
	 * Persists sanitized operational state.
	 *
	 * @param array<string, mixed> $state State to persist.
	 */
	public function save( array $state ): void {
		$this->data = self::sanitize( $state );
		update_option( self::OPTION, $this->data );
	}

	/**
	 * Attempts to acquire the update lock for the given owner.
	 *
	 * @param string $owner Lock owner token.
	 */
	public function try_acquire_lock( string $owner ): bool {
		$state = $this->get();
		$lock  = $state['lock'];
		$now   = time();

		if ( is_array( $lock ) && ! empty( $lock['expires_at'] ) && (int) $lock['expires_at'] > $now ) {
			return false;
		}

		$state['lock'] = array(
			'owner'      => $owner,
			'expires_at' => $now + self::LOCK_TTL_SECONDS,
		);

		$this->save( $state );

		return true;
	}

	/**
	 * Releases the update lock.
	 */
	public function release_lock(): void {
		$state         = $this->get();
		$state['lock'] = array(
			'owner'      => '',
			'expires_at' => 0,
		);
		$this->save( $state );
	}

	/**
	 * Whether an unexpired update lock is currently held.
	 */
	public function is_lock_held(): bool {
		$lock = $this->get()['lock'] ?? null;

		if ( ! is_array( $lock ) ) {
			return false;
		}

		return ! empty( $lock['expires_at'] ) && (int) $lock['expires_at'] > time();
	}

	/**
	 * Sanitizes one per-currency operational row.
	 *
	 * @param array<string, mixed> $row Raw currency operational row.
	 * @return array<string, mixed>
	 */
	private static function sanitize_currency_row( array $row ): array {
		$status = isset( $row['last_status'] ) ? (string) $row['last_status'] : self::STATUS_NEVER;

		if ( ! in_array( $status, array( self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_NEVER ), true ) ) {
			$status = self::STATUS_NEVER;
		}

		$error = isset( $row['last_error'] ) ? (string) $row['last_error'] : '';

		if ( strlen( $error ) > self::ERROR_MAX_LENGTH ) {
			$error = substr( $error, 0, self::ERROR_MAX_LENGTH );
		}

		$failures = ( isset( $row['consecutive_failures'] ) && is_numeric( $row['consecutive_failures'] ) )
			? max( 0, (int) $row['consecutive_failures'] )
			: 0;

		return array(
			'last_fetch_at'        => self::sanitize_timestamp( $row['last_fetch_at'] ?? 0 ),
			'last_status'          => $status,
			'last_error'           => $error,
			'consecutive_failures' => $failures,
		);
	}

	/**
	 * Sanitizes a timestamp field.
	 *
	 * @param mixed $value Raw timestamp.
	 */
	private static function sanitize_timestamp( mixed $value ): int {
		if ( ! is_numeric( $value ) ) {
			return 0;
		}

		return max( 0, (int) $value );
	}

	/**
	 * Sanitizes the bounded failure history list.
	 *
	 * @param array<int, mixed> $history Raw failure history.
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_failure_history( array $history ): array {
		$clean = array();

		foreach ( $history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$scope = isset( $entry['scope'] ) ? strtoupper( trim( (string) $entry['scope'] ) ) : '';
			$error = isset( $entry['error'] ) ? (string) $entry['error'] : '';

			if ( strlen( $error ) > self::ERROR_MAX_LENGTH ) {
				$error = substr( $error, 0, self::ERROR_MAX_LENGTH );
			}

			$clean[] = array(
				'at'    => self::sanitize_timestamp( $entry['at'] ?? 0 ),
				'scope' => $scope,
				'error' => $error,
			);

			if ( count( $clean ) >= self::FAILURE_HISTORY_CAP ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes the update lock row.
	 *
	 * @param array<string, mixed> $lock Raw lock row.
	 * @return array{owner: string, expires_at: int}
	 */
	private static function sanitize_lock( array $lock ): array {
		return array(
			'owner'      => isset( $lock['owner'] ) ? (string) $lock['owner'] : '',
			'expires_at' => self::sanitize_timestamp( $lock['expires_at'] ?? 0 ),
		);
	}
}
