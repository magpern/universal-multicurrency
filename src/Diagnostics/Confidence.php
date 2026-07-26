<?php
/**
 * Confidence levels a detector's evidence can reach, and the pure scoring
 * thresholds that produce them.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Frozen by design: changing a threshold changes what every existing
 * detector reports, so a change here is an architecture decision (see
 * ADR-0007), never a per-detector tuning knob. `NONE` exists only to give a
 * score below 10 a name; `ConflictScorer` never reports it as a finding.
 */
final class Confidence {

	public const NONE   = 'none';
	public const LOW    = 'low';
	public const MEDIUM = 'medium';
	public const HIGH   = 'high';

	public const THRESHOLD_HIGH   = 60;
	public const THRESHOLD_MEDIUM = 30;
	public const THRESHOLD_LOW    = 10;

	/**
	 * Rank order, lowest to highest, for comparing two confidence levels.
	 *
	 * @var array<string, int>
	 */
	public const RANK = array(
		self::NONE   => 0,
		self::LOW    => 1,
		self::MEDIUM => 2,
		self::HIGH   => 3,
	);

	/**
	 * Whether `$level` is one of the defined confidence levels.
	 *
	 * @param string $level Candidate confidence level.
	 */
	public static function is_valid( string $level ): bool {
		return array_key_exists( $level, self::RANK );
	}

	/**
	 * Maps an integer score (0..100) to the confidence level it reaches.
	 *
	 * `>= 60` is HIGH, `>= 30` is MEDIUM, `>= 10` is LOW; anything lower is
	 * NONE. Boundaries are inclusive on the lower edge of each band.
	 *
	 * @param int $score Score to classify.
	 *
	 * @throws \InvalidArgumentException If `$score` is negative.
	 */
	public static function from_score( int $score ): string {
		if ( $score < 0 ) {
			throw new \InvalidArgumentException( "Score cannot be negative: {$score}." );
		}

		if ( $score >= self::THRESHOLD_HIGH ) {
			return self::HIGH;
		}

		if ( $score >= self::THRESHOLD_MEDIUM ) {
			return self::MEDIUM;
		}

		if ( $score >= self::THRESHOLD_LOW ) {
			return self::LOW;
		}

		return self::NONE;
	}

	/**
	 * Whether `$level` is at or above `$minimum` on the fixed rank order.
	 *
	 * @param string $level   Confidence level under test.
	 * @param string $minimum Lowest confidence level `$level` must reach.
	 *
	 * @throws \InvalidArgumentException If either level is unknown.
	 */
	public static function at_least( string $level, string $minimum ): bool {
		if ( ! self::is_valid( $level ) ) {
			throw new \InvalidArgumentException( "Unknown confidence level: '{$level}'." );
		}

		if ( ! self::is_valid( $minimum ) ) {
			throw new \InvalidArgumentException( "Unknown confidence level: '{$minimum}'." );
		}

		return self::RANK[ $level ] >= self::RANK[ $minimum ];
	}
}
