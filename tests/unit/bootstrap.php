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

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Identity translation stub for unit tests without WordPress loaded.
	 *
	 * @param string $text   Source string.
	 * @param string $domain Text domain (ignored).
	 */
	function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $text;
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

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';

		if ( $echo ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound
		$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';

		if ( $echo ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = null ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( is_array( $key ) ) {
			$args = $key;
			$url  = $value ?? '/';
		} else {
			$args = array( (string) $key => (string) $value );
			$url  = $url ?? '/';
		}

		$parts = array();

		foreach ( $args as $arg_key => $arg_value ) {
			$parts[] = rawurlencode( (string) $arg_key ) . '=' . rawurlencode( (string) $arg_value );
		}

		$separator = str_contains( (string) $url, '?' ) ? '&' : '?';

		return (string) $url . $separator . implode( '&', $parts );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

// Test doubles never match *Test.php, so PHPUnit's directory-based
// discovery never loads them; required explicitly, matching how
// tests/integration/bootstrap.php loads StoreApiTestCase.
require_once __DIR__ . '/Doubles/ArrayEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/CountingEnvironmentProbe.php';
require_once __DIR__ . '/Doubles/FakeCountryContextProvider.php';
require_once __DIR__ . '/Doubles/MapMetadataProvider.php';
require_once __DIR__ . '/Doubles/StaticDetectorRegistry.php';
require_once __DIR__ . '/Doubles/ThrowingRateUpdateState.php';
require_once __DIR__ . '/Doubles/WooCommerceSwitcherDoubles.php';
require_once dirname( __DIR__ ) . '/Support/UniversalGeoContextStub.php';
