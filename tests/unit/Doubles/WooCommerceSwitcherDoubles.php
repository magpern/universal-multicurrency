<?php
/**
 * Minimal WooCommerce stubs for CurrencySwitcher unit tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WooCommerce API stubs for unit tests.

if ( ! function_exists( 'wc_setcookie' ) ) {
	/**
	 * Records cookie writes for unit tests.
	 *
	 * @param string $name     Cookie name.
	 * @param string $value    Cookie value.
	 * @param int    $expire   Expiry timestamp.
	 * @param bool   $secure   Whether cookie is secure.
	 * @param bool   $httponly Whether cookie is HTTP-only.
	 */
	function wc_setcookie( string $name, string $value, int $expire, bool $secure = false, bool $httponly = false ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		unset( $secure, $httponly, $expire );
		$GLOBALS['umc_currency_switcher_test_cookies'][ $name ] = $value;
	}
}

if ( ! class_exists( 'WC_Session_Stub', false ) ) {
	/**
	 * Minimal WooCommerce session stub for unit tests.
	 */
	final class WC_Session_Stub {
		/**
		 * Reads a session value.
		 *
		 * @param string $key Session key.
		 */
		public function get( string $key ) {
			return $GLOBALS['umc_currency_switcher_test_session'][ $key ] ?? null;
		}

		/**
		 * Writes a session value.
		 *
		 * @param string $key   Session key.
		 * @param mixed  $value Session value.
		 */
		public function set( string $key, $value ): void {
			$GLOBALS['umc_currency_switcher_test_session'][ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WC_Stub', false ) ) {
	/**
	 * Minimal WooCommerce facade stub for unit tests.
	 */
	final class WC_Stub {
		/**
		 * Session double.
		 *
		 * @var WC_Session_Stub
		 */
		public WC_Session_Stub $session;

		public function __construct() {
			$this->session = new WC_Session_Stub();
		}

		/**
		 * Returns the shared stub instance.
		 *
		 * @return self
		 */
		public static function instance(): self {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new self();
			}

			return $instance;
		}

		/**
		 * Whether the current request is a REST request.
		 *
		 * @return bool
		 */
		public function is_rest_api_request(): bool {
			return false;
		}
	}
}

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Returns the WooCommerce stub instance.
	 *
	 * @return WC_Stub
	 */
	function WC(): WC_Stub { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return WC_Stub::instance();
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	/**
	 * Whether the request is over HTTPS.
	 *
	 * @return bool
	 */
	function is_ssl(): bool { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		return false;
	}
}
