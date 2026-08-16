<?php
/**
 * Converter double that deliberately inverts a regular/sale pair.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use UMC\Currency;
use UMC\Integration\DisplayPriceConverter;

/**
 * Used to prove ADR-0030's deliberate M24 hardening: prior to
 * {@see \UMC\Pricing\FixedPriceDocumentMerger}'s extraction,
 * {@see \UMC\Pricing\FixedPriceCatalogOperationsService::seed()} never
 * validated the final converted pair, so an FX conversion that happened to
 * invert sale/regular at the target currency's decimal precision (a
 * decimal-rounding edge case, never observed in production and not
 * exercised by any pre-existing M24 test) would have been silently
 * persisted as an invalid fixed price.
 *
 * {@see \UMC\Pricing\FixedPriceCatalogOperationsService::seed_one()} calls
 * `convert_to()` at most twice per product — once for the authored regular
 * price, then (only when an authored sale price is also present) once more
 * for the sale price. This double returns a low value on the first call and
 * a high value on every call after, deterministically engineering an
 * inverted result regardless of the real native amounts supplied, without
 * needing to reverse-engineer an actual rounding edge case.
 */
final class InvertingDisplayPriceConverter implements DisplayPriceConverter {

	/**
	 * Number of {@see convert_to()} invocations so far.
	 *
	 * @var int
	 */
	private int $calls = 0;

	/**
	 * {@inheritDoc}
	 */
	public function convert( mixed $amount ) {
		return $amount;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed    $amount Unused; this double ignores the real amount.
	 * @param Currency $target Unused.
	 * @param string   $rate   Unused.
	 */
	public function convert_to( mixed $amount, Currency $target, string $rate ) {
		unset( $amount, $target, $rate );

		++$this->calls;

		return 1 === $this->calls ? '10.00' : '20.00';
	}
}
