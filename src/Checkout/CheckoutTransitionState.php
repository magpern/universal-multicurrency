<?php
/**
 * Checkout transition state value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Immutable checkout transition captured for notices and order snapshots.
 */
final class CheckoutTransitionState {

	public const REASON_STORE_CURRENCY = 'store_currency_at_checkout';

	public const REASON_UNSUPPORTED_SELECTED = 'unsupported_selected_currency';

	/**
	 * Configured checkout mode.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Shopper-selected currency code.
	 *
	 * @var string
	 */
	private string $shopper_currency;

	/**
	 * Effective checkout currency code.
	 *
	 * @var string
	 */
	private string $effective_currency;

	/**
	 * Transition reason, or empty when none applies.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Whether a gateway fallback occurred.
	 *
	 * @var bool
	 */
	private bool $fallback_occurred;

	/**
	 * Whether fallback was attempted this checkout session.
	 *
	 * @var bool
	 */
	private bool $fallback_attempted;

	/**
	 * Creates transition state.
	 *
	 * @param string $mode               Configured checkout mode.
	 * @param string $shopper_currency     Shopper currency code.
	 * @param string $effective_currency   Effective checkout currency code.
	 * @param string $reason               Transition reason.
	 * @param bool   $fallback_occurred    Whether fallback occurred.
	 * @param bool   $fallback_attempted   Whether fallback was attempted.
	 */
	public function __construct(
		string $mode,
		string $shopper_currency,
		string $effective_currency,
		string $reason = '',
		bool $fallback_occurred = false,
		bool $fallback_attempted = false
	) {
		$this->mode               = CheckoutSettings::sanitize_mode( $mode );
		$this->shopper_currency   = strtoupper( $shopper_currency );
		$this->effective_currency = strtoupper( $effective_currency );
		$this->reason             = trim( $reason );
		$this->fallback_occurred  = $fallback_occurred;
		$this->fallback_attempted = $fallback_attempted;
	}

	/**
	 * Whether a customer-visible transition exists.
	 */
	public function has_transition(): bool {
		return '' !== $this->reason && $this->shopper_currency !== $this->effective_currency;
	}

	/**
	 * Signature used to dedupe notices across surfaces.
	 */
	public function notice_signature(): string {
		return sprintf(
			'%s|%s|%s|%s',
			$this->mode,
			$this->shopper_currency,
			$this->effective_currency,
			$this->reason
		);
	}

	/**
	 * Configured checkout mode.
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Shopper-selected currency code.
	 */
	public function shopper_currency(): string {
		return $this->shopper_currency;
	}

	/**
	 * Effective checkout currency code.
	 */
	public function effective_currency(): string {
		return $this->effective_currency;
	}

	/**
	 * Transition reason, or empty when none applies.
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Whether gateway fallback occurred.
	 */
	public function fallback_occurred(): bool {
		return $this->fallback_occurred;
	}

	/**
	 * Whether fallback was attempted this checkout session.
	 */
	public function fallback_attempted(): bool {
		return $this->fallback_attempted;
	}

	/**
	 * Exports a session-safe array representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'mode'               => $this->mode,
			'shopper_currency'   => $this->shopper_currency,
			'effective_currency' => $this->effective_currency,
			'reason'             => $this->reason,
			'fallback_occurred'  => $this->fallback_occurred,
			'fallback_attempted' => $this->fallback_attempted,
		);
	}

	/**
	 * Rehydrates transition state from session data.
	 *
	 * @param mixed $raw Stored session payload.
	 */
	public static function from_array( mixed $raw ): ?self {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		return new self(
			(string) ( $raw['mode'] ?? CheckoutSettings::MODE_SELECTED ),
			(string) ( $raw['shopper_currency'] ?? '' ),
			(string) ( $raw['effective_currency'] ?? '' ),
			(string) ( $raw['reason'] ?? '' ),
			! empty( $raw['fallback_occurred'] ),
			! empty( $raw['fallback_attempted'] )
		);
	}
}
