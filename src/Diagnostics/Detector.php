<?php
/**
 * A named thing this plugin can produce evidence for.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use InvalidArgumentException;

/**
 * Immutable. Deliberately generic: nothing in this class, or in anything
 * that consumes it besides {@see DetectorManifest}, may know what a
 * detector actually names. The id is an opaque identifier and the label an
 * opaque display string; both are validated for shape only.
 */
final class Detector {

	public const MAX_SIGNATURES = 12;

	private const ID_PATTERN = '/^[a-z0-9_-]{2,32}$/';

	private const LABEL_MAX_LENGTH = 120;

	/**
	 * The detector's opaque identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * The detector's opaque display label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * The detector's signatures.
	 *
	 * @var array<int, Signature>
	 */
	private array $signatures;

	/**
	 * Constructs the detector.
	 *
	 * @param string                $id         Opaque identifier; must match `/^[a-z0-9_-]{2,32}$/`.
	 * @param string                $label      Opaque display label; 1..120 characters.
	 * @param array<int, Signature> $signatures At least one, at most {@see self::MAX_SIGNATURES}, no duplicates.
	 *
	 * @throws InvalidArgumentException If any argument is invalid.
	 */
	public function __construct( string $id, string $label, array $signatures ) {
		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			throw new InvalidArgumentException( "Detector id '{$id}' does not match " . self::ID_PATTERN . '.' );
		}

		if ( '' === trim( $label ) ) {
			throw new InvalidArgumentException( 'Detector label cannot be empty.' );
		}

		if ( strlen( $label ) > self::LABEL_MAX_LENGTH ) {
			throw new InvalidArgumentException( 'Detector label exceeds ' . self::LABEL_MAX_LENGTH . ' characters.' );
		}

		if ( array() === $signatures ) {
			throw new InvalidArgumentException( "Detector '{$id}' must have at least one signature." );
		}

		if ( count( $signatures ) > self::MAX_SIGNATURES ) {
			throw new InvalidArgumentException( "Detector '{$id}' exceeds " . self::MAX_SIGNATURES . ' signatures.' );
		}

		$seen = array();

		foreach ( $signatures as $signature ) {
			if ( ! $signature instanceof Signature ) {
				throw new InvalidArgumentException( "Detector '{$id}' signatures must all be Signature instances." );
			}

			if ( isset( $seen[ $signature->key() ] ) ) {
				throw new InvalidArgumentException( "Detector '{$id}' has a duplicate signature: '{$signature->key()}'." );
			}

			$seen[ $signature->key() ] = true;
		}

		$this->id         = $id;
		$this->label      = $label;
		$this->signatures = $signatures;
	}

	/**
	 * The detector's opaque identifier.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The detector's opaque display label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * The detector's signatures.
	 *
	 * @return array<int, Signature>
	 */
	public function signatures(): array {
		return $this->signatures;
	}

	/**
	 * The score this detector would reach if every signature matched.
	 * Clamped to 100 — the scale is bounded regardless of how many
	 * signatures a detector carries.
	 */
	public function max_score(): int {
		$sum = 0;

		foreach ( $this->signatures as $signature ) {
			$sum += $signature->weight();
		}

		return min( 100, $sum );
	}
}
