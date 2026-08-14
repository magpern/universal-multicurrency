<?php
/**
 * Maps persisted origin to reporting classification.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\CurrencySwitcher;
use UMC\Order\OrderCurrencySnapshot;

/**
 * Maps persisted origin metadata to reporting classification.
 */
final class ReportingOriginClassifier {

	/**
	 * Classifies one order snapshot for reporting origin buckets.
	 *
	 * @param OrderCurrencySnapshot $snapshot Persisted currency snapshot.
	 */
	public function classify( OrderCurrencySnapshot $snapshot ): string {
		$origin = $snapshot->currency_origin();

		if ( CurrencySwitcher::ORIGIN_CUSTOMER === $origin ) {
			return CurrencySwitcher::ORIGIN_CUSTOMER;
		}

		if ( CurrencySwitcher::ORIGIN_VISITOR_LOCATION === $origin ) {
			return CurrencySwitcher::ORIGIN_VISITOR_LOCATION;
		}

		return ReportingConstants::ORIGIN_UNKNOWN;
	}
}
