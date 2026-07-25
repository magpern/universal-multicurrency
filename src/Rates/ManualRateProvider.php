<?php

/**
 * Manual (admin-entered) exchange-rate provider.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Resolves rates from admin-entered values in {@see Settings}.
 *
 * Stored rates are all expressed relative to the store base currency
 * (1 base = rate target). This provider performs no arithmetic — it only
 * looks up and returns rate strings; all calculation lives in the converter.
 */
final class ManualRateProvider implements RateProvider {

	/**
	 * Settings store holding the configured rates.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * The store base currency code (uppercase).
	 *
	 * @var string
	 */
	private string $base_code;

	/**
	 * Binds the provider to a settings store and the store base currency.
	 *
	 * @param Settings $settings           Settings store holding configured rates.
	 * @param string   $base_currency_code Store base currency code.
	 */
	public function __construct( Settings $settings, string $base_currency_code ) {
		$this->settings  = $settings;
		$this->base_code = strtoupper( $base_currency_code );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $base_code   Base (source) currency code.
	 * @param string $target_code Target currency code.
	 */
	public function get_rate( string $base_code, string $target_code ): ?string {
		$base_code   = strtoupper( $base_code );
		$target_code = strtoupper( $target_code );

		if ( $base_code === $target_code ) {
			return '1';
		}

		// Only base-to-target lookups are supported; stored rates are relative
		// to the store base currency.
		if ( $base_code !== $this->base_code ) {
			return null;
		}

		return $this->settings->get_rate( $target_code );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $base_code   Base (source) currency code.
	 * @param string $target_code Target currency code.
	 */
	public function has_rate( string $base_code, string $target_code ): bool {
		return null !== $this->get_rate( $base_code, $target_code );
	}
}
