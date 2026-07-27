<?php
/**
 * Request-scoped active-currency facade.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

use UMC\Rates\RateProvider;

/**
 * Lightweight request facade over the currency domain.
 *
 * Gathers the raw candidates (explicit request param, WC session, guest
 * cookie), delegates the choice to {@see CurrencyResolver}, resolves it to a
 * {@see Currency}, and computes the base→active rate once. Everything is
 * memoized for the request. This class touches WordPress/WooCommerce
 * request state; the resolution logic itself stays in the pure resolver.
 */
final class CurrencyContext {

	public const SESSION_KEY = 'umc_currency';
	public const COOKIE_NAME = 'umc_currency';
	public const QUERY_VAR   = 'currency';

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Rate provider.
	 *
	 * @var RateProvider
	 */
	private RateProvider $rates;

	/**
	 * Resolution logic.
	 *
	 * @var CurrencyResolver
	 */
	private CurrencyResolver $resolver;

	/**
	 * Memoized active currency.
	 *
	 * @var Currency|null
	 */
	private ?Currency $active = null;

	/**
	 * Memoized base→active rate.
	 *
	 * @var string|null
	 */
	private ?string $rate = null;

	/**
	 * Memoized convertible-request decision.
	 *
	 * @var bool|null
	 */
	private ?bool $convertible = null;

	/**
	 * Binds the facade to its collaborators.
	 *
	 * @param CurrencyRegistry $registry Currency registry.
	 * @param RateProvider     $rates    Rate provider.
	 * @param CurrencyResolver $resolver Resolution logic.
	 */
	public function __construct( CurrencyRegistry $registry, RateProvider $rates, CurrencyResolver $resolver ) {
		$this->registry = $registry;
		$this->rates    = $rates;
		$this->resolver = $resolver;
	}

	/**
	 * The base currency.
	 */
	public function get_base_currency(): Currency {
		return $this->registry->get_base_currency();
	}

	/**
	 * Resolves a code to a Currency (base or configured), or null.
	 *
	 * @param string $code Currency code.
	 */
	public function get_currency( string $code ): ?Currency {
		return $this->registry->get_currency( $code );
	}

