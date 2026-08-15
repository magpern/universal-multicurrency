<?php
/**
 * Minimal WP_CLI\Utils stub for direct command testing.
 *
 * Not PSR-4 autoloadable (WP_CLI-namespaced, not UMC-namespaced) — required
 * explicitly from tests/integration/bootstrap.php alongside WpCliTestStub.php.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace WP_CLI;

if ( ! class_exists( __NAMESPACE__ . '\\Utils' ) ) {
	/**
	 * Test double for WP_CLI\Utils — captures table rows for assertions
	 * instead of rendering to a terminal.
	 */
	final class Utils {

		/**
		 * Rows passed to the most recent format_items() call.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $last_items = array();

		/**
		 * Resets captured state between tests.
		 */
		public static function reset(): void {
			self::$last_items = array();
		}

		/**
		 * @param string                           $format Output format (unused by the stub).
		 * @param array<int, array<string, mixed>> $items  Rows to format.
		 * @param array<int, string>|string        $fields Column keys (unused by the stub).
		 */
		public static function format_items( $format, $items, $fields ): void {
			unset( $format, $fields );

			self::$last_items = $items;
		}
	}
}
