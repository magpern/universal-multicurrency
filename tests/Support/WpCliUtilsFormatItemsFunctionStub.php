<?php
/**
 * Minimal WP_CLI\Utils\format_items() namespaced-function stub.
 *
 * Not PSR-4 autoloadable (WP_CLI-namespaced, not UMC-namespaced) — required
 * explicitly from tests/integration/bootstrap.php alongside
 * WpCliUtilsTestStub.php. Kept in its own file because a single file may not
 * mix function declarations with OO structures (Universal.Files.SeparateFunctionsFromOO).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace WP_CLI\Utils;

if ( ! function_exists( __NAMESPACE__ . '\\format_items' ) ) {
	/**
	 * Namespaced-function form of format_items(), matching real WP-CLI's
	 * actual API (\WP_CLI\Utils\format_items(), not a static class method —
	 * see RatesCommand's usage, contrasted with PricesCommand's latent bug
	 * calling the non-existent \WP_CLI\Utils::format_items() static form).
	 * Captures rows into the same Utils::$last_items used by the class-method
	 * stub in WpCliUtilsTestStub.php, since both forms exist only as test
	 * doubles here and share one assertion surface.
	 *
	 * @param string                           $format Output format (unused by the stub).
	 * @param array<int, array<string, mixed>> $items  Rows to format.
	 * @param array<int, string>|string        $fields Column keys (unused by the stub).
	 */
	function format_items( $format, $items, $fields ): void { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Namespaced test stub mirrors WP_CLI\Utils\format_items().
		unset( $format, $fields );

		\WP_CLI\Utils::$last_items = $items;
	}
}
