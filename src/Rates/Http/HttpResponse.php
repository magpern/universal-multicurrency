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

	private int $status_code;

	/** @var array<string, string> */
	private array $headers;

	private string $body;

	private bool $is_error;

	/**
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

	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * @return array<string, string>
	 */
	public function headers(): array {
		return $this->headers;
	}

	public function body(): string {
		return $this->body;
	}

	public function is_error(): bool {
		return $this->is_error;
	}

	public function header( string $name ): ?string {
		$key = strtolower( $name );

		return $this->headers[ $key ] ?? null;
	}
}
