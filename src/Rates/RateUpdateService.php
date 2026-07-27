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

	private ExchangeRateSource $source;

	private ExchangeRateStore $store;

	private string $base_currency_code;

	/**
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
	 * @param string[]|null $codes Null = every automatic currency.
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
				$result = RateFetchResult::not_modified( $this->source->id(), time() );
				$this->store->release_lock();
				return $result;
			}

			$metadata = $this->store->get_last_provider_metadata();
			$result   = $this->source->fetch( $this->base_currency_code, $targets, $metadata );

			$this->store->apply_fetch_result( $result );
			$this->store->release_lock();

			/**
			 * Fires after a rate fetch attempt completes.
			 *
			 * @param RateFetchResult $result Fetch outcome.
			 */
			do_action( 'umc_rate_fetch_completed', $result );

			return $result;
		} catch ( \Throwable $exception ) {
			$this->store->release_lock();
			throw $exception;
		}
	}
}