	/**
	 * The currency codes that may be activated: enabled and rated, plus base.
	 *
	 * @return array<int, string>
	 */
	public function get_selectable_codes(): array {
		$base  = $this->registry->get_base_code();
		$codes = array( $base );

		foreach ( $this->registry->get_enabled_currencies() as $currency ) {
			$code = $currency->code();

			if ( $code === $base || $this->rates->has_rate( $base, $code ) ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * The active currency for this request (memoized).
	 */
	public function get_active_currency(): Currency {
		if ( null !== $this->active ) {
			return $this->active;
		}

		$base = $this->registry->get_base_code();
		$code = $this->resolver->resolve(
			$this->read_explicit(),
			$this->read_session(),
			$this->read_cookie(),
			$base,
			$this->get_selectable_codes()
		);

		$currency     = $this->registry->get_currency( $code );
		$this->active = $currency ?? $this->registry->get_base_currency();

		return $this->active;
	}

	/**
	 * The active currency code.
	 */
	public function get_active_code(): string {
		return $this->get_active_currency()->code();
	}

	/**
	 * Whether the base currency is the active currency.
	 */
	public function is_base_active(): bool {
		return $this->registry->is_base( $this->get_active_code() );
	}

	/**
	 * The base→active exchange rate as a decimal string ('1' when base is active).
	 */
	public function get_rate(): string {
		if ( null !== $this->rate ) {
			return $this->rate;
		}

		if ( $this->is_base_active() ) {
			$this->rate = '1';

			return $this->rate;
		}

		$rate       = $this->rates->get_rate( $this->registry->get_base_code(), $this->get_active_code() );
		$this->rate = ( null === $rate ) ? '1' : $rate;

		return $this->rate;
	}

	/**
	 * The rate identity for the active currency, e.g. "SEK:11.50" (base: "EUR:1").
	 *
	 * A currency code alone is not enough to key a monetary cache: a configured
	 * rate can change while the code stays the same. Combining the active code
	 * with the exact rate string yields a value that changes whenever either
	 * does, so caches keyed by it self-invalidate on a rate edit. Filterable via
	 * `umc_currency_signature` for callers that resolve custom rate identities.
	 */
	public function get_currency_signature(): string {
		$code = $this->get_active_code();
		$rate = $this->get_rate();

		/**
		 * Filters the active currency's rate identity used for cache isolation.
		 *
		 * @since 0.3.0
		 *
		 * @param string $signature Default `code:rate` signature.
		 * @param string $code      Active currency code.
		 * @param string $rate      Base→active rate string.
		 */
		return (string) apply_filters( 'umc_currency_signature', $code . ':' . $rate, $code, $rate );
	}

	/**
	 * Whether prices should be converted for the current request.
	 *
	 * Frontend page views, frontend AJAX and the Store API convert; admin
	 * screens, other REST namespaces, cron and WP-CLI do not. The Store API
	 * backs the Cart and Checkout blocks, which are storefront surfaces in
	 * everything but transport, so they resolve and convert exactly as a page
	 * view does. Every other REST namespace — the admin REST API included —
	 * keeps reporting base-currency values, because those consumers expect the
	 * stored data rather than a shopper's presentation of it.
	 *
	 * Filterable via `umc_is_request_convertible`.
	 */
	public function is_convertible_request(): bool {
		if ( null !== $this->convertible ) {
			return $this->convertible;
		}

		$convertible = true;

		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$convertible = false;
		} elseif ( function_exists( 'WC' ) && WC()->is_rest_api_request() && ! $this->is_store_api_request() ) {
			$convertible = false;
		} elseif ( is_admin() && ! wp_doing_ajax() ) {
			$convertible = false;
		}

		/**
		 * Filters whether the current request should have prices converted.
		 *
		 * @since 0.2.0
		 *
		 * @param bool $convertible Whether conversion applies.
		 */
		$this->convertible = (bool) apply_filters( 'umc_is_request_convertible', $convertible );

		return $this->convertible;
	}

	/**
	 * Whether the current request targets the WooCommerce Store API.
	 *
	 * Prefers the route WordPress parsed, anchored at the start, which is what
	 * WooCommerce's own Store API authentication matches on. Matching the raw
	 * request URI instead would accept the namespace anywhere in the string, so
	 * a query argument that happened to contain a Store API path — a redirect
	 * target, a search term — would be read as a Store API request and open the
	 * conversion boundary on a route that should never convert.
	 *
	 * The URI test remains as a fallback for dispatches that carry no parsed
	 * route, and is deliberately identical to WooCommerce's own so behaviour
	 * there is unchanged.
	 */
	private function is_store_api_request(): bool {
		$route = $this->parsed_rest_route();

		if ( null !== $route ) {
			return 0 === strpos( $route, '/wc/store/' );
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		return false !== strpos( $uri, trailingslashit( rest_get_url_prefix() ) . 'wc/store/' );
	}

	/**
	 * The REST route WordPress resolved for this request, if any.
	 */
	private function parsed_rest_route(): ?string {
		if ( ! isset( $GLOBALS['wp'] ) || ! $GLOBALS['wp'] instanceof \WP ) {
			return null;
		}

		$route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		return is_string( $route ) && '' !== $route ? $route : null;
	}

	/**
	 * Reads the explicitly requested code from the query string, if present.
	 */
	private function read_explicit(): ?string {
		// Display preference only; validated against the allow-list in the
		// resolver. State changes happen in CurrencySwitcher, not here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only currency preference, allow-list validated, no state change.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only preference; normalized in normalize_currency_code().
		return $this->normalize_currency_code( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
	}

	/**
	 * Reads the selected code from the WooCommerce session, if available.
	 */
	private function read_session(): ?string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}

		$value = WC()->session->get( self::SESSION_KEY );

		return $this->normalize_currency_code( $value );
	}

	/**
	 * Reads the selected code from the guest cookie, if present.
	 */
	private function read_cookie(): ?string {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalized in normalize_currency_code().
		return $this->normalize_currency_code( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
	}

	/**
	 * Normalizes a currency candidate to an uppercase ISO-style code or null.
	 *
	 * Rejects malformed values at the input boundary so session/cookie/query
	 * poisoning cannot propagate non-code strings toward resolution logic.
	 *
	 * @param mixed $value Raw candidate value.
	 */
	private function normalize_currency_code( mixed $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$code = strtoupper( trim( sanitize_text_field( (string) $value ) ) );

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) ) {
			return null;
		}

		return $code;
	}
}
