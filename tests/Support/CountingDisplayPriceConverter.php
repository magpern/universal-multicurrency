<?php
/**
 * Counting test double for display-price conversion instrumentation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

use UMC\Currency;
use UMC\Integration\DisplayPriceConverter;
use UMC\Integration\PriceConversionService;

/**
 * Delegates to {@see PriceConversionService} while recording convert attempts.
 */
final class CountingDisplayPriceConverter implements DisplayPriceConverter {

	/**
	 * Underlying converter.
	 *
	 * @var PriceConversionService
	 */
	private PriceConversionService $delegate;

	/**
	 * Number of convert() calls that reached numeric conversion.
	 *
	 * @var int
	 */
	private int $convert_calls = 0;

	/**
	 * Wraps a real conversion service.
	 *
	 * @param PriceConversionService $delegate Real converter.
	 */
	public function __construct( PriceConversionService $delegate ) {
		$this->delegate = $delegate;
	}

	/**
	 * Returns the number of recorded conversion attempts.
	 */
	public function convert_calls(): int {
		return $this->convert_calls;
	}

	/**
	 * Resets the conversion call counter.
	 */
	public function reset(): void {
		$this->convert_calls = 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $amount Base-currency amount.
	 */
	public function convert( mixed $amount ) {
		++$this->convert_calls;

		return $this->delegate->convert( $amount );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed    $amount Base-currency amount.
	 * @param Currency $target Target currency.
	 * @param string   $rate   Exchange rate.
	 */
	public function convert_to( mixed $amount, Currency $target, string $rate ) {
		++$this->convert_calls;

		return $this->delegate->convert_to( $amount, $target, $rate );
	}
}
