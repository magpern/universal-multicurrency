<?php
/**
 * Closed set of admissible evidence kinds.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Every kind here passes the three-test admissibility rule (non-execution,
 * nominality, inertness): each probe is a pure lookup in a structure PHP or
 * WordPress already owns, takes a name and returns a boolean, and can
 * influence nothing but rendered admin text. There is deliberately no
 * `option`, `cookie` or `session` kind — reading a foreign plugin's stored
 * value requires knowing its schema, which is the runtime coupling this
 * milestone exists to avoid. The set is closed: adding a kind here is an
 * architecture decision, not a data-file edit.
 */
final class SignatureKind {

	public const PLUGIN_PATH = 'plugin_path';
	public const CLASS_NAME  = 'class';
	public const FUNCTION    = 'function';
	public const CONSTANT    = 'constant';
	public const SHORTCODE   = 'shortcode';
	public const HOOK        = 'hook';

	/**
	 * Every admissible signature kind.
	 *
	 * @var array<int, string>
	 */
	public const ALL = array(
		self::PLUGIN_PATH,
		self::CLASS_NAME,
		self::FUNCTION,
		self::CONSTANT,
		self::SHORTCODE,
		self::HOOK,
	);

	/**
	 * Default evidential weight per kind, when a signature does not supply
	 * its own. `plugin_path` alone is dispositive (WordPress itself reports
	 * the plugin active); `hook` alone is the weakest (a compatibility
	 * snippet can plant one with the target plugin absent).
	 *
	 * @var array<string, int>
	 */
	public const DEFAULT_WEIGHTS = array(
		self::PLUGIN_PATH => 60,
		self::CLASS_NAME  => 40,
		self::FUNCTION    => 30,
		self::CONSTANT    => 25,
		self::SHORTCODE   => 15,
		self::HOOK        => 10,
	);

	/**
	 * The highest weight any single signature may carry.
	 */
	public const MAX_WEIGHT = 60;

	/**
	 * The lowest weight any single signature may carry.
	 */
	public const MIN_WEIGHT = 1;

	/**
	 * Whether `$kind` is one of the admissible signature kinds.
	 *
	 * @param string $kind Candidate signature kind.
	 */
	public static function is_valid( string $kind ): bool {
		return in_array( $kind, self::ALL, true );
	}

	/**
	 * The evidential weight a signature of this kind carries by default.
	 *
	 * @param string $kind Signature kind.
	 *
	 * @throws \InvalidArgumentException If `$kind` is not admissible.
	 */
	public static function default_weight( string $kind ): int {
		if ( ! self::is_valid( $kind ) ) {
			throw new \InvalidArgumentException( "Unknown signature kind: '{$kind}'." );
		}

		return self::DEFAULT_WEIGHTS[ $kind ];
	}

	/**
	 * The needle pattern a signature of this kind must match.
	 *
	 * `class` needles allow a leading namespace separator (already stripped
	 * by the sanitiser) and the full PHP identifier character set. `hook` is
	 * the widest, matching WordPress's own permissive hook-name convention.
	 *
	 * @param string $kind Signature kind.
	 *
	 * @throws \InvalidArgumentException If `$kind` is not admissible.
	 */
	public static function needle_pattern( string $kind ): string {
		switch ( $kind ) {
			case self::PLUGIN_PATH:
				return '/^[A-Za-z0-9._-]+\/[A-Za-z0-9._\/-]+\.php$/';
			case self::CLASS_NAME:
				return '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff\\\\]{0,190}$/';
			case self::FUNCTION:
			case self::CONSTANT:
				return '/^[A-Za-z_][A-Za-z0-9_]{0,190}$/';
			case self::HOOK:
				return '/^[A-Za-z0-9_\/.-]{2,190}$/';
			case self::SHORTCODE:
				return '/^[A-Za-z0-9_-]{2,60}$/';
			default:
				throw new \InvalidArgumentException( "Unknown signature kind: '{$kind}'." );
		}
	}
}
