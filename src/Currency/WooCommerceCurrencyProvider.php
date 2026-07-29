<?php
/**
 * WooCommerce-backed currency metadata provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Currency;

use UMC\Currency as CurrencyVo;
use UMC\Support\IsoCurrencyDecimals;

/**
 * Delegates currency metadata to WooCommerce core helpers.
 */
final class WooCommerceCurrencyProvider implements CurrencyMetadataProvider {

	/**
	 * Cached currency metadata keyed by ISO code.
	 *
	 * @var array<string, CurrencyMetadata>|null
	 */
	private ?array $cache = null;

	/**
	 * Returns metadata for one ISO code, if known.
	 *
	 * @param string $code ISO currency code.
	 */
	public function get( string $code ): ?CurrencyMetadata {
		$code = strtoupper( trim( $code ) );

		if ( ! $this->is_known( $code ) ) {
			return null;
		}

		return $this->all()[ $code ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$currencies = function_exists( 'get_woocommerce_currencies' )
			? get_woocommerce_currencies()
			: array();

		$all = array();

		foreach ( $currencies as $code => $name ) {
			if ( ! is_string( $code ) || ! is_string( $name ) ) {
				continue;
			}

			$code = strtoupper( trim( $code ) );

			if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) ) {
				continue;
			}

			$all[ $code ] = $this->build_metadata( $code, $name );
		}

		$this->cache = $all;

		return $all;
	}

	/**
	 * Returns currencies matching a search query against name and ISO code.
	 *
	 * @param string $query Search query.
	 * @return array<string, CurrencyMetadata>
	 */
	public function search( string $query ): array {
		$query = strtolower( trim( $query ) );

		if ( '' === $query ) {
			return $this->all();
		}

		$matches = array();

		foreach ( $this->all() as $code => $metadata ) {
			if ( str_contains( strtolower( $code ), $query )
				|| str_contains( strtolower( $metadata->name() ), $query ) ) {
				$matches[ $code ] = $metadata;
			}
		}

		return $matches;
	}

	/**
	 * Whether the provider recognises an ISO code.
	 *
	 * @param string $code ISO currency code.
	 */
	public function is_known( string $code ): bool {
		$code = strtoupper( trim( $code ) );

		return isset( $this->all()[ $code ] );
	}

	/**
	 * Builds metadata for one WooCommerce currency entry.
	 *
	 * @param string $code ISO currency code.
	 * @param string $name Currency name from WooCommerce.
	 */
	private function build_metadata( string $code, string $name ): CurrencyMetadata {
		$symbol = function_exists( 'get_woocommerce_currency_symbol' )
			? (string) get_woocommerce_currency_symbol( $code )
			: $code;

		$decimals = max(
			0,
			min( CurrencyVo::MAX_DECIMALS, IsoCurrencyDecimals::decimals( $code ) )
		);

		return new CurrencyMetadata(
			$code,
			$name,
			$symbol,
			$decimals,
			CurrencyPositionDefaults::for_currency( $code, $symbol )
		);
	}
}
