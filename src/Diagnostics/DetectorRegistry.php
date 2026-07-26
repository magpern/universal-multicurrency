<?php
/**
 * Sanitises and hydrates the detector manifest, extensible via one
 * data-only filter.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * `sanitize()` is pure, WordPress-free and never throws — the same
 * silent-drop contract as {@see \UMC\Settings::sanitize()}: malformed input
 * yields a smaller, valid output, never an exception. It is the single
 * code path both the built-in manifest and any third-party filter output
 * pass through, so a filter cannot express anything the built-ins
 * couldn't.
 *
 * `detectors()` is the one instance method that touches WordPress (it
 * applies {@see self::FILTER} before sanitising) and is intentionally not
 * unit-tested here — the same split `\UMC\Settings` already draws between
 * its pure static sanitiser and its option-reading instance methods.
 */
final class DetectorRegistry {

	/**
	 * Filter through which a site or another plugin may add, remove or
	 * adjust detector definitions. Receives and must return the same
	 * data-only shape as {@see DetectorManifest::manifest()} — arrays of
	 * scalars, sanitised identically to the built-ins before use.
	 *
	 * @since 0.6.0
	 */
	public const FILTER = 'umc_conflict_detectors';

	/**
	 * Ceiling on the number of detectors {@see self::sanitize()} keeps.
	 */
	public const MAX_DETECTORS = 32;

	/**
	 * Ceiling on the number of signatures per detector {@see self::sanitize()} keeps.
	 */
	public const MAX_SIGNATURES = 12;

	private const ID_PATTERN = '/^[a-z0-9_-]{2,32}$/';

	/**
	 * Detector ids no manifest row may use — they name the plugin itself.
	 *
	 * @var array<int, string>
	 */
	private const RESERVED_IDS = array( 'umc', 'universal-multicurrency' );

	private const LABEL_MAX_LENGTH = 120;

	/**
	 * Memoized hydrated detectors.
	 *
	 * @var array<int, Detector>|null
	 */
	private ?array $detectors = null;

