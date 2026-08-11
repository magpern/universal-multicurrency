<?php
/**
 * Orchestrates exchange-rate fetches through the store boundary.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Thin service: lock, fetch once, persist, notify.
 */
final class RateUpdateService {

	/**
	 * Configured exchange-rate source.
	 *
	 * @var ExchangeRateSource
	 */
	private ExchangeRateSource $source;

	/**
	 * Persistence boundary for rate data.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $store;

	/**
	 * Store base currency code.
	 *
	 * @var string
	 */
	private string $base_currency_code;

	/**
	 * Binds the update service to its source, store, and base currency.
	 *
	 * @param ExchangeRateSource $source             Rate provider.
	 * @param ExchangeRateStore  $store              Persistence boundary.
	 * @param string             $base_currency_code Store base currency code.
	 */
	public function __construct(
		ExchangeRateSource $source,
		ExchangeRateStore $store,
		string $base_currency_code
	) {
		$this->source             = $source;
		$this->store              = $store;
		$this->base_currency_code = strtoupper( $base_currency_code );
	}

	/**
	 * Fetches and persists rates for automatic currencies.
	 *
	 * @param string[]|null $codes Null = every automatic currency.
	 * @throws UpdateInProgressException When another update holds the lock.
	 */
	public function update( ?array $codes = null ): RateFetchResult {
		if ( ! $this->store->try_acquire_lock() ) {
			throw new UpdateInProgressException( 'A rate update is already in progress.' );
		}

		try {
			$targets = $this->store->get_automatic_currency_codes();

			if ( null !== $codes ) {
				$requested = array_map(
					static fn( $code ): string => strtoupper( trim( (string) $code ) ),
					$codes
				);
				$targets   = array_values( array_intersect( $targets, $requested ) );
			}

			if ( array() === $targets ) {
				return RateFetchResult::no_automatic_targets( $this->source->id(), time() );
			}

			$metadata = $this->store->get_last_provider_metadata();
			$result   = $this->source->fetch( $this->base_currency_code, $targets, $metadata );

			$this->store->apply_fetch_result( $result, $targets );

			/**
			 * Fires after a rate fetch attempt completes.
			 *
			 * @since 0.8.0
			 *
			 * @param RateFetchResult $result Fetch outcome.
			 */
			do_action( 'umc_rate_fetch_completed', $result );

			return $result;
		} finally {
			$this->store->release_lock();
		}
	}
}
