<?php
/**
 * Versioned geographic context document for Currency Simulation only —
 * never a runtime resolution format (see ADR-0018).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Extensible GeoContext document (schema v2).
 */
final class GeoContext {

	/**
	 * Bumped 1 → 2 in M14: the reserved `network` and `providers` subtrees
	 * (scaffolding for a second-geo-platform direction ADR-0018 supersedes)
	 * were removed. See {@see GeoContextSerializer::decode()} for the
	 * corresponding stale-document discard on read.
	 */
	public const SCHEMA_VERSION = 2;

	/**
	 * Normalized GeoContext document payload.
	 *
	 * @var array<string, mixed>
	 */
	private array $document;

	/**
	 * Constructs a GeoContext from a normalized document.
	 *
	 * @param array<string, mixed> $document Normalized document.
	 */
	private function __construct( array $document ) {
		$this->document = $document;
	}

	/**
	 * Builds a sandbox GeoContext from a partial input array.
	 *
	 * @param array<string, mixed> $input Partial document fields.
	 */
	public static function for_sandbox( array $input ): self {
		$defaults                 = self::default_document( true );
		$merged                   = self::merge_recursive( $defaults, $input );
		$merged['schema_version'] = self::SCHEMA_VERSION;
		$merged['simulation']     = true;

		return new self( self::normalize( $merged ) );
	}

	/**
	 * Wraps a legacy CountryContext as a GeoContext document.
	 *
	 * @param CountryContext $context Resolved country context.
	 */
	public static function from_country_context( CountryContext $context ): self {
		$document = self::default_document( false );

		if ( $context->is_known() ) {
			$document['geo']['country'] = strtoupper( (string) $context->country_code() );
		}

		$document['resolution']['source']     = $context->source();
		$document['resolution']['confidence'] = $context->confidence();
		$document['schema_version']           = self::SCHEMA_VERSION;

		return new self( $document );
	}

	/**
	 * Parses a stored or posted document array.
	 *
	 * @param array<string, mixed> $data Raw document.
	 */
	public static function from_array( array $data ): self {
		$simulation               = ! empty( $data['simulation'] );
		$merged                   = self::merge_recursive( self::default_document( $simulation ), $data );
		$merged['schema_version'] = self::SCHEMA_VERSION;

		return new self( self::normalize( $merged ) );
	}

	/**
	 * Returns the document as an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->document;
	}

	/**
	 * Primary ISO alpha-2 country code when present.
	 */
	public function country_code(): ?string {
		$country = $this->document['geo']['country'] ?? null;

		if ( ! is_string( $country ) || '' === $country ) {
			return null;
		}

		$code = strtoupper( trim( $country ) );

		return 1 === preg_match( '/^[A-Z]{2}$/', $code ) ? $code : null;
	}

	/**
	 * Whether this document represents a sandbox run.
	 */
	public function is_simulation(): bool {
		return ! empty( $this->document['simulation'] );
	}

	/**
	 * Returns the shopper precedence subtree.
	 *
	 * @return array<string, mixed>
	 */
	public function shopper(): array {
		$shopper = $this->document['shopper'] ?? array();

		return is_array( $shopper ) ? $shopper : array();
	}

	/**
	 * Returns the default GeoContext document shape.
	 *
	 * @param bool $simulation Whether this is a sandbox document.
	 * @return array<string, mixed>
	 */
	public static function default_document( bool $simulation ): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'simulation'     => $simulation,
			'shopper'        => array(
				'explicit_currency' => null,
				'session_currency'  => null,
				'cookie_currency'   => null,
				'manual_selection'  => false,
				'checkout_locked'   => false,
			),
			'geo'            => array(
				'country'      => null,
				'region_hints' => array(),
				'city'         => null,
				'postal_code'  => null,
				'timezone'     => null,
				'coordinates'  => null,
			),
			'resolution'     => array(
				'source'     => null,
				'confidence' => null,
				'evidence'   => array(),
				'trace'      => array(),
				'timing_ms'  => null,
			),
			'routing'        => array(
				'evaluation'     => null,
				'final_currency' => null,
			),
		);
	}

	/**
	 * Normalizes country and currency fields in a document.
	 *
	 * @param array<string, mixed> $document Document to normalize.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $document ): array {
		$country = $document['geo']['country'] ?? null;

		if ( is_string( $country ) && '' !== $country ) {
			$document['geo']['country'] = strtoupper( trim( $country ) );
		}

		foreach ( array( 'explicit_currency', 'session_currency', 'cookie_currency' ) as $key ) {
			$value = $document['shopper'][ $key ] ?? null;

			if ( is_string( $value ) && '' !== $value ) {
				$document['shopper'][ $key ] = strtoupper( trim( $value ) );
			}
		}

		return $document;
	}

	/**
	 * Recursively merges patch values into a base document.
	 *
	 * @param array<string, mixed> $base  Base array.
	 * @param array<string, mixed> $patch Patch array.
	 * @return array<string, mixed>
	 */
	private static function merge_recursive( array $base, array $patch ): array {
		foreach ( $patch as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::merge_recursive( $base[ $key ], $value );
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}
}
