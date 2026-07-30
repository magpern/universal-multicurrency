<?php
/**
 * Save-time validation outcome for geo routing rules.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Hard errors and advisory warnings from rule validation.
 */
final class GeoRoutingValidationResult {

	/**
	 * Constructs a validation result.
	 *
	 * @param array<int, string> $errors   Blocking errors.
	 * @param array<int, string> $warnings Advisory warnings.
	 */
	public function __construct(
		private array $errors,
		private array $warnings
	) {
	}

	/**
	 * Blocking validation errors.
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Advisory validation warnings.
	 *
	 * @return list<string>
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * Whether no blocking errors were recorded.
	 */
	public function is_valid(): bool {
		return array() === $this->errors;
	}
}
