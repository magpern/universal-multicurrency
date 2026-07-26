<?php
/**
 * Seam between the pure scoring core and whatever actually reads the host
 * environment.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The only thing a probe is allowed to do is answer "is this signature
 * present?" for a list of signatures — never a value, never a count, never
 * anything else about the environment. This is what keeps every scoring
 * permutation testable without WordPress: a test supplies its own probe
 * (see `tests/unit/Doubles/ArrayEnvironmentProbe.php`), and only the real
 * WordPress-backed implementation ever touches a live registry.
 */
interface EnvironmentProbe {

	/**
	 * Answers whether each signature is present.
	 *
	 * @param array<int, Signature> $signatures Signatures to evaluate.
	 *
	 * @return array<string, bool> Keyed by {@see Signature::key()}.
	 */
	public function evaluate( array $signatures ): array;
}
