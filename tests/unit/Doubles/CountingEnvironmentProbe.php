<?php
/**
 * Test double that counts how many times evaluate() runs.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Doubles;

use UMC\Diagnostics\EnvironmentProbe;
use UMC\Diagnostics\Signature;

/**
 * Wraps another probe and records how many times {@see evaluate()} ran.
 */
final class CountingEnvironmentProbe implements EnvironmentProbe {

	/**
	 * @var EnvironmentProbe Inner probe.
	 */
	private EnvironmentProbe $inner;

	/**
	 * @var int Number of evaluate() calls.
	 */
	private int $calls = 0;

	public function __construct( EnvironmentProbe $inner ) {
		$this->inner = $inner;
	}

	public function evaluate( array $signatures ): array {
		++$this->calls;

		return $this->inner->evaluate( $signatures );
	}

	public function calls(): int {
		return $this->calls;
	}
}
