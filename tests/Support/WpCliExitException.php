<?php
/**
 * Exception thrown by the WP_CLI test stub instead of exiting the process.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use RuntimeException;

/**
 * Thrown by {@see WP_CLI}'s error()/halt() stub methods so a command under
 * test can be asserted on instead of terminating the PHPUnit process.
 */
final class WpCliExitException extends RuntimeException {

	/**
	 * Exit code WP_CLI would have used.
	 *
	 * @var int
	 */
	private int $exit_code;

	/**
	 * @param string $message   CLI error message.
	 * @param int    $exit_code Intended process exit code.
	 */
	public function __construct( string $message, int $exit_code ) {
		parent::__construct( $message );

		$this->exit_code = $exit_code;
	}

	/**
	 * The exit code WP_CLI would have used.
	 */
	public function exit_code(): int {
		return $this->exit_code;
	}
}
