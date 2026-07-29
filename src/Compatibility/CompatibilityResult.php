<?php
/**
 * One compatibility diagnostic result.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

use InvalidArgumentException;

/**
 * Immutable compatibility finding for admin rendering and reports.
 */
final class CompatibilityResult {

	/**
	 * Stable result identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Result category.
	 *
	 * @var string
	 */
	private string $category;

	/**
	 * Result severity.
	 *
	 * @var string
	 */
	private string $severity;

	/**
	 * Short title.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Concise summary.
	 *
	 * @var string
	 */
	private string $summary;

	/**
	 * Determinism classification.
	 *
	 * @var string
	 */
	private string $determinism;

	/**
	 * Structured evidence key/value pairs.
	 *
	 * @var array<string, string>
	 */
	private array $evidence;

	/**
	 * Optional recommended actions.
	 *
	 * @var array<int, CompatibilityAction>
	 */
	private array $actions;

	/**
	 * Optional detail lines.
	 *
	 * @var array<int, string>
	 */
	private array $details;

	/**
	 * Creates a result.
	 *
	 * @param string                          $id           Stable identifier.
	 * @param string                          $category     Category slug.
	 * @param string                          $severity     Severity slug.
	 * @param string                          $title        Short title.
	 * @param string                          $summary      Concise summary.
	 * @param string                          $determinism  Determinism slug.
	 * @param array<string, string>           $evidence     Structured evidence.
	 * @param array<int, CompatibilityAction> $actions     Recommended actions.
	 * @param array<int, string>              $details      Optional detail lines.
	 */
	public function __construct(
		string $id,
		string $category,
		string $severity,
		string $title,
		string $summary,
		string $determinism,
		array $evidence = array(),
		array $actions = array(),
		array $details = array()
	) {
		if ( '' === $id ) {
			throw new InvalidArgumentException( 'Compatibility result id cannot be empty.' );
		}

		if ( ! CompatibilityCategory::is_valid( $category ) ) {
			throw new InvalidArgumentException( "Unknown compatibility category: {$category}." );
		}

		if ( ! CompatibilitySeverity::is_valid( $severity ) ) {
			throw new InvalidArgumentException( "Unknown compatibility severity: {$severity}." );
		}

		if ( ! CompatibilityDeterminism::is_valid( $determinism ) ) {
			throw new InvalidArgumentException( "Unknown compatibility determinism: {$determinism}." );
		}

		foreach ( $actions as $action ) {
			if ( ! $action instanceof CompatibilityAction ) {
				throw new InvalidArgumentException( 'Actions must be CompatibilityAction instances.' );
			}
		}

		$this->id          = $id;
		$this->category    = $category;
		$this->severity    = $severity;
		$this->title       = $title;
		$this->summary     = $summary;
		$this->determinism = $determinism;
		$this->evidence    = $evidence;
		$this->actions     = $actions;
		$this->details     = array_values( $details );
	}

	/**
	 * Stable identifier.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Category slug.
	 */
	public function category(): string {
		return $this->category;
	}

	/**
	 * Severity slug.
	 */
	public function severity(): string {
		return $this->severity;
	}

	/**
	 * Short title.
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Concise summary.
	 */
	public function summary(): string {
		return $this->summary;
	}

	/**
	 * Determinism slug.
	 */
	public function determinism(): string {
		return $this->determinism;
	}

	/**
	 * Structured evidence.
	 *
	 * @return array<string, string>
	 */
	public function evidence(): array {
		return $this->evidence;
	}

	/**
	 * Recommended actions.
	 *
	 * @return array<int, CompatibilityAction>
	 */
	public function actions(): array {
		return $this->actions;
	}

	/**
	 * Optional detail lines.
	 *
	 * @return array<int, string>
	 */
	public function details(): array {
		return $this->details;
	}

	/**
	 * Whether evidence is present.
	 */
	public function has_evidence(): bool {
		return array() !== $this->evidence || array() !== $this->details;
	}

	/**
	 * Compares two results for deterministic ordering.
	 */
	public static function compare( self $left, self $right ): int {
		$left_rank  = CompatibilitySeverity::RANK[ $left->severity() ] ?? 0;
		$right_rank = CompatibilitySeverity::RANK[ $right->severity() ] ?? 0;

		if ( $left_rank !== $right_rank ) {
			return $right_rank <=> $left_rank;
		}

		$left_category  = CompatibilityCategory::sort_index( $left->category() );
		$right_category = CompatibilityCategory::sort_index( $right->category() );

		if ( $left_category !== $right_category ) {
			return $left_category <=> $right_category;
		}

		return strcmp( $left->id(), $right->id() );
	}

	/**
	 * Builds an unavailable result for a failed check.
	 *
	 * @param string $id       Check identifier.
	 * @param string $category Category slug.
	 * @param string $title    Short title.
	 * @param string $summary  Concise summary.
	 */
	public static function unavailable( string $id, string $category, string $title, string $summary ): self {
		return new self(
			$id,
			$category,
			CompatibilitySeverity::UNAVAILABLE,
			$title,
			$summary,
			CompatibilityDeterminism::DETERMINISTIC
		);
	}
}
