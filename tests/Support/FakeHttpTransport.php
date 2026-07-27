<?php
/**
 * In-memory HTTP transport for tests.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

use UMC\Rates\Http\HttpResponse;
use UMC\Rates\Http\HttpTransport;

/**
 * Returns canned responses keyed by URL.
 */
final class FakeHttpTransport implements HttpTransport {

	/** @var array<string, HttpResponse> */
	private array $responses = array();

	/** @var list<array{url: string, headers: array<string, string>}> */
	private array $requests = array();

	/**
	 * @param string      $url      URL pattern or exact URL.
	 * @param HttpResponse $response Canned response.
	 */
	public function register( string $url, HttpResponse $response ): void {
		$this->responses[ $url ] = $response;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string               $url     Request URL.
	 * @param array<string, string> $headers Optional request headers.
	 * @param int                  $timeout Timeout in seconds.
	 */
	public function get( string $url, array $headers = array(), int $timeout = 15 ): HttpResponse {
		unset( $timeout );

		$this->requests[] = array(
			'url'     => $url,
			'headers' => $headers,
		);

		return $this->responses[ $url ] ?? new HttpResponse( 404, array(), '', false );
	}

	/**
	 * @return list<array{url: string, headers: array<string, string>}>
	 */
	public function requests(): array {
		return $this->requests;
	}

	public function request_count(): int {
		return count( $this->requests );
	}
}
