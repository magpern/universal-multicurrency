<?php
/**
 * Currency switch action.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Handles an explicit currency switch request.
 *
 * Validates the requested code against the selectable allow-list, persists it
 * to the WooCommerce session and a 30-day guest cookie, then safe-redirects to
 * the same URL without the query parameter (keeping it out of caches, history
 * and search indexes). No nonce is used: the action only changes the visitor's
 * own display preference and is idempotent.
 */
final class CurrencySwitcher {

	private const COOKIE_LIFETIME = 30 * DAY_IN_SECONDS;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the switcher to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Handles the switch when a `?currency=` parameter is present.
	 *
	 * When the parameter is present it is consumed (persisted if valid) and the
	 * request is redirected to the same URL without it. Absent parameter is a
	 * no-op so normal requests are untouched.
	 */
	public function maybe_switch(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Own display preference, allow-list validated, no nonce by design.
		if ( ! isset( $_GET[ CurrencyContext::QUERY_VAR ] ) ) {
			return;
		}

		$requested = $this->requested_code();

		if ( null !== $requested ) {
			$this->persist( $requested );
		}

		if ( headers_sent() ) {
			return;
		}

		wp_safe_redirect( remove_query_arg( CurrencyContext::QUERY_VAR ) );
		$this->halt();
	}

	/**
	 * Returns the requested code if present and selectable, otherwise null.
	 */
	public function requested_code(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Own display preference, allow-list validated, no nonce by design.
		if ( ! isset( $_GET[ CurrencyContext::QUERY_VAR ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Own display preference, allow-list validated, no nonce by design.
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET[ CurrencyContext::QUERY_VAR ] ) ) );

		return in_array( $code, $this->context->get_selectable_codes(), true ) ? $code : null;
	}

	/**
	 * Persists the selected code to the session and the guest cookie.
	 *
	 * @param string $code Validated currency code.
	 */
	public function persist( string $code ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, $code );
		}

		wc_setcookie( CurrencyContext::COOKIE_NAME, $code, time() + self::COOKIE_LIFETIME, is_ssl(), false );
	}

	/**
	 * Ends the request after a redirect. Isolated for testability.
	 *
	 * @codeCoverageIgnore
	 */
	protected function halt(): void {
		exit;
	}
}
