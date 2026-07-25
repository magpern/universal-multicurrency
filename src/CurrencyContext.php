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
	 * Frontend page views and frontend AJAX convert; admin screens, REST/Store
	 * API, cron and WP-CLI do not. Filterable via `umc_is_request_convertible`.
	 */
	public function is_convertible_request(): bool {
		if ( null !== $this->convertible ) {
			return $this->convertible;
		}

		$convertible = true;

		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$convertible = false;
		} elseif ( function_exists( 'WC' ) && WC()->is_rest_api_request() ) {
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
	 * Reads the explicitly requested code from the query string, if present.
	 */
	private function read_explicit(): ?string {
		// Display preference only; validated against the allow-list in the
		// resolver. State changes happen in CurrencySwitcher, not here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only currency preference, allow-list validated, no state change.
		return isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : null;
	}

	/**
	 * Reads the selected code from the WooCommerce session, if available.
	 */
	private function read_session(): ?string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return null;
		}

		$value = WC()->session->get( self::SESSION_KEY );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Reads the selected code from the guest cookie, if present.
	 */
	private function read_cookie(): ?string {
		return isset( $_COOKIE[ self::COOKIE_NAME ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
			: null;
	}
}
