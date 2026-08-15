<?php
/**
 * Minimal WP_CLI stub for direct, non-process-exiting command testing.
 *
 * Not PSR-4 autoloadable (global-namespace class) — required explicitly
 * from tests/integration/bootstrap.php, matching how tests/unit/bootstrap.php
 * requires its own WC_Order stub.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

use UMC\Tests\Support\WpCliExitException;

// PricesCommand (like RatesCommand) is deliberately WP_CLI-framework-free at
// the class level so it stays autoloadable/testable without a real WP-CLI
// runtime; this stub exists only so its `\WP_CLI::...` calls resolve when
// exercised directly (bypassing \WP_CLI::add_command() dispatch entirely).
if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Test double for the global WP_CLI framework class.
	 */
	final class WP_CLI {

		/**
		 * Messages passed to success(), for test assertions.
		 *
		 * @var array<int, string>
		 */
		public static array $success_messages = array();

		/**
		 * Messages passed to warning(), for test assertions.
		 *
		 * @var array<int, string>
		 */
		public static array $warning_messages = array();

		/**
		 * Resets captured state between tests.
		 */
		public static function reset(): void {
			self::$success_messages = array();
			self::$warning_messages = array();
		}

		/**
		 * @param string $message Success message.
		 */
		public static function success( $message ): void {
			self::$success_messages[] = (string) $message;
		}

		/**
		 * @param string $message Warning message.
		 */
		public static function warning( $message ): void {
			self::$warning_messages[] = (string) $message;
		}

		/**
		 * @param string   $message  Error message.
		 * @param bool|int $do_exit  Whether/what to exit with.
		 *
		 * @throws WpCliExitException Always, when $do_exit is truthy.
		 */
		public static function error( $message, $do_exit = true ): void {
			if ( $do_exit ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only exception message, never rendered as output.
				throw new WpCliExitException( (string) $message, true === $do_exit ? 1 : (int) $do_exit );
			}
		}

		/**
		 * @param int $code Exit code.
		 *
		 * @throws WpCliExitException Always.
		 */
		public static function halt( $code ): void {
			throw new WpCliExitException( 'halt', (int) $code );
		}

		/**
		 * @param string $command      Command name (unused by the stub).
		 * @param object $implementation Command implementation (unused by the stub).
		 */
		public static function add_command( $command, $implementation ): void {
			unset( $command, $implementation );
		}
	}
}
