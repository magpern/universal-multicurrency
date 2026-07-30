<?php
/**
 * Resolved visitor country context.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Immutable country resolution result from a provider chain.
 */
final class CountryContext {

	/**
	 * Constructs resolved country context.
	 *
	 * @param string|null $country_code ISO alpha-2 or null.
	 * @param string      $source       Provider source id.
	 * @param float       $confidence   Confidence 0..1.
	 */
	public function __construct(
		private ?string $country_code,
		private string $source,
		private float $confidence
	) {
	}

	/**
	 * ISO alpha-2 country code when known.
	 */
	public function country_code(): ?string {
		return $this->country_code;
	}

	/**
	 * Provider source identifier.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Resolution confidence between 0 and 1.
	 */
	public function confidence(): float {
		return $this->confidence;
	}

	/**
	 * Whether a non-empty country code was resolved.
	 */
	public function is_known(): bool {
		return null !== $this->country_code && '' !== $this->country_code;
	}

	/**
	 * Unknown context placeholder.
	 */
	public static function unknown(): self {
		return new self( null, 'unknown', 0.0 );
	}
}
