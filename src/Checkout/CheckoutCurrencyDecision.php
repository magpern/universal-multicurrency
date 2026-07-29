<?php
/**
 * Checkout currency policy decision.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Result of a checkout policy evaluation pass.
 */
final class CheckoutCurrencyDecision {

	/**
	 * Effective checkout currency for this pass.
	 *
	 * @var string
	 */
	private string $effective_currency;

	/**
	 * Transition reason, or empty when none applies.
	 *
	 * @var string
	 */
	private string $transition_reason;

	/**
	 * Whether a fallback pass should run.
	 *
	 * @var bool
	 */
	private bool $should_fallback;

	/**
	 * Whether fallback occurred in this decision.
	 *
	 * @var bool
	 */
	private bool $fallback_occurred;

	/**
	 * Creates a policy decision.
	 *
	 * @param string $effective_currency Effective checkout currency.
	 * @param string $transition_reason  Transition reason.
	 * @param bool   $should_fallback    Whether fallback should run next.
	 * @param bool   $fallback_occurred  Whether fallback occurred.
	 */
	public function __construct(
		string $effective_currency,
		string $transition_reason = '',
		bool $should_fallback = false,
		bool $fallback_occurred = false
	) {
		$this->effective_currency = strtoupper( $effective_currency );
		$this->transition_reason  = trim( $transition_reason );
		$this->should_fallback    = $should_fallback;
		$this->fallback_occurred  = $fallback_occurred;
	}

	/**
	 * Effective checkout currency for this pass.
	 */
	public function effective_currency(): string {
		return $this->effective_currency;
	}

	/**
	 * Transition reason, or empty when none applies.
	 */
	public function transition_reason(): string {
		return $this->transition_reason;
	}

	/**
	 * Whether a fallback pass should run next.
	 */
	public function should_fallback(): bool {
		return $this->should_fallback;
	}

	/**
	 * Whether fallback occurred in this decision.
	 */
	public function fallback_occurred(): bool {
		return $this->fallback_occurred;
	}
}
