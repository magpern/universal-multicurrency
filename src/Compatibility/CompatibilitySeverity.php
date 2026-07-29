<?php
/**
 * Severity levels for compatibility diagnostics.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Ordered advisory severity for one compatibility result.
 */
final class CompatibilitySeverity {

	public const PASS = 'pass';

	public const INFO = 'info';

	public const WARNING = 'warning';

	public const CONFLICT = 'conflict';

	public const UNAVAILABLE = 'unavailable';

	/**
	 * Rank used for sorting (higher = more severe).
	 *
	 * @var array<string, int>
	 */
	public const RANK = array(
		self::CONFLICT    => 50,
		self::WARNING     => 40,
		self::UNAVAILABLE => 30,
		self::INFO        => 20,
		self::PASS        => 10,
	);

	/**
	 * Whether the given severity is valid.
	 *
	 * @param string $severity Severity slug.
	 */
	public static function is_valid( string $severity ): bool {
		return isset( self::RANK[ $severity ] );
	}
}