	/**
	 * Memoized, ordered by id ascending. Applies {@see self::FILTER} to the
	 * built-in manifest, sanitises the combined result once, and hydrates
	 * it into immutable value objects.
	 *
	 * @return array<int, Detector>
	 */
	public function detectors(): array {
		if ( null === $this->detectors ) {
			/**
			 * Filters the raw (unsanitised) detector manifest before it is
			 * sanitised and hydrated. Add, remove or adjust detector
			 * definitions — the same data-only shape as
			 * {@see DetectorManifest::manifest()}. Never a callable, an
			 * object, or anything that could execute; everything returned
			 * here passes through {@see DetectorRegistry::sanitize()}
			 * identically to the built-ins.
			 *
			 * @since 0.6.0
			 *
			 * @param array<string, array{label: string, signatures: array<int, array{kind: string, needle: string, weight?: int}>}> $manifest Raw detector manifest.
			 */
			$raw       = apply_filters( self::FILTER, DetectorManifest::manifest() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$sanitized = self::sanitize( $raw );

			$hydrated = array();

			foreach ( $sanitized as $id => $row ) {
				$hydrated[] = self::hydrate( $id, $row );
			}

			$this->detectors = $hydrated;
		}

		return $this->detectors;
	}

	/**
	 * Sanitises a raw detector manifest.
	 *
	 * Pure, total, never throws. Malformed input produces a smaller valid
	 * output rather than an exception — the same contract as
	 * `\UMC\Settings::sanitize()`.
	 *
	 * @param mixed $raw Raw detector manifest, of any shape.
	 *
	 * @return array<string, array{label: string, signatures: array<int, array{kind: string, needle: string, weight: int}>}>
	 */
	public static function sanitize( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$detectors = array();

		foreach ( $raw as $id => $row ) {
			$sanitized = self::sanitize_detector( $id, $row );

			if ( null !== $sanitized ) {
				$detectors[ (string) $id ] = $sanitized;
			}
		}

		ksort( $detectors );

		if ( count( $detectors ) > self::MAX_DETECTORS ) {
			$detectors = array_slice( $detectors, 0, self::MAX_DETECTORS, true );
		}

		return $detectors;
	}

	/**
	 * Sanitises one raw detector row, or drops it entirely.
	 *
	 * @param mixed $id  Candidate detector id (the manifest's array key).
	 * @param mixed $row Candidate detector row.
	 *
	 * @return array{label: string, signatures: array<int, array{kind: string, needle: string, weight: int}>}|null
	 */
	private static function sanitize_detector( mixed $id, mixed $row ): ?array {
		if ( ! is_string( $id ) || 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			return null;
		}

		if ( in_array( $id, self::RESERVED_IDS, true ) ) {
			return null;
		}

		if ( ! is_array( $row ) ) {
			return null;
		}

		$raw_signatures = $row['signatures'] ?? null;

		if ( ! is_array( $raw_signatures ) ) {
			return null;
		}

		$signatures = array();

		foreach ( $raw_signatures as $raw_signature ) {
			$signature = self::sanitize_signature( $raw_signature );

			if ( null !== $signature ) {
				$signatures[] = $signature;
			}
		}

		if ( array() === $signatures ) {
			return null;
		}

		usort(
			$signatures,
			static function ( array $a, array $b ): int {
				if ( $a['weight'] !== $b['weight'] ) {
					return $b['weight'] - $a['weight'];
				}

				if ( $a['kind'] !== $b['kind'] ) {
					return strcmp( $a['kind'], $b['kind'] );
				}

				return strcmp( $a['needle'], $b['needle'] );
			}
		);

		if ( count( $signatures ) > self::MAX_SIGNATURES ) {
			$signatures = array_slice( $signatures, 0, self::MAX_SIGNATURES );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- this method is WordPress-free by design (see the class docblock); wp_strip_all_tags() is unavailable here.
		$label = is_string( $row['label'] ?? null ) ? trim( strip_tags( (string) $row['label'] ) ) : '';

		if ( '' === $label ) {
			$label = $id;
		}

		if ( strlen( $label ) > self::LABEL_MAX_LENGTH ) {
			$label = substr( $label, 0, self::LABEL_MAX_LENGTH );
		}

		return array(
			'label'      => $label,
			'signatures' => $signatures,
		);
	}

	/**
	 * Sanitises one raw signature row, or drops it entirely.
	 *
	 * @param mixed $raw Candidate signature row.
	 *
	 * @return array{kind: string, needle: string, weight: int}|null
	 */
	private static function sanitize_signature( mixed $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$kind = $raw['kind'] ?? null;

		if ( ! is_string( $kind ) || ! SignatureKind::is_valid( $kind ) ) {
			return null;
		}

		$needle = $raw['needle'] ?? null;

		if ( ! is_string( $needle ) ) {
			return null;
		}

		$needle = trim( $needle );

		if ( SignatureKind::CLASS_NAME === $kind ) {
			$needle = ltrim( $needle, '\\' );
		}

		if ( 1 !== preg_match( SignatureKind::needle_pattern( $kind ), $needle ) ) {
			return null;
		}

		if ( false !== stripos( $needle, 'universal-multicurrency' ) ) {
			return null;
		}

		if ( 1 === preg_match( '/^UMC\\\\/', $needle ) ) {
			return null;
		}

		// This plugin's own function/hook/constant prefix. The slug check
		// above catches a plugin_path or a value quoting the slug; a
		// function, hook or constant needle self-targets by this prefix
		// instead (every real umc_ symbol, e.g. umc_is_request_convertible).
		if ( 1 === preg_match( '/^umc_/i', $needle ) ) {
			return null;
		}

		$weight = $raw['weight'] ?? null;

		if ( null === $weight ) {
			$weight = SignatureKind::default_weight( $kind );
		} elseif ( is_int( $weight ) || ( is_string( $weight ) && 1 === preg_match( '/^-?\d+$/', $weight ) ) ) {
			$weight = max( SignatureKind::MIN_WEIGHT, min( SignatureKind::MAX_WEIGHT, (int) $weight ) );
		} else {
			$weight = SignatureKind::default_weight( $kind );
		}

		return array(
			'kind'   => $kind,
			'needle' => $needle,
			'weight' => $weight,
		);
	}

	/**
	 * Hydrates one sanitised detector row into an immutable value object.
	 *
	 * @param string                                                                                         $id  Detector id.
	 * @param array{label: string, signatures: array<int, array{kind: string, needle: string, weight: int}>} $row Sanitised detector row.
	 */
	private static function hydrate( string $id, array $row ): Detector {
		$signatures = array();

		foreach ( $row['signatures'] as $signature ) {
			$signatures[] = new Signature( $signature['kind'], $signature['needle'], $signature['weight'] );
		}

		return new Detector( $id, $row['label'], $signatures );
	}
}
