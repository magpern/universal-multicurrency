<?php
/**
 * Unit test bootstrap: composer autoloader only, WordPress is not loaded.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'UMC_VERSION' ) ) {
	define( 'UMC_VERSION', '0.0.0-test' );
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $value;
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * Minimal admin screen stub for unit tests.
	 */
	function get_current_screen() {
		return null;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Identity translation stub for unit tests without WordPress loaded.
	 *
	 * @param string $text   Source string.
	 * @param string $domain Text domain (ignored).
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $text;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return str_repeat( 'a', (int) $length );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return true;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Minimal plural stub for unit tests without WordPress loaded.
	 *
	 * @param string $single Singular string.
	 * @param string $plural Plural string.
	 * @param int    $number Item count.
	 * @param string $domain Text domain (ignored).
	 */
	function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return 1 === (int) $number ? $single : $plural;
	}
}

// Test doubles never match *Test.php, so PHPUnit's directory-based
// discovery never loads them; required explicitly, matching how
// tests/integration/bootstrap.php loads StoreApiTestCase.
require_once __DIR__ . '/Doubles/ArrayEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/CountingEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/StaticDetectorRegistry.php';
require_once __DIR__ . '/Doubles/ThrowingRateUpdateState.php';
