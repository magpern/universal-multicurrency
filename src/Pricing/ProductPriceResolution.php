<?php
/**
 * Result of resolving one product price field.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Explicit product price resolution outcome.
 */
final class ProductPriceResolution {

	public const SOURCE_FIXED     = 'fixed';
	public const SOURCE_CONVERTED = 'converted';

	/**
	 * Captures one resolved product price field.
	 *
	 * @param mixed  $amount   Resolved price value.
	 * @param string $source   {@see SOURCE_FIXED} or {@see SOURCE_CONVERTED}.
	 * @param string $currency Active currency code.
	 * @param string $field    Price field: regular, sale, or price.
	 */
	public function __construct(
		private mixed $amount,
		private string $source,
		private string $currency,
		private string $field
	) {
	}

	/**
	 * Resolved monetary value for WooCommerce.
	 */
	public function amount(): mixed {
		return $this->amount;
	}

	/**
	 * Pricing source identifier.
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Active currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Which getter field was resolved.
	 */
	public function field(): string {
		return $this->field;
	}

	/**
	 * Whether this resolution used a fixed price.
	 */
	public function is_fixed(): bool {
		return self::SOURCE_FIXED === $this->source;
	}
}
