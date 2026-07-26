<?php
/**
 * Classifies a running version against a supported floor and a tested
 * ceiling.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Pure and never throws: a missing or malformed version string is a real
 * possibility at runtime (a host reporting an unexpected format, a probe
 * that failed), and the conservative response is to say so explicitly
 * rather than to guess. `UNPARSEABLE` exists for exactly that case — it is
 * never treated as `SUPPORTED`.
 */
final class VersionPolicy {

	public const BELOW_FLOOR  = 'below_floor';
	public const AT_FLOOR     = 'at_floor';
	public const SUPPORTED    = 'supported';
	public const ABOVE_TESTED = 'above_tested';
	public const UNPARSEABLE  = 'unparseable';

	/**
	 * Every possible classification outcome.
	 *
	 * @var array<int, string>
	 */
	public const ALL = array(
		self::BELOW_FLOOR,
		self::AT_FLOOR,
		self::SUPPORTED,
		self::ABOVE_TESTED,
		self::UNPARSEABLE,
	);

	/**
	 * Classifies `$running` against `$floor` (the minimum supported
	 * version) and `$tested` (the highest version this milestone verified).
	 * `$floor` and `$tested` are trusted to be well-formed (they come from
	 * this plugin's own declared policy, not the host); only `$running`
	 * is host-reported and may be malformed.
	 *
	 * @param string $running Version actually running, as reported by the host.
	 * @param string $floor   Minimum supported version.
	 * @param string $tested  Highest version this milestone verified.
	 */
	public function evaluate( string $running, string $floor, string $tested ): string {
		if ( ! self::is_parseable( $running ) || ! self::is_parseable( $floor ) || ! self::is_parseable( $tested ) ) {
			return self::UNPARSEABLE;
		}

		if ( version_compare( $running, $floor, '<' ) ) {
			return self::BELOW_FLOOR;
		}

		if ( version_compare( $running, $floor, '==' ) ) {
			return self::AT_FLOOR;
		}

		if ( version_compare( $running, $tested, '>' ) ) {
			return self::ABOVE_TESTED;
		}

		return self::SUPPORTED;
	}

	/**
	 * Whether `$version` is a bare `X`, `X.Y` or `X.Y.Z` numeric string.
	 *
	 * @param string $version Candidate version string.
	 */
	private static function is_parseable( string $version ): bool {
		return 1 === preg_match( '/^\d+(?:\.\d+){0,2}$/', trim( $version ) );
	}
}
