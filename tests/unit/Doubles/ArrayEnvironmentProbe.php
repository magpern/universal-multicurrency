<?php
/**
 * Test double for {@see \UMC\Diagnostics\EnvironmentProbe}.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Doubles;

use UMC\Diagnostics\EnvironmentProbe;
use UMC\Diagnostics\Signature;

/**
 * Answers `evaluate()` from a constructor-supplied evidence map instead of
 * reading anything real, which is what keeps every scoring permutation in
 * the unit suite. Missing keys default to false, matching how a real probe
 * would report a signature it found no trace of.
 *
 * Does not match `*Test.php`, so PHPUnit's own directory-based discovery
 * never runs it as a test; it is loaded explicitly by
 * `tests/unit/bootstrap.php` instead (see that file for why).
 */
final class ArrayEnvironmentProbe implements EnvironmentProbe {

	/**
	 * The constructor-supplied evidence map.
	 *
	 * @var array<string, bool>
	 */
	private array $evidence;

	/**
	 * @param array<string, bool> $evidence Keyed by {@see Signature::key()}.
	 */
	public function __construct( array $evidence ) {
		$this->evidence = $evidence;
	}

	/**
	 * @param array<int, Signature> $signatures Signatures to evaluate.
	 *
	 * @return array<string, bool>
	 */
	public function evaluate( array $signatures ): array {
		$result = array();

		foreach ( $signatures as $signature ) {
			$result[ $signature->key() ] = $this->evidence[ $signature->key() ] ?? false;
		}

		return $result;
	}
}
