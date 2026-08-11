<?php
/**
 * Deterministic inputs for currency decision explanation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Decision;

/**
 * Immutable explanation/simulation input set.
 */
final class DecisionExplanationInput {

	/**
	 * Creates explanation inputs.
	 *
	 * @param string|null        $explicit_currency   Explicit currency.
	 * @param string|null        $session_currency    Session currency.
	 * @param string|null        $cookie_currency     Cookie currency.
	 * @param string             $base_currency       Store base.
	 * @param array<int, string> $selectable          Selectable codes.
	 * @param bool               $manual_selection    Manual selection flag.
	 * @param string|null        $currency_origin     Provenance metadata.
	 * @param bool               $order_context_active Whether order context is active.
	 * @param bool               $geo_enabled         Whether geo is enabled.
	 * @param string             $country_code        ISO country code.
	 * @param bool               $checkout_locked     Checkout geo lock.
	 * @param bool               $include_checkout    Whether to explain checkout.
	 * @param string             $checkout_mode       selected|store.
	 * @param bool               $show_notice         Checkout notice setting.
	 * @param bool               $payment_required    Whether payment is required.
	 * @param bool               $gateway_supports_display Whether gateways support display currency.
	 * @param string|null        $ugc_available       Availability label code, optional.
	 */
	public function __construct(
		private ?string $explicit_currency,
		private ?string $session_currency,
		private ?string $cookie_currency,
		private string $base_currency,
		private array $selectable,
		private bool $manual_selection = false,
		private ?string $currency_origin = null,
		private bool $order_context_active = false,
		private bool $geo_enabled = true,
		private string $country_code = '',
		private bool $checkout_locked = false,
		private bool $include_checkout = false,
		private string $checkout_mode = 'selected',
		private bool $show_notice = true,
		private bool $payment_required = true,
		private bool $gateway_supports_display = true,
		private ?string $ugc_available = null
	) {
	}

	/**
	 * Explicit currency.
	 */
	public function explicit_currency(): ?string {
		return $this->explicit_currency;
	}

	/**
	 * Session currency.
	 */
	public function session_currency(): ?string {
		return $this->session_currency;
	}

	/**
	 * Cookie currency.
	 */
	public function cookie_currency(): ?string {
		return $this->cookie_currency;
	}

	/**
	 * Base currency.
	 */
	public function base_currency(): string {
		return $this->base_currency;
	}

	/**
	 * Selectable currencies.
	 *
	 * @return array<int, string>
	 */
	public function selectable(): array {
		return $this->selectable;
	}

	/**
	 * Manual selection flag.
	 */
	public function manual_selection(): bool {
		return $this->manual_selection;
	}

	/**
	 * Currency origin provenance.
	 */
	public function currency_origin(): ?string {
		return $this->currency_origin;
	}

	/**
	 * Whether order-owned context is active.
	 */
	public function order_context_active(): bool {
		return $this->order_context_active;
	}

	/**
	 * Whether geo is enabled.
	 */
	public function geo_enabled(): bool {
		return $this->geo_enabled;
	}

	/**
	 * Country code.
	 */
	public function country_code(): string {
		return $this->country_code;
	}

	/**
	 * Whether checkout is locked for geo.
	 */
	public function checkout_locked(): bool {
		return $this->checkout_locked;
	}

	/**
	 * Whether checkout explanation is included.
	 */
	public function include_checkout(): bool {
		return $this->include_checkout;
	}

	/**
	 * Checkout mode.
	 */
	public function checkout_mode(): string {
		return $this->checkout_mode;
	}

	/**
	 * Whether customer notices are enabled.
	 */
	public function show_notice(): bool {
		return $this->show_notice;
	}

	/**
	 * Whether payment is required.
	 */
	public function payment_required(): bool {
		return $this->payment_required;
	}

	/**
	 * Whether gateways support the display/shopper currency.
	 */
	public function gateway_supports_display(): bool {
		return $this->gateway_supports_display;
	}

	/**
	 * Optional UGC availability code.
	 */
	public function ugc_available(): ?string {
		return $this->ugc_available;
	}
}
