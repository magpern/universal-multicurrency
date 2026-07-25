<?php

/**
 * Display-price conversion seam.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\Converter;
use UMC\Currency;
use UMC\CurrencyContext;

/**
 * The single seam that turns a base-currency amount into a display value.
 *
 * All WooCommerce integration points (Milestone 2 price hooks, and later cart /
 * coupon / shipping conversion) go through this service rather than calling the
 * {@see Converter} directly. It owns the display rules — empty/non-numeric
 * passthrough and the base-currency no-op — so those live in exactly one place.
 *
 * It only ever calls the converter with a positive rate the context guarantees,
 * so it does not throw on valid display input.
 */
final class PriceConversionService {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the service to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Converts a base amount into the active currency for display.
	 *
	 * Returns the value unchanged when it is empty/non-numeric or when the base
	 * currency is active. Otherwise multiplies by the active rate and rounds to
	 * the active currency's decimals.
	 *
	 * @param mixed $amount Base-currency amount (string|int|float), or '' / null.
	 * @return mixed Converted decimal string, or the original value when not converted.
	 */
	public function convert( mixed $amount ) {
		return $this->convert_to( $amount, $this->context->get_active_currency(), $this->context->get_rate() );
	}

	/**
	 * Converts a known-numeric base amount into the active currency, as a string.
	 *
	 * The single entry point for the Milestone 3 transaction integrations (coupon
	 * and shipping conversion): those callers hold plain numeric amounts (coupon
	 * amounts, shipping costs) rather than product-price values, and must never
	 * reach {@see Converter} directly. It reuses {@see convert()} — so the
	 * base no-op and empty/non-numeric passthrough rules still live in one place —
	 * and normalizes the result to a string WooCommerce can consume.
	 *
	 * Callers are expected to gate on the active currency themselves and only call
	 * this when a conversion is wanted; on the base-currency no-op the amount is
	 * returned unchanged (stringified).
	 *
	 * @param string|int|float $base Base-currency amount.
	 * @return string Converted decimal string, or the stringified original on no-op.
	 */
	public function convert_amount( string|int|float $base ): string {
		$converted = $this->convert( $base );

		return is_scalar( $converted ) ? (string) $converted : '';
	}

	/**
	 * Converts a base amount into a specific target currency for display.
	 *
	 * @param mixed    $amount Base-currency amount (string|int|float), or '' / null.
	 * @param Currency $target Target currency (supplies the decimals).
	 * @param string   $rate   Positive base→target decimal rate string.
	 * @return mixed Converted decimal string, or the original value when not converted.
	 */
	public function convert_to( mixed $amount, Currency $target, string $rate ) {
		if ( ! $this->is_convertible_amount( $amount ) ) {
			return $amount;
		}

		if ( $target->code() === $this->context->get_base_currency()->code() ) {
			return $amount;
		}

		return Converter::apply_rate( $amount, $rate, $target->decimals() );
	}

	/**
	 * Whether an amount is a real number that should be converted.
	 *
	 * Empty string, null and non-numeric values (e.g. an unset sale price) pass
	 * through untouched — never coerced to 0.
	 *
	 * @param mixed $amount Candidate amount.
	 */
	private function is_convertible_amount( mixed $amount ): bool {
		if ( '' === $amount || null === $amount ) {
			return false;
		}

		return is_numeric( $amount );
	}
}
