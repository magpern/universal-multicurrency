<?php
/**
 * Batch-level metadata from an exchange-rate provider fetch.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Immutable value object describing one provider fetch (not per currency).
 */
final class ProviderMetadata {

	public const SCHEMA_VERSION = 1;

	/**
	 * Metadata shape version.
	 *
	 * @var int
	 */
	private int $schema_version;

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private string $provider_id;

	/**
	 * Provider "as of" date, if known.
	 *
	 * @var string|null
	 */
	private ?string $provider_date;

	/**
	 * Provider dataset version, if any.
	 *
	 * @var string|null
	 */
	private ?string $provider_version;

	/**
	 * HTTP ETag from the last fetch, if present.
	 *
	 * @var string|null
	 */
	private ?string $etag;

	/**
	 * HTTP Last-Modified from the last fetch, if present.
	 *
	 * @var string|null
	 */
	private ?string $last_modified;

	/**
	 * Builds provider metadata for a fetch result.
	 *
	 * @param int         $schema_version   Metadata shape version.
	 * @param string      $provider_id      Provider identifier.
	 * @param string|null $provider_date    Provider "as of" date.
	 * @param string|null $provider_version Provider dataset version, if any.
	 * @param string|null $etag             HTTP ETag, if present.
	 * @param string|null $last_modified    HTTP Last-Modified, if present.
	 */
	public function __construct(
		int $schema_version,
		string $provider_id,
		?string $provider_date = null,
		?string $provider_version = null,
		?string $etag = null,
		?string $last_modified = null
	) {
		$this->schema_version   = max( 1, $schema_version );
		$this->provider_id      = $provider_id;
		$this->provider_date    = $provider_date;
		$this->provider_version = $provider_version;
		$this->etag             = $etag;
		$this->last_modified    = $last_modified;
	}

	/**
	 * Rehydrates metadata from persisted storage.
	 *
	 * @param array<string, mixed> $data Raw stored metadata.
	 */
	public static function from_array( array $data ): self {
		$version = isset( $data['schema_version'] ) && is_numeric( $data['schema_version'] )
			? (int) $data['schema_version']
			: self::SCHEMA_VERSION;

		return new self(
			$version,
			isset( $data['provider_id'] ) ? (string) $data['provider_id'] : '',
			isset( $data['provider_date'] ) ? self::nullable_string( $data['provider_date'] ) : null,
			isset( $data['provider_version'] ) ? self::nullable_string( $data['provider_version'] ) : null,
			isset( $data['etag'] ) ? self::nullable_string( $data['etag'] ) : null,
			isset( $data['last_modified'] ) ? self::nullable_string( $data['last_modified'] ) : null
		);
	}

	/**
	 * Serializes metadata for persistence.
	 *
	 * @return array<string, scalar|null>
	 */
	public function to_array(): array {
		return array(
			'schema_version'   => $this->schema_version,
			'provider_id'      => $this->provider_id,
			'provider_date'    => $this->provider_date,
			'provider_version' => $this->provider_version,
			'etag'             => $this->etag,
			'last_modified'    => $this->last_modified,
		);
	}

	/**
	 * The metadata shape version.
	 */
	public function schema_version(): int {
		return $this->schema_version;
	}

	/**
	 * The provider identifier.
	 */
	public function provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * The provider "as of" date.
	 */
	public function provider_date(): ?string {
		return $this->provider_date;
	}

	/**
	 * The provider dataset version.
	 */
	public function provider_version(): ?string {
		return $this->provider_version;
	}

	/**
	 * The HTTP ETag from the last fetch.
	 */
	public function etag(): ?string {
		return $this->etag;
	}

	/**
	 * The HTTP Last-Modified from the last fetch.
	 */
	public function last_modified(): ?string {
		return $this->last_modified;
	}

	/**
	 * Normalizes a nullable string field.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function nullable_string( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$string = trim( (string) $value );

		return '' === $string ? null : $string;
	}
}
