<?php
/**
 * Country context provider contract.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Resolves visitor country context for geo routing.
 */
interface CountryContextProviderInterface {

	/**
	 * Provider identifier.
	 */
	public function id(): string;

	/**
	 * Whether this provider can run in the current environment.
	 */
	public function is_available(): bool;

	/**
	 * Resolves country context, or null when this provider cannot answer.
	 */
	public function resolve(): ?CountryContext;
}
