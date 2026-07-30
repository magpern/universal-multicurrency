<?php
/**
 * Currency switch action.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Display\SwitcherSettingsRepository;

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

	public const SESSION_MANUAL_SELECTION = 'umc_manual_currency';

	private const COOKIE_LIFETIME = 30 * DAY_IN_SECONDS;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Display settings repository for persistence policy.
	 *
	 * @var SwitcherSettingsRepository
	 */
	private SwitcherSettingsRepository $settings_repository;

	/**
	 * Binds the switcher to the currency context.
	 *
	 * @param CurrencyContext            $context             Request-scoped currency facade.
	 * @param SwitcherSettingsRepository $settings_repository Display settings repository.
	 */
	public function __construct( CurrencyContext $context, SwitcherSettingsRepository $settings_repository ) {
		$this->context             = $context;
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Handles the switch when a `?currency=` parameter is present.
	 *
	 * When the parameter is present it is consumed (persisted if valid) and the
	 * request is redirected to the same URL without it. Absent parameter is a
	 * no-op so normal requests are untouched.
	 *
	 * REST requests never switch. Answering an API call with a redirect to a
	 * storefront URL would corrupt the response, and persisting a preference as
	 * a side effect of a read would be surprising for API consumers. A
	 * `?currency=` argument on a REST request still resolves for that response
	 * alone, because {@see CurrencyContext} reads it independently.
	 */
	public function maybe_switch(): void {
		if ( $this->is_rest_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Own display preference, allow-list validated, no nonce by design.
		if ( ! isset( $_GET[ CurrencyContext::QUERY_VAR ] ) ) {
			return;
		}

		$requested = $this->requested_code();

		if ( null !== $requested ) {
			$this->persist( $requested, true );
		}

		if ( headers_sent() ) {
			return;
		}

		wp_safe_redirect( remove_query_arg( CurrencyContext::QUERY_VAR ) );
		$this->halt();
	}

	/**
	 * Whether the current request is served by the REST API.
	 */
	private function is_rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return function_exists( 'WC' ) && WC()->is_rest_api_request();
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
	 * @param string $code   Validated currency code.
	 * @param bool   $manual Whether the shopper explicitly chose this currency.
	 */
	public function persist( string $code, bool $manual = false ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( CurrencyContext::SESSION_KEY, $code );

			if ( $manual ) {
				WC()->session->set( self::SESSION_MANUAL_SELECTION, '1' );
			}
		}

		if ( $this->settings_repository->remember_selection() ) {
			wc_setcookie( CurrencyContext::COOKIE_NAME, $code, time() + self::COOKIE_LIFETIME, is_ssl(), false );
			return;
		}

		$this->delete_remembered_cookie();
	}

	/**
	 * Expires any remembered guest currency cookie.
	 */
	private function delete_remembered_cookie(): void {
		wc_setcookie( CurrencyContext::COOKIE_NAME, '', time() - YEAR_IN_SECONDS, is_ssl(), false );
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
