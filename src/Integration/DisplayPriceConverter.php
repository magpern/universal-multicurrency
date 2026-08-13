<?php
/**
 * Display-price conversion contract for product pricing resolution.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\Currency;

/**
 * Seam exercised by {@see ProductPriceResolutionService} for FX fallback only.
 */
interface DisplayPriceConverter {

	/**
	 * Converts a base amount into the active currency for display.
	 *
	 * @param mixed $amount Base-currency amount (string|int|float), or '' / null.
	 * @return mixed Converted decimal string, or the original value when not converted.
	 */
	public function convert( mixed $amount );

	/**
	 * Converts a base amount into a specific target currency for display.
	 *
	 * @param mixed    $amount Base-currency amount (string|int|float), or '' / null.
	 * @param Currency $target Target currency (supplies the decimals).
	 * @param string   $rate   Positive base→target decimal rate string.
	 * @return mixed Converted decimal string, or the original value when not converted.
	 */
	public function convert_to( mixed $amount, Currency $target, string $rate );
}
