<?php
/**
 * HTTP transport abstraction for rate providers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates\Http;

/**
 * Performs outbound HTTP GET requests.
 */
interface HttpTransport {

	/**
	 * @param string               $url     Request URL.
	 * @param array<string, string> $headers Optional request headers.
	 * @param int                  $timeout Timeout in seconds.
	 * @return HttpResponse
	 */
	public function get( string $url, array $headers = array(), int $timeout = 15 ): HttpResponse;
}
