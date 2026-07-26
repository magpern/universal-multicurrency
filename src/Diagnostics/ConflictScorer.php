<?php
/**
 * Pure weight-sum scoring: evidence in, ranked findings out.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use InvalidArgumentException;

/**
 * A weight-sum, not a count of matched signatures, decides confidence: one
 * dispositive fact (a `plugin_path` match, weight 60) must outrank several
 * weak, independently plantable ones (three `hook` matches, weight 10
 * each). This also makes the function property-testable — adding a matched
 * signature can never lower a detector's score — and keeps every step
 * integer arithmetic, so no float, no division, and no rounding decision
 * can ever enter a confidence judgment.
 *
 * Stateless and side-effect free: the same `(detectors, evidence)` pair
 * always produces the same findings, in the same order.
 */
final class ConflictScorer {

	/**
	 * Scores every detector against the given evidence.
	 *
	 * @param array<int, Detector> $detectors Detectors to test against the evidence.
	 * @param array<string, bool>  $evidence  Evidence, keyed by {@see Signature::key()}.
	 * @param string               $minimum   Lowest confidence level to report.
	 *
	 * @return array<int, Finding> Ordered by score descending, then id ascending.
	 *
	 * @throws InvalidArgumentException If `$minimum` is unknown, or an element of `$detectors` is not a Detector.
	 */
	public function score( array $detectors, array $evidence, string $minimum = Confidence::LOW ): array {
		if ( ! Confidence::is_valid( $minimum ) ) {
			throw new InvalidArgumentException( "Unknown confidence level: '{$minimum}'." );
		}

		$findings = array();

		foreach ( $detectors as $detector ) {
			if ( ! $detector instanceof Detector ) {
				throw new InvalidArgumentException( 'Detectors must all be Detector instances.' );
			}

			$finding = $this->score_detector( $detector, $evidence );

			if ( Confidence::NONE === $finding->confidence() ) {
				continue;
			}

			if ( ! Confidence::at_least( $finding->confidence(), $minimum ) ) {
				continue;
			}

			$findings[] = $finding;
		}

		usort(
			$findings,
			static function ( Finding $a, Finding $b ): int {
				if ( $a->score() !== $b->score() ) {
					return $b->score() - $a->score();
				}

				return strcmp( $a->id(), $b->id() );
			}
		);

		return $findings;
	}

	/**
	 * Scores a single detector against the given evidence.
	 *
	 * @param Detector            $detector Detector to score.
	 * @param array<string, bool> $evidence Evidence, keyed by {@see Signature::key()}.
	 */
	private function score_detector( Detector $detector, array $evidence ): Finding {
		$matched = array();
		$sum     = 0;

		foreach ( $detector->signatures() as $signature ) {
			if ( true === ( $evidence[ $signature->key() ] ?? false ) ) {
				$matched[] = $signature;
				$sum      += $signature->weight();
			}
		}

		$score = min( 100, $sum );

		return new Finding( $detector->id(), $detector->label(), $score, Confidence::from_score( $score ), $matched );
	}
}
