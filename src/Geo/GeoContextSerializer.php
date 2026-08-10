<?php
/**
 * GeoContext document serialization helpers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Encodes and decodes GeoContext documents for admin sandbox round-trips.
 */
final class GeoContextSerializer {

	/**
	 * Encodes a document to JSON for ephemeral admin transport.
	 *
	 * @param array<string, mixed> $document GeoContext document.
	 */
	public static function encode( array $document ): string {
		return (string) wp_json_encode( $document );
	}

	/**
	 * Decodes JSON into a GeoContext instance.
	 *
	 * Documents from a prior schema version are discarded rather than
	 * upgraded — this is an ephemeral per-user cache (last sandbox result),
	 * not persisted merchant configuration, so silently dropping a stale
	 * entry is safe and avoids reviving fields a schema bump removed.
	 *
	 * @param string $json Encoded document.
	 */
	public static function decode( string $json ): ?GeoContext {
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ( $data['schema_version'] ?? null ) !== GeoContext::SCHEMA_VERSION ) {
			return null;
		}

		return GeoContext::from_array( $data );
	}

	/**
	 * Builds a GeoContext from sandbox POST fields.
	 *
	 * @param array<string, mixed> $post Raw POST subset.
	 * @param string               $sample_currency Sample currency for boolean flags.
	 */
	public static function from_sandbox_post( array $post, string $sample_currency ): GeoContext {
		$country = isset( $post['country'] ) ? strtoupper( sanitize_text_field( (string) $post['country'] ) ) : '';

		$shopper = array(
			'explicit_currency' => ! empty( $post['explicit_currency'] ) ? $sample_currency : null,
			'session_currency'  => ! empty( $post['session_currency'] ) ? $sample_currency : null,
			'cookie_currency'   => ! empty( $post['cookie_currency'] ) ? $sample_currency : null,
			'manual_selection'  => ! empty( $post['manual_selection'] ),
			'checkout_locked'   => ! empty( $post['checkout_locked'] ),
		);

		return GeoContext::for_sandbox(
			array(
				'geo'     => array(
					'country' => '' !== $country ? $country : null,
				),
				'shopper' => $shopper,
			)
		);
	}
}
