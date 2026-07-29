<?php
/**
 * Determinism classification for compatibility checks.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Describes how reliably a check can be evaluated.
 */
final class CompatibilityDeterminism {

	public const DETERMINISTIC = 'deterministic';

	public const HEURISTIC = 'heuristic';

	public const FACT = 'fact';

	/**
	 * Whether the determinism value is valid.
	 *
	 * @param string $determinism Determinism slug.
	 */
	public static function is_valid( string $determinism ): bool {
		return in_array(
			$determinism,
			array(
				self::DETERMINISTIC,
				self::HEURISTIC,
				self::FACT,
			),
			true
		);
	}
}
