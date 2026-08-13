<?php
/**
 * Normalized fixed-price document stored in product meta.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Versioned map of foreign-currency fixed prices.
 *
 * Does not filter out disabled currencies on read — runtime resolution ignores them.
 */
final class FixedPriceDocument {

	public const SCHEMA_VERSION = 1;

	public const META_KEY = '_umc_fixed_prices';

	/**
	 * Currency map keyed by uppercase ISO code.
	 *
	 * @param array<string, FixedCurrencyPrice> $currencies Keyed by uppercase ISO code.
	 */
	private function __construct(
		private array $currencies
	) {
	}

	/**
	 * Empty document (conversion fallback for all currencies).
	 */
	public static function empty(): self {
		return new self( array() );
	}

	/**
	 * Parses stored post meta into a document.
	 *
	 * @param mixed  $raw                  Stored meta value.
	 * @param string $base_currency_code   Store base currency code.
	 */
	public static function from_storage( mixed $raw, string $base_currency_code = '' ): self {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return self::empty();
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return self::empty();
		}

		$currencies_raw = $decoded['currencies'] ?? $decoded;

		if ( ! is_array( $currencies_raw ) ) {
			return self::empty();
		}

		$base   = strtoupper( $base_currency_code );
		$parsed = array();

		foreach ( $currencies_raw as $code => $entry ) {
			if ( ! is_string( $code ) || ! is_array( $entry ) ) {
				continue;
			}

			$normalized_code = strtoupper( sanitize_text_field( $code ) );

			if ( '' === $normalized_code || ( '' !== $base && $normalized_code === $base ) ) {
				continue;
			}

			$regular = FixedPriceValidator::normalize_price( $entry['regular'] ?? '' );
			$sale    = FixedPriceValidator::normalize_price( $entry['sale'] ?? '' );

			if ( '' === $regular && '' === $sale ) {
				continue;
			}

			$parsed[ $normalized_code ] = new FixedCurrencyPrice( $regular, $sale );
		}

		return new self( $parsed );
	}

	/**
	 * Builds a document from an admin submission map.
	 *
	 * @param array<string, array{regular?:mixed,sale?:mixed}> $currencies         Currency entries.
	 * @param string                                           $base_currency_code Store base currency code.
	 */
	public static function from_array( array $currencies, string $base_currency_code ): self {
		$normalized = array();

		foreach ( $currencies as $code => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$currency_code = strtoupper( sanitize_text_field( (string) $code ) );

			if ( '' === $currency_code || strtoupper( $base_currency_code ) === $currency_code ) {
				continue;
			}

			$regular = FixedPriceValidator::normalize_price( $entry['regular'] ?? '' );
			$sale    = FixedPriceValidator::normalize_price( $entry['sale'] ?? '' );

			if ( '' === $regular && '' === $sale ) {
				continue;
			}

			$normalized[ $currency_code ] = new FixedCurrencyPrice( $regular, $sale );
		}

		return new self( $normalized );
	}

	/**
	 * Fixed price for a currency code, or null when absent.
	 *
	 * @param string $code Currency code.
	 */
	public function get_currency( string $code ): ?FixedCurrencyPrice {
		return $this->currencies[ strtoupper( $code ) ] ?? null;
	}

	/**
	 * Returns all fixed currency entries.
	 *
	 * @return array<string, FixedCurrencyPrice>
	 */
	public function currencies(): array {
		return $this->currencies;
	}

	/**
	 * Whether the document contains any currency entries.
	 */
	public function is_empty(): bool {
		return array() === $this->currencies;
	}

	/**
	 * Whether persisted base-currency data was present in raw storage.
	 *
	 * @param mixed  $raw                  Stored meta value.
	 * @param string $base_currency_code   Store base currency code.
	 */
	public static function raw_contains_base_currency( mixed $raw, string $base_currency_code ): bool {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return false;
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return false;
		}

		$currencies_raw = $decoded['currencies'] ?? $decoded;

		if ( ! is_array( $currencies_raw ) ) {
			return false;
		}

		$base = strtoupper( $base_currency_code );

		foreach ( $currencies_raw as $code => $entry ) {
			if ( strtoupper( (string) $code ) === $base && is_array( $entry ) ) {
				$regular = FixedPriceValidator::normalize_price( $entry['regular'] ?? '' );
				$sale    = FixedPriceValidator::normalize_price( $entry['sale'] ?? '' );

				if ( '' !== $regular || '' !== $sale ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Deterministic fingerprint for variation price cache identity.
	 */
	public function fingerprint(): string {
		if ( $this->is_empty() ) {
			return 'empty';
		}

		$payload = array();

		foreach ( $this->currencies as $code => $price ) {
			$payload[ $code ] = $price->to_array();
		}

		ksort( $payload );

		return md5( (string) wp_json_encode( $payload ) );
	}

	/**
	 * JSON string for post meta storage, or '' to delete meta.
	 */
	public function to_storage_json(): string {
		if ( $this->is_empty() ) {
			return '';
		}

		$currencies = array();

		foreach ( $this->currencies as $code => $price ) {
			$row = array();

			if ( '' !== $price->regular() ) {
				$row['regular'] = $price->regular();
			}

			if ( '' !== $price->sale() ) {
				$row['sale'] = $price->sale();
			}

			if ( array() !== $row ) {
				$currencies[ $code ] = $row;
			}
		}

		if ( array() === $currencies ) {
			return '';
		}

		return (string) wp_json_encode(
			array(
				'schema_version' => self::SCHEMA_VERSION,
				'currencies'     => $currencies,
			)
		);
	}
}
