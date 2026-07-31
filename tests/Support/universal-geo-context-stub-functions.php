<?php
/**
 * Global function stubs for Universal Geo Context's public API.
 *
 * Loaded only by {@see \UMC\Tests\Support\UniversalGeoContextStub::install()}
 * — never by the test bootstrap directly — so a test that never calls
 * install() genuinely never defines these functions and can observe
 * `function_exists()` returning false (with `@runInSeparateProcess`).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

use UMC\Tests\Support\UniversalGeoContextStub;

if ( ! function_exists( 'universal_geo_get_country_code' ) ) {
	/**
	 * Stubbed Universal Geo Context country resolver.
	 */
	function universal_geo_get_country_code() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return UniversalGeoContextStub::country();
	}
}

if ( ! function_exists( 'universal_geo_api_version' ) ) {
	/**
	 * Stubbed Universal Geo Context public API version.
	 */
	function universal_geo_api_version() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return UniversalGeoContextStub::api_version();
	}
}

if ( ! function_exists( 'universal_geo_get_source' ) ) {
	/**
	 * Stubbed Universal Geo Context resolution source.
	 */
	function universal_geo_get_source() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return UniversalGeoContextStub::source();
	}
}

if ( ! function_exists( 'universal_geo_get_confidence' ) ) {
	/**
	 * Stubbed Universal Geo Context resolution confidence.
	 */
	function universal_geo_get_confidence() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return UniversalGeoContextStub::confidence();
	}
}
