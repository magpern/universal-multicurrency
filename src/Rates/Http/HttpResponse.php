<?php
/**
 * HTTP response value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates\Http;

/**
 * Normalized HTTP response for provider parsing.
 */
final class HttpResponse {

	/**
	 * HTTP status code (0 on transport error).
	 *
	 * @var int
	 */
	private int $status_code;

	/**
	 * Response headers keyed by lowercase name.
	 *
	 * @var array<string, string>
	 */
	private array $headers;

	/**
	 * Response body.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * Whether the transport layer failed.
	 *
	 * @var bool
	 */
	private bool $is_error;

	/**
	 * Builds a normalized HTTP response.
	 *
	 * @param int                   $status_code HTTP status code (0 on transport error).
	 * @param array<string, string> $headers     Response headers (lowercase keys).
	 * @param string                $body        Response body.
	 * @param bool                  $is_error    Whether the transport layer failed.
	 */
	public function __construct( int $status_code, array $headers, string $body, bool $is_error = false ) {
		$this->status_code = $status_code;
		$this->headers     = $headers;
		$this->body        = $body;
		$this->is_error    = $is_error;
	}

	/**
	 * The HTTP status code.
	 */
	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * All response headers keyed by lowercase name.
	 *
	 * @return array<string, string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * The response body.
	 */
	public function body(): string {
		return $this->body;
	}

	/**
	 * Whether the transport layer failed.
	 */
	public function is_error(): bool {
		return $this->is_error;
	}

	/**
	 * Returns one response header by name.
	 *
	 * @param string $name Header name.
	 */
	public function header( string $name ): ?string {
		$key = strtolower( $name );

		return $this->headers[ $key ] ?? null;
	}
}
