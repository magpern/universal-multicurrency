<?php
/**
 * Shared deterministic monetary fixtures for Milestone 18 transaction-integrity tests.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

/**
 * Small canonical scenario set for multicurrency cart / shipping / coupon / tax
 * parity tests. Totals expectations should still be derived from WooCommerce
 * APIs after conversion — these constants only pin authored base amounts and
 * the exchange rate.
 */
final class GoldenTransactionFixtures {

	/**
	 * Store base currency.
	 */
	public const BASE = 'SEK';

	/**
	 * Foreign shopper currency.
	 */
	public const FOREIGN = 'EUR';

	/**
	 * Non-trivial base→foreign rate (SEK → EUR).
	 */
	public const RATE = '0.08912347';

	/**
	 * Representative product regular price in base currency.
	 */
	public const PRODUCT_PRICE = '1234.56';

	/**
	 * Flat-rate shipping cost in base currency.
	 */
	public const SHIPPING_COST = '79';

	/**
	 * Fixed cart coupon amount in base currency.
	 */
	public const FIXED_COUPON = '100';

	/**
	 * Percentage coupon amount (currency-agnostic).
	 */
	public const PERCENT_COUPON = '10';

	/**
	 * Free-shipping minimum order amount in base currency.
	 */
	public const FREE_SHIPPING_MIN = '1000';

	/**
	 * Standard VAT percentage used by golden tax scenarios.
	 */
	public const TAX_RATE = 25.0;

	/**
	 * Currencies map for Settings::save().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function currencies(): array {
		return array(
			self::FOREIGN => array(
				'rate'    => self::RATE,
				'enabled' => true,
			),
		);
	}

	/**
	 * Converted free-shipping threshold in the foreign currency, as a float.
	 *
	 * Uses the same Converter seam rounding as production (active decimals = 2).
	 */
	public static function converted_free_shipping_min(): float {
		return (float) \UMC\Converter::apply_rate( self::FREE_SHIPPING_MIN, self::RATE, 2 );
	}

	/**
	 * Converted product unit price in the foreign currency (2 decimals).
	 */
	public static function converted_product_price(): string {
		return \UMC\Converter::apply_rate( self::PRODUCT_PRICE, self::RATE, 2 );
	}
}
