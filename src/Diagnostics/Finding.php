<?php
/**
 * The scored result of testing one detector's signatures against evidence.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use InvalidArgumentException;

/**
 * Immutable. Produced only by {@see ConflictScorer}; carries exactly what a
 * rendering surface (an admin notice, a Site Health entry) needs to explain
 * itself, and nothing that could be mistaken for a currency, a rate, or an
 * order fact — a `Finding` is advisory, never authoritative over anything
 * monetary.
 */
final class Finding {

	/**
	 * The detector id this finding scores.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * The detector's display label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * The score reached, 0..100.
	 *
	 * @var int
	 */
	private int $score;

	/**
	 * The confidence level assigned to the score.
	 *
	 * @var string
	 */
	private string $confidence;

	/**
	 * The signatures that matched.
	 *
	 * @var array<int, Signature>
	 */
	private array $matched;

	/**
	 * Constructs the finding.
	 *
	 * @param string                $id         Detector id this finding scores.
	 * @param string                $label      Detector label.
	 * @param int                   $score      Score reached, 0..100.
	 * @param string                $confidence Confidence level {@see Confidence::from_score()} assigned to `$score`.
	 * @param array<int, Signature> $matched    Signatures that matched, in the order they were tested.
	 *
	 * @throws InvalidArgumentException If any argument is invalid.
	 */
	public function __construct( string $id, string $label, int $score, string $confidence, array $matched ) {
		if ( '' === $id ) {
			throw new InvalidArgumentException( 'Finding id cannot be empty.' );
		}

		if ( '' === $label ) {
			throw new InvalidArgumentException( 'Finding label cannot be empty.' );
		}

		if ( $score < 0 || $score > 100 ) {
			throw new InvalidArgumentException( "Finding score {$score} is outside 0..100." );
		}

		if ( ! Confidence::is_valid( $confidence ) ) {
			throw new InvalidArgumentException( "Unknown confidence level: '{$confidence}'." );
		}

		foreach ( $matched as $signature ) {
			if ( ! $signature instanceof Signature ) {
				throw new InvalidArgumentException( 'Finding matched signatures must all be Signature instances.' );
			}
		}

		$this->id         = $id;
		$this->label      = $label;
		$this->score      = $score;
		$this->confidence = $confidence;
		$this->matched    = $matched;
	}

	/**
	 * The detector id this finding scores.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The detector's display label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * The score reached, 0..100.
	 */
	public function score(): int {
		return $this->score;
	}

	/**
	 * The confidence level assigned to {@see self::score()}.
	 */
	public function confidence(): string {
		return $this->confidence;
	}

	/**
	 * The signatures that matched.
	 *
	 * @return array<int, Signature>
	 */
	public function matched(): array {
		return $this->matched;
	}

	/**
	 * A plain-array projection for rendering surfaces and Site Health.
	 * Carries only the matched signatures' kind/needle/weight — never a
	 * value, never the full manifest, never anything the signature did not
	 * already expose.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'label'      => $this->label,
			'score'      => $this->score,
			'confidence' => $this->confidence,
			'matched'    => array_map(
				static function ( Signature $signature ): array {
					return array(
						'kind'   => $signature->kind(),
						'needle' => $signature->needle(),
						'weight' => $signature->weight(),
					);
				},
				$this->matched
			),
		);
	}
}
