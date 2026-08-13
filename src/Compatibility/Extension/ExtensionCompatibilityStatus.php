<?php
/**
 * Extension compatibility status values.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Status describing UMC compatibility with a third-party extension.
 */
final class ExtensionCompatibilityStatus {

	public const NATIVE = 'native';

	public const INTEGRATED = 'integrated';

	public const CHARACTERIZED = 'characterized';

	public const KNOWN_LIMITATION = 'known_limitation';

	public const INCOMPATIBLE = 'incompatible';

	public const NOT_EVALUATED = 'not_evaluated';

	/**
	 * All valid statuses.
	 */
	public const ALL = array(
		self::NATIVE,
		self::INTEGRATED,
		self::CHARACTERIZED,
		self::KNOWN_LIMITATION,
		self::INCOMPATIBLE,
		self::NOT_EVALUATED,
	);

	/**
	 * Whether a status slug is valid.
	 *
	 * @param string $status Status slug.
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, self::ALL, true );
	}

	/**
	 * Human-readable label for admin display.
	 *
	 * @param string $status Status slug.
	 */
	public static function label( string $status ): string {
		switch ( $status ) {
			case self::NATIVE:
				return 'Native';
			case self::INTEGRATED:
				return 'Integrated';
			case self::CHARACTERIZED:
				return 'Characterized';
			case self::KNOWN_LIMITATION:
				return 'Known limitation';
			case self::INCOMPATIBLE:
				return 'Incompatible';
			default:
				return 'Not evaluated';
		}
	}

	/**
	 * Whether the status requires E3 evidence.
	 *
	 * @param string $status Status slug.
	 */
	public static function requires_e3( string $status ): bool {
		return self::INTEGRATED === $status;
	}
}
