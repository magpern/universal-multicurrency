<?php
/**
 * Unit test bootstrap: composer autoloader only, WordPress is not loaded.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/Support/OptionWriteMetrics.php';

use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\Tests\Support\OptionWriteMetrics;

if ( ! defined( 'UMC_VERSION' ) ) {
	define( 'UMC_VERSION', '0.0.0-test' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		unset( $args );
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
		if ( Settings::OPTION === $option ) {
			OptionWriteMetrics::record_settings_write();
		}

		if ( RateUpdateState::OPTION === $option ) {
			OptionWriteMetrics::record_rate_state_write();
		}

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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return (string) $url;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value, $url = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( false === $url ) {
			$url = '/';
		}

		$separator = str_contains( (string) $url, '?' ) ? '&' : '?';

		return (string) $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}

// Test doubles never match *Test.php, so PHPUnit's directory-based
// discovery never loads them; required explicitly, matching how
// tests/integration/bootstrap.php loads StoreApiTestCase.
require_once __DIR__ . '/Doubles/ArrayEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/CountingEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/MapMetadataProvider.php';
require_once __DIR__ . '/Doubles/StaticDetectorRegistry.php';
require_once __DIR__ . '/Doubles/ThrowingRateUpdateState.php';
require_once __DIR__ . '/Doubles/WooCommerceSwitcherDoubles.php';
