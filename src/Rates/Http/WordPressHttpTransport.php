<?php
/**
 * WordPress HTTP transport for rate providers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates\Http;

/**
 * The only production class permitted to call wp_safe_remote_get() for rates.
 */
final class WordPressHttpTransport implements HttpTransport {

	/**
	 * {@inheritDoc}
	 *
	 * @param string                $url     Request URL.
	 * @param array<string, string> $headers Optional request headers.
	 * @param int                   $timeout Timeout in seconds.
	 */
	public function get( string $url, array $headers = array(), int $timeout = 15 ): HttpResponse {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => max( 1, $timeout ),
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new HttpResponse( 0, array(), '', true );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );
		$raw    = wp_remote_retrieve_headers( $response );
		$flat   = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $key => $value ) {
				if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
					$flat[ strtolower( $key ) ] = (string) $value;
				}
			}
		}

		return new HttpResponse( $status, $flat, $body, false );
	}
}
