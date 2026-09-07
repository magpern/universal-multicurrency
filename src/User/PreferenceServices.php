<?php
/**
 * Bound preference services for public helpers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\User;

/**
 * Request/bootstrap binding for the preferred-currency public API.
 */
final class PreferenceServices {

	/**
	 * Bound preferred-currency service.
	 *
	 * @var PreferredCurrency|null
	 */
	private static ?PreferredCurrency $preferred_currency = null;

	/**
	 * Binds the preferred-currency service after bootstrap.
	 *
	 * @param PreferredCurrency $service Preference service.
	 */
	public static function bind_preferred_currency( PreferredCurrency $service ): void {
		self::$preferred_currency = $service;
	}

	/**
	 * Bound preferred-currency service, if available.
	 */
	public static function preferred_currency(): ?PreferredCurrency {
		return self::$preferred_currency;
	}
}
