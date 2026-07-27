<?php
/**
 * A single piece of passive evidence a detector may match on.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use InvalidArgumentException;

/**
 * Immutable. A signature names a fact to look up in a structure PHP or
 * WordPress already owns (see {@see SignatureKind}) — it never carries a
 * value, a callable, or anything that could execute. Validation happens
 * once, at construction, so every consumer downstream can trust the shape
 * without re-checking it.
 */
final class Signature {

	/**
	 * The signature's kind.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * The identifier this signature looks up.
	 *
	 * @var string
	 */
	private string $needle;

	/**
	 * The signature's evidential weight.
	 *
	 * @var int
	 */
	private int $weight;

	/**
	 * Constructs the signature.
	 *
	 * @param string $kind   Signature kind; must be one of {@see SignatureKind::ALL}.
	 * @param string $needle Identifier to look up; must match {@see SignatureKind::needle_pattern()}.
	 * @param int    $weight Evidential weight, clamped to {@see SignatureKind::MIN_WEIGHT}..{@see SignatureKind::MAX_WEIGHT}.
	 *
	 * @throws InvalidArgumentException If any argument is invalid.
	 */
	public function __construct( string $kind, string $needle, int $weight ) {
		if ( ! SignatureKind::is_valid( $kind ) ) {
			throw new InvalidArgumentException( "Unknown signature kind: '{$kind}'." );
		}

		if ( '' === $needle ) {
			throw new InvalidArgumentException( 'Signature needle cannot be empty.' );
		}

		if ( 1 !== preg_match( SignatureKind::needle_pattern( $kind ), $needle ) ) {
			throw new InvalidArgumentException( "Needle '{$needle}' is not a valid {$kind} identifier." );
		}

		if ( $weight < SignatureKind::MIN_WEIGHT || $weight > SignatureKind::MAX_WEIGHT ) {
			throw new InvalidArgumentException(
				"Weight {$weight} is outside the allowed range " . SignatureKind::MIN_WEIGHT . '..' . SignatureKind::MAX_WEIGHT . '.'
			);
		}

		$this->kind   = $kind;
		$this->needle = $needle;
		$this->weight = $weight;
	}

	/**
	 * The signature's kind.
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * The identifier this signature looks up.
	 */
	public function needle(): string {
		return $this->needle;
	}

	/**
	 * The signature's evidential weight.
	 */
	public function weight(): int {
		return $this->weight;
	}

	/**
	 * Evidence-map key: `"kind:needle"`, unique across every admissible
	 * signature this plugin can express.
	 */
	public function key(): string {
		return $this->kind . ':' . $this->needle;
	}
}
