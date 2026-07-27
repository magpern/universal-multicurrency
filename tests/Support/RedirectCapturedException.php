<?php
/**
 * Captures a redirect target instead of terminating the test process.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use RuntimeException;

/**
 * Thrown from a `wp_redirect` filter so a controller's `exit` is never reached.
 */
final class RedirectCapturedException extends RuntimeException {

	/**
	 * Redirect target passed to `wp_safe_redirect()`.
	 *
	 * @var string
	 */
	private string $location;

	/**
	 * @param string $location Redirect target.
	 */
	public function __construct( string $location ) {
		parent::__construct( 'Redirect captured: ' . $location );

		$this->location = $location;
	}

	/**
	 * The captured redirect target.
	 */
	public function location(): string {
		return $this->location;
	}

	/**
	 * Returns one decoded query argument from the captured target.
	 *
	 * @param string $key Query argument name.
	 */
	public function query_arg( string $key ): ?string {
		$query = (string) wp_parse_url( $this->location, PHP_URL_QUERY );

		if ( '' === $query ) {
			return null;
		}

		$args = array();
		parse_str( $query, $args );

		return isset( $args[ $key ] ) ? (string) $args[ $key ] : null;
	}
}
