<?php
/**
 * Currency registry.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Assembles {@see Currency} objects from the injected base currency and the
 * configured currencies in {@see Settings}.
 *
 * Pure domain: the base currency is injected, never read from
 * `woocommerce_currency` here. Whoever composes the registry is responsible
 * for reading the store base currency and building the base Currency. The base
 * is always present and always enabled, and a configured row sharing the base
 * code never overrides the base's identity.
 */
final class CurrencyRegistry {

	/**
	 * Configured-currency source.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * The store base currency, forced enabled.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Binds the registry to its settings and the store base currency.
	 *
	 * @param Settings $settings       Configured-currency source.
	 * @param Currency $base_currency  Store base currency (forced enabled).
	 */
	public function __construct( Settings $settings, Currency $base_currency ) {
		$this->settings = $settings;
		$this->base     = $base_currency->is_enabled()
			? $base_currency
			: new Currency(
				$base_currency->code(),
				$base_currency->decimals(),
				$base_currency->symbol(),
				$base_currency->position(),
				true
			);
	}

	/**
	 * The base currency code.
	 */
	public function get_base_code(): string {
		return $this->base->code();
	}

	/**
	 * The base currency.
	 */
	public function get_base_currency(): Currency {
		return $this->base;
	}

	/**
	 * Whether a code is the base currency.
	 *
	 * @param string $code Currency code.
	 */
	public function is_base( string $code ): bool {
		return strtoupper( $code ) === $this->base->code();
	}

	/**
	 * The Currency for a code, or null when neither base nor configured.
	 *
	 * @param string $code Currency code.
	 */
	public function get_currency( string $code ): ?Currency {
		$code = strtoupper( $code );

		if ( $this->is_base( $code ) ) {
			return $this->base;
		}

		$config = $this->settings->get_currency_config( $code );

		return null === $config ? null : Currency::from_array( $code, $config );
	}

	/**
	 * Whether a code resolves to a known currency.
	 *
	 * @param string $code Currency code.
	 */
	public function has_currency( string $code ): bool {
		return null !== $this->get_currency( $code );
	}

	/**
	 * All known currencies: the base first, then configured currencies
	 * (excluding any row sharing the base code).
	 *
	 * @return array<int, Currency>
	 */
	public function get_currencies(): array {
		$currencies = array( $this->base );

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			if ( $this->is_base( $code ) ) {
				continue;
			}

			$currencies[] = Currency::from_array( $code, $config );
		}

		return $currencies;
	}

	/**
	 * All enabled currencies (the base is always enabled).
	 *
	 * @return array<int, Currency>
	 */
	public function get_enabled_currencies(): array {
		return array_values(
			array_filter(
				$this->get_currencies(),
				static fn ( Currency $currency ): bool => $currency->is_enabled()
			)
		);
	}
}
