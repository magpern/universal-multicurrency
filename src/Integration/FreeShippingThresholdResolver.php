<?php
/**
 * Shared free-shipping threshold resolution for eligibility and public display.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

use UMC\CurrencyContext;

/**
 * Resolves a base-authored WooCommerce free-shipping `min_amount` into the
 * active shopper currency using the same conversion seam checkout uses.
 *
 * Does not multiply rates or round money itself — foreign amounts go only
 * through {@see PriceConversionService::convert_amount()}.
 *
 * Display-only policy (Option A over-precision, missing-rate fail-closed,
 * convertible-request gating) lives in
 * {@see \UMC\PublicApi\FreeShippingThresholdDisplayService} so eligibility
 * semantics stay identical to pre-v1.2.0 conversion behaviour.
 */
final class FreeShippingThresholdResolver {

	/**
	 * Conversion seam.
	 *
	 * @var PriceConversionService
	 */
	private PriceConversionService $service;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds conversion seam and currency context.
	 *
	 * @param PriceConversionService $service Conversion seam.
	 * @param CurrencyContext        $context Request-scoped currency facade.
	 */
	public function __construct( PriceConversionService $service, CurrencyContext $context ) {
		$this->service = $service;
		$this->context = $context;
	}

	/**
	 * Resolves a base-currency threshold for the active shopper currency.
	 *
	 * Returns null only for empty / non-numeric / negative input. Foreign
	 * conversion uses {@see PriceConversionService::convert_amount()} (existing
	 * rate resolution, including legacy `'1'` fallback inside get_rate for
	 * eligibility parity).
	 *
	 * @param string $base_threshold Base-authored decimal string.
	 */
	public function resolve( string $base_threshold ): ?FreeShippingThreshold {
		$trimmed = trim( $base_threshold );

		if ( '' === $trimmed || ! is_numeric( $trimmed ) ) {
			return null;
		}

		if ( (float) $trimmed < 0.0 ) {
			return null;
		}

		$base = $this->context->get_base_currency();

		if ( $this->context->is_base_active() ) {
			return new FreeShippingThreshold(
				$this->canonical_base_amount( $trimmed, $base->decimals() ),
				$base->code()
			);
		}

		$converted = $this->service->convert_amount( $trimmed );

		if ( '' === $converted || ! is_numeric( $converted ) ) {
			return null;
		}

		return new FreeShippingThreshold(
			$converted,
			$this->context->get_active_code()
		);
	}

	/**
	 * Whether a base-authored threshold exceeds base-currency fractional precision.
	 *
	 * Used by the public display service (ADR-0034 Option A). Eligibility does
	 * not call this — WooCommerce / Converter behaviour for over-precise
	 * settings remains unchanged.
	 *
	 * @param string $base_threshold Raw input string.
	 */
	public function exceeds_base_precision( string $base_threshold ): bool {
		$trimmed = trim( $base_threshold );

		if ( '' === $trimmed || ! is_numeric( $trimmed ) ) {
			return false;
		}

		return $this->fractional_digits_exceed(
			$trimmed,
			$this->context->get_base_currency()->decimals()
		);
	}

	/**
	 * Whether fractional digits exceed the allowed base-currency precision.
	 *
	 * @param string $amount   Trimmed numeric string.
	 * @param int    $decimals Base currency decimals.
	 */
	private function fractional_digits_exceed( string $amount, int $decimals ): bool {
		$normalized = ltrim( $amount, '+' );

		if ( str_contains( $normalized, 'e' ) || str_contains( $normalized, 'E' ) ) {
			return true;
		}

		$dot = strpos( $normalized, '.' );

		if ( false === $dot ) {
			return false;
		}

		$fraction = substr( $normalized, $dot + 1 );

		return strlen( $fraction ) > max( 0, $decimals );
	}

	/**
	 * Canonical base-currency decimal string without FX.
	 *
	 * @param string $amount   Valid numeric string (≤ base decimals).
	 * @param int    $decimals Base decimals.
	 */
	private function canonical_base_amount( string $amount, int $decimals ): string {
		$decimals = max( 0, $decimals );

		return number_format( (float) $amount, $decimals, '.', '' );
	}
}
