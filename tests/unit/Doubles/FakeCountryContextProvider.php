<?php
/**
 * Configurable country context provider double.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Doubles;

use UMC\Geo\CountryContext;
use UMC\Geo\CountryContextProviderInterface;

/**
 * Deterministic provider double for CountryContextResolver characterization tests.
 */
final class FakeCountryContextProvider implements CountryContextProviderInterface {

	/**
	 * Provider identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Whether this provider reports itself as available.
	 *
	 * @var bool
	 */
	private bool $available;

	/**
	 * Context to return from resolve(), or null.
	 *
	 * @var CountryContext|null
	 */
	private ?CountryContext $context;

	/**
	 * Number of times resolve() was called.
	 *
	 * @var int
	 */
	private int $resolve_calls = 0;

	/**
	 * Constructs the fake provider.
	 *
	 * @param string              $id        Provider identifier.
	 * @param bool                $available Whether is_available() returns true.
	 * @param CountryContext|null $context   Context returned by resolve().
	 */
	public function __construct( string $id, bool $available, ?CountryContext $context ) {
		$this->id        = $id;
		$this->available = $available;
		$this->context   = $context;
	}

	public function id(): string {
		return $this->id;
	}

	public function is_available(): bool {
		return $this->available;
	}

	public function resolve(): ?CountryContext {
		++$this->resolve_calls;

		return $this->context;
	}

	/**
	 * Number of times resolve() was invoked.
	 */
	public function resolve_call_count(): int {
		return $this->resolve_calls;
	}
}
