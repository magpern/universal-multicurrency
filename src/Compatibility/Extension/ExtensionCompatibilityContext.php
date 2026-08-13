<?php
/**
 * Request-scoped extension renewal context state.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Tracks subscription renewal isolation context per request.
 */
final class ExtensionCompatibilityContext {

	/**
	 * Whether the current request is generating a subscription renewal order.
	 *
	 * @var bool
	 */
	private static bool $renewal_context = false;

	/**
	 * Subscription/order currency code locked during renewal generation.
	 *
	 * @var string
	 */
	private static string $renewal_currency = '';

	/**
	 * Marks renewal generation context to isolate browsing currency.
	 *
	 * @param string $currency Subscription/order currency code.
	 */
	public static function enter_renewal_context( string $currency ): void {
		self::$renewal_context  = true;
		self::$renewal_currency = strtoupper( $currency );
	}

	/**
	 * Clears renewal generation context.
	 */
	public static function exit_renewal_context(): void {
		self::$renewal_context  = false;
		self::$renewal_currency = '';
	}

	/**
	 * Whether renewal context is active.
	 */
	public static function is_renewal_context(): bool {
		return self::$renewal_context;
	}

	/**
	 * Locked renewal currency when in renewal context.
	 */
	public static function renewal_currency(): string {
		return self::$renewal_currency;
	}

	/**
	 * Whether product price conversion should be suppressed for renewal-owned amounts.
	 */
	public static function should_suppress_browsing_conversion(): bool {
		return self::$renewal_context && '' !== self::$renewal_currency;
	}

	/**
	 * Resets memoized state (testing).
	 */
	public static function reset(): void {
		self::$renewal_context  = false;
		self::$renewal_currency = '';
	}
}
