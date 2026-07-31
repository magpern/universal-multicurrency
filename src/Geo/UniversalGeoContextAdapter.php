<?php
/**
 * Universal Geo Context public API adapter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Reads country context from the optional Universal Geo Context plugin.
 */
final class UniversalGeoContextAdapter implements CountryContextProviderInterface {

	/**
	 * Provider identifier.
	 */
	public function id(): string {
		return 'universal_geo_context';
	}

	/**
	 * Whether Universal Geo Context API is available.
	 *
	 * Delegates to {@see UgcIntegrationStatus}, the single source of truth for
	 * availability shared with admin and diagnostics surfaces.
	 */
	public function is_available(): bool {
		return ( new UgcIntegrationStatus() )->is_available();
	}

	/**
	 * Resolves country from Universal Geo Context.
	 */
	public function resolve(): ?CountryContext {
		if ( ! $this->is_available() ) {
			return null;
		}

		$country = universal_geo_get_country_code();

		if ( ! is_string( $country ) || '' === $country ) {
			return null;
		}

		$country = strtoupper( trim( $country ) );

		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return null;
		}

		$source     = function_exists( 'universal_geo_get_source' ) ? (string) universal_geo_get_source() : 'universal_geo_context';
		$confidence = function_exists( 'universal_geo_get_confidence' ) ? (float) universal_geo_get_confidence() : 1.0;

		return new CountryContext( $country, $source, $confidence );
	}
}
