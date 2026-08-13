<?php
/**
 * Extension compatibility evidence tiers (E0–E3).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

use InvalidArgumentException;

/**
 * Evidence strength governing the maximum compatibility status claim.
 */
final class ExtensionEvidenceTier {

	public const E0 = 'e0';

	public const E1 = 'e1';

	public const E2 = 'e2';

	public const E3 = 'e3';

	/**
	 * All valid tiers in ascending strength.
	 */
	public const ALL = array( self::E0, self::E1, self::E2, self::E3 );

	/**
	 * Whether a tier slug is valid.
	 *
	 * @param string $tier Tier slug.
	 */
	public static function is_valid( string $tier ): bool {
		return in_array( $tier, self::ALL, true );
	}

	/**
	 * Returns the maximum status allowed for a tier.
	 *
	 * @param string $tier Tier slug.
	 */
	public static function max_status( string $tier ): string {
		self::assert_valid( $tier );

		switch ( $tier ) {
			case self::E3:
				return ExtensionCompatibilityStatus::INTEGRATED;
			case self::E1:
			case self::E2:
				return ExtensionCompatibilityStatus::CHARACTERIZED;
			default:
				return ExtensionCompatibilityStatus::NOT_EVALUATED;
		}
	}

	/**
	 * Merchant-visible sub-label for characterized/integrated tiers.
	 *
	 * @param string $tier Tier slug.
	 */
	public static function merchant_sub_label( string $tier ): string {
		self::assert_valid( $tier );

		switch ( $tier ) {
			case self::E1:
				return ExtensionCharacterizedSubLabel::CONTRACT_TESTS;
			case self::E2:
				return ExtensionCharacterizedSubLabel::SIMULATED_HOOKS;
			case self::E3:
				return ExtensionCharacterizedSubLabel::REAL_EXTENSION;
			default:
				return '';
		}
	}

	/**
	 * Primary merchant status line combining status + sub-label.
	 *
	 * @param string $status Compatibility status.
	 * @param string $tier   Evidence tier.
	 */
	public static function merchant_status_line( string $status, string $tier ): string {
		if ( ExtensionCompatibilityStatus::INTEGRATED === $status ) {
			return ExtensionCharacterizedSubLabel::REAL_EXTENSION;
		}

		if ( ExtensionCompatibilityStatus::CHARACTERIZED === $status ) {
			$sub = self::merchant_sub_label( $tier );
			return '' !== $sub ? $sub : ExtensionCompatibilityStatus::label( $status );
		}

		return ExtensionCompatibilityStatus::label( $status );
	}

	/**
	 * Asserts a tier slug is valid.
	 *
	 * @param string $tier Tier slug.
	 *
	 * @throws InvalidArgumentException When the tier is unknown.
	 */
	private static function assert_valid( string $tier ): void {
		if ( ! self::is_valid( $tier ) ) {
			throw new InvalidArgumentException( "Unknown extension evidence tier: {$tier}." );
		}
	}
}
