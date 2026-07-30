<?php
/**
 * Result of ordered geo rule evaluation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Pure evaluation outcome for routing and simulation traces.
 */
final class GeoRuleEvaluationResult {

	/**
	 * Constructs a rule evaluation result.
	 *
	 * @param string|null                     $currency                Final currency or null.
	 * @param string|null                     $matched_rule_id      Matched rule id.
	 * @param string|null                     $matched_rule_type    Matched rule type.
	 * @param string|null                     $matched_rule_label   Human label.
	 * @param int|null                        $matched_rule_index   Zero-based index.
	 * @param bool                            $catch_all_matched    Whether other rule matched.
	 * @param bool                            $technical_fallback_used Whether technical fallback applied.
	 * @param array<int, array<string,mixed>> $trace                   Ordered step trace.
	 * @param array<int, string>              $warnings                Diagnostic warnings.
	 */
	public function __construct(
		private ?string $currency,
		private ?string $matched_rule_id,
		private ?string $matched_rule_type,
		private ?string $matched_rule_label,
		private ?int $matched_rule_index,
		private bool $catch_all_matched,
		private bool $technical_fallback_used,
		private array $trace,
		private array $warnings
	) {
	}

	/**
	 * Final matched currency code.
	 */
	public function currency(): ?string {
		return $this->currency;
	}

	/**
	 * Matched rule identifier.
	 */
	public function matched_rule_id(): ?string {
		return $this->matched_rule_id;
	}

	/**
	 * Matched rule type.
	 */
	public function matched_rule_type(): ?string {
		return $this->matched_rule_type;
	}

	/**
	 * Human-readable matched rule label.
	 */
	public function matched_rule_label(): ?string {
		return $this->matched_rule_label;
	}

	/**
	 * Zero-based index of the matched rule.
	 */
	public function matched_rule_index(): ?int {
		return $this->matched_rule_index;
	}

	/**
	 * Whether the catch-all rule matched.
	 */
	public function catch_all_matched(): bool {
		return $this->catch_all_matched;
	}

	/**
	 * Whether technical fallback was applied.
	 */
	public function technical_fallback_used(): bool {
		return $this->technical_fallback_used;
	}

	/**
	 * Ordered evaluation trace steps.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function trace(): array {
		return $this->trace;
	}

	/**
	 * Diagnostic warnings from evaluation.
	 *
	 * @return list<string>
	 */
	public function warnings(): array {
		return $this->warnings;
	}
}
