<?php
/**
 * Parses multicurrency admin POST payloads into settings rows.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Settings;

/**
 * Shared parser for currency editor POST data.
 */
final class CurrencySettingsParser {

	/**
	 * Creates a parser for currency editor POST payloads.
	 *
	 * @param Settings $settings Merchant settings store.
	 * @param Currency $base     Store base currency.
	 */
	public function __construct(
		private Settings $settings,
		private Currency $base
	) {
	}

	/**
	 * Parses currency editor POST rows into sanitized config rows.
	 *
	 * @param array<int|string, mixed> $raw Unslashed POST payload.
	 * @return array<string, array<string, mixed>>
	 */
	public function parse( array $raw ): array {
		$currencies = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$code = isset( $row['code'] ) ? strtoupper( sanitize_text_field( (string) $row['code'] ) ) : '';

			if ( '' === $code || $code === $this->base->code() ) {
				continue;
			}

			$existing = $this->settings->get_currency_config( $code ) ?? array();

			$manual_rate         = isset( $row['manual_rate'] ) ? sanitize_text_field( (string) $row['manual_rate'] ) : '';
			$merchant_adjustment = isset( $row['merchant_adjustment'] ) ? sanitize_text_field( (string) $row['merchant_adjustment'] ) : '0';
			$rate_mode           = isset( $row['rate_mode'] ) ? sanitize_text_field( (string) $row['rate_mode'] ) : '';
			$rate_updated_at     = (int) ( $existing['rate_updated_at'] ?? 0 );

			if ( $this->rate_inputs_changed( $existing, $manual_rate, $merchant_adjustment, $rate_mode ) ) {
				$rate_updated_at = time();
			}

			$currencies[ $code ] = array(
				'enabled'             => ! empty( $row['enabled'] ),
				'symbol'              => isset( $row['symbol'] ) ? sanitize_text_field( (string) $row['symbol'] ) : '',
				'position'            => isset( $row['position'] ) ? sanitize_text_field( (string) $row['position'] ) : Currency::DEFAULT_POSITION,
				'decimals'            => isset( $row['decimals'] ) ? (int) $row['decimals'] : Currency::DEFAULT_DECIMALS,
				'manual_rate'         => $manual_rate,
				'merchant_adjustment' => $merchant_adjustment,
				'rate_mode'           => $rate_mode,
				'provider_rate'       => $existing['provider_rate'] ?? '',
				'rate_updated_at'     => $rate_updated_at,
			);
		}

		return $currencies;
	}

	/**
	 * Whether merchant-editable rate inputs differ from the stored row.
	 *
	 * @param array<string, mixed> $existing            Stored currency configuration.
	 * @param string               $manual_rate         Incoming manual rate.
	 * @param string               $merchant_adjustment Incoming adjustment percentage.
	 * @param string               $rate_mode           Incoming per-currency rate mode.
	 */
	private function rate_inputs_changed(
		array $existing,
		string $manual_rate,
		string $merchant_adjustment,
		string $rate_mode
	): bool {
		$existing_manual     = Settings::normalize_rate( $existing['manual_rate'] ?? ( $existing['rate'] ?? '' ) );
		$existing_adjustment = Settings::enforce_adjustment_range(
			Settings::normalize_adjustment( $existing['merchant_adjustment'] ?? '0' )
		);
		$existing_mode       = isset( $existing['rate_mode'] ) ? (string) $existing['rate_mode'] : '';

		$incoming_manual     = Settings::normalize_rate( $manual_rate );
		$incoming_adjustment = Settings::enforce_adjustment_range(
			Settings::normalize_adjustment( $merchant_adjustment )
		);

		return $existing_manual !== $incoming_manual
			|| $existing_adjustment !== $incoming_adjustment
			|| $existing_mode !== $rate_mode;
	}
}
