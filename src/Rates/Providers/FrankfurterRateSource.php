<?php
/**
 * Frankfurter (ECB) exchange-rate source.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates\Providers;

use UMC\Rates\ExchangeRateSource;
use UMC\Rates\Http\HttpTransport;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateQuote;
use UMC\Settings;

/**
 * Fetches latest fiat rates from the Frankfurter public API.
 */
final class FrankfurterRateSource implements ExchangeRateSource {

	private const ENDPOINT = 'https://api.frankfurter.dev/v1/latest';

	private const PROVIDER_ID = 'frankfurter';

	private const HEADER_MAX_LENGTH = 200;

	/**
	 * ISO codes Frankfurter/ECB publish (subset used for capability checks).
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_CURRENCIES = array(
		'AUD',
		'BGN',
		'BRL',
		'CAD',
		'CHF',
		'CNY',
		'CZK',
		'DKK',
		'EUR',
		'GBP',
		'HKD',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'ISK',
		'JPY',
		'KRW',
		'MXN',
		'MYR',
		'NOK',
		'NZD',
		'PHP',
		'PLN',
		'RON',
		'SEK',
		'SGD',
		'THB',
		'TRY',
		'USD',
		'ZAR',
	);

	private HttpTransport $transport;

	/**
	 * @param HttpTransport|null $transport HTTP transport (defaults to WordPress in production wiring).
	 */
	public function __construct( ?HttpTransport $transport = null ) {
		$this->transport = $transport ?? new \UMC\Rates\Http\WordPressHttpTransport();
	}

	public function id(): string {
		return self::PROVIDER_ID;
	}

	public function label(): string {
		return 'Frankfurter';
	}

	public function supports_conditional_requests(): bool {
		return true;
	}

	public function supports_historical_rates(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $codes Currency codes to check.
	 */
	public function supports_currencies( array $codes ): bool {
		if ( array() === $codes ) {
			return false;
		}

		$allowed = array_fill_keys( self::SUPPORTED_CURRENCIES, true );

		foreach ( $codes as $code ) {
			$upper = strtoupper( trim( (string) $code ) );

			if ( ! isset( $allowed[ $upper ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $target_codes Target currency codes (never empty).
	 */
	public function fetch( string $base_code, array $target_codes, ?ProviderMetadata $previous = null ): RateFetchResult {
		$base_code    = strtoupper( trim( $base_code ) );
		$target_codes = array_values(
			array_unique(
				array_map(
					static fn( $code ): string => strtoupper( trim( (string) $code ) ),
					$target_codes
				)
			)
		);

		$fetched_at = time();
		$url        = self::ENDPOINT . '?base=' . rawurlencode( $base_code ) . '&symbols=' . rawurlencode( implode( ',', $target_codes ) );
		$headers    = $this->conditional_headers( $previous );

		$response = $this->transport->get( $url, $headers, 15 );

		if ( $response->is_error() ) {
			return $this->total_failure( $target_codes, 'provider_unavailable', $fetched_at );
		}

		if ( 304 === $response->status_code() ) {
			return RateFetchResult::not_modified( self::PROVIDER_ID, $fetched_at );
		}

		if ( $response->status_code() < 200 || $response->status_code() >= 300 ) {
			return $this->total_failure( $target_codes, 'provider_unavailable', $fetched_at );
		}

		$payload = json_decode( $response->body(), true );

		if ( ! is_array( $payload ) || ! isset( $payload['rates'] ) || ! is_array( $payload['rates'] ) ) {
			return $this->total_failure( $target_codes, 'invalid_response', $fetched_at );
		}

		$quotes   = array();
		$failures = array();

		foreach ( $target_codes as $code ) {
			if ( ! array_key_exists( $code, $payload['rates'] ) ) {
				$failures[ $code ] = 'not_returned_by_provider';
				continue;
			}

			$normalized = Settings::normalize_rate( $payload['rates'][ $code ] );

			if ( '' === $normalized ) {
				$failures[ $code ] = 'invalid_response';
				continue;
			}

			$quotes[] = new RateQuote( $base_code, $code, $normalized );
		}

		if ( array() === $quotes && array() !== $failures ) {
			return $this->total_failure( $target_codes, 'invalid_response', $fetched_at );
		}

		$metadata = new ProviderMetadata(
			ProviderMetadata::SCHEMA_VERSION,
			self::PROVIDER_ID,
			isset( $payload['date'] ) ? (string) $payload['date'] : null,
			null,
			$this->cap_header( $response->header( 'etag' ) ),
			$this->cap_header( $response->header( 'last-modified' ) )
		);

		return RateFetchResult::success( $quotes, $failures, $metadata, $fetched_at );
	}

	/**
	 * @return array<string, string>
	 */
	private function conditional_headers( ?ProviderMetadata $previous ): array {
		if ( null === $previous ) {
			return array();
		}

		$headers = array();

		$etag = $previous->etag();

		if ( null !== $etag && '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		$modified = $previous->last_modified();

		if ( null !== $modified && '' !== $modified ) {
			$headers['If-Modified-Since'] = $modified;
		}

		return $headers;
	}

	/**
	 * @param string[] $target_codes Target currency codes.
	 */
	private function total_failure( array $target_codes, string $reason, int $fetched_at ): RateFetchResult {
		$meta     = new ProviderMetadata( ProviderMetadata::SCHEMA_VERSION, self::PROVIDER_ID );
		$failures = array();

		foreach ( $target_codes as $code ) {
			$failures[ $code ] = $reason;
		}

		return RateFetchResult::success(
			array(),
			$failures,
			$meta,
			$fetched_at
		);
	}

	private function cap_header( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( strlen( $value ) > self::HEADER_MAX_LENGTH ) {
			return substr( $value, 0, self::HEADER_MAX_LENGTH );
		}

		return $value;
	}
}
