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

// Test doubles never match *Test.php, so PHPUnit's directory-based
// discovery never loads them; required explicitly, matching how
// tests/integration/bootstrap.php loads StoreApiTestCase.
require_once __DIR__ . '/Doubles/ArrayEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/CountingEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/StaticDetectorRegistry.php';
