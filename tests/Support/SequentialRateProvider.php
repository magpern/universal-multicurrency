<?php
/**
 * Rate provider double returning a different rate on each call.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use UMC\Rates\RateProvider;

/**
 * Used to prove structurally that a catalog operation resolves its rate
 * exactly once per invocation (ADR-0029 § Single execution-rate snapshot):
 * if the orchestration service ever called {@see get_rate()} more than
 * once, it would observe a different rate mid-operation.
 */
final class SequentialRateProvider implements RateProvider {

	/**
	 * Number of {@see get_rate()} invocations so far.
	 *
	 * @var int
	 */
	private int $calls = 0;

	/**
	 * @param array<int, string> $rates Sequence of rates returned on successive calls.
	 */
	public function __construct(
		private array $rates
	) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $base_code   Unused; this double is single-pair only.
	 * @param string $target_code Unused; this double is single-pair only.
	 */
	public function get_rate( string $base_code, string $target_code ): ?string {
		unset( $base_code, $target_code );

		$index = $this->calls;
		++$this->calls;

		if ( isset( $this->rates[ $index ] ) ) {
			return $this->rates[ $index ];
		}

		if ( array() === $this->rates ) {
			return null;
		}

		return end( $this->rates );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $base_code   Unused; this double is single-pair only.
	 * @param string $target_code Unused; this double is single-pair only.
	 */
	public function has_rate( string $base_code, string $target_code ): bool {
		unset( $base_code, $target_code );

		return array() !== $this->rates;
	}

	/**
	 * Number of times {@see get_rate()} has been called.
	 */
	public function call_count(): int {
		return $this->calls;
	}
}
