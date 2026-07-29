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

	public const REASON_SETTLE_BASE = 'settle_base_at_checkout';

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
	 * Settlement currency code used for gateways and order creation.
	 *
	 * @var string
	 */
	private string $settlement_currency;

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
	 * @param string $settlement_currency  Settlement currency code.
	 */
	public function __construct(
		string $mode,
		string $shopper_currency,
		string $effective_currency,
		string $reason = '',
		bool $fallback_occurred = false,
		bool $fallback_attempted = false,
		string $settlement_currency = ''
	) {
		$this->mode               = CheckoutSettings::sanitize_mode( $mode );
		$this->shopper_currency   = strtoupper( $shopper_currency );
		$this->effective_currency = strtoupper( $effective_currency );
		$this->reason             = trim( $reason );
		$this->fallback_occurred  = $fallback_occurred;
		$this->fallback_attempted = $fallback_attempted;

		$settlement = '' !== $settlement_currency ? strtoupper( $settlement_currency ) : $this->effective_currency;
		$this->settlement_currency = $settlement;
	}

	/**
	 * Whether a customer-visible transition exists.
	 */
	public function has_transition(): bool {
		if ( '' === $this->reason ) {
			return false;
		}

		return $this->shopper_currency !== $this->settlement_currency;
	}

	/**
	 * Signature used to dedupe notices across surfaces.
	 */
	public function notice_signature(): string {
		return sprintf(
			'%s|%s|%s|%s|%s',
			$this->mode,
			$this->shopper_currency,
			$this->effective_currency,
			$this->settlement_currency,
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
	 * Settlement currency code for gateways and order creation.
	 */
	public function settlement_currency(): string {
		return $this->settlement_currency;
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
			'mode'                => $this->mode,
			'shopper_currency'    => $this->shopper_currency,
			'effective_currency'  => $this->effective_currency,
			'settlement_currency' => $this->settlement_currency,
			'reason'              => $this->reason,
			'fallback_occurred'   => $this->fallback_occurred,
			'fallback_attempted'  => $this->fallback_attempted,
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

		$effective = (string) ( $raw['effective_currency'] ?? '' );

		return new self(
			(string) ( $raw['mode'] ?? CheckoutSettings::MODE_SELECTED ),
			(string) ( $raw['shopper_currency'] ?? '' ),
			$effective,
			(string) ( $raw['reason'] ?? '' ),
			! empty( $raw['fallback_occurred'] ),
			! empty( $raw['fallback_attempted'] ),
			(string) ( $raw['settlement_currency'] ?? $effective )
		);
	}
}
