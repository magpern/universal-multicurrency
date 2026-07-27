<?php
/**
 * Test double that throws on save().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Doubles;

use UMC\Rates\RateUpdateState;

/**
 * Simulates an operational-state persistence failure after settings were written.
 */
final class ThrowingRateUpdateState extends RateUpdateState {

	/**
	 * Persists sanitized operational state.
	 *
	 * @param array<string, mixed> $state State to persist.
	 * @throws \RuntimeException When persistence fails in test doubles.
	 */
	public function save( array $state ): void {
		unset( $state );
		throw new \RuntimeException( 'Simulated state write failure.' );
	}
}
