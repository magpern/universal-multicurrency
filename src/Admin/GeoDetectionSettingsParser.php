<?php
/**
 * Geo Detection settings POST parser.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\GeoRoutingRule;
use UMC\Geo\GeoRoutingRuleValidator;
use UMC\Settings;

/**
 * Parses and validates Geo Detection settings submissions.
 */
final class GeoDetectionSettingsParser {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Routing rule validator.
	 *
	 * @var GeoRoutingRuleValidator
	 */
	private GeoRoutingRuleValidator $validator;

	/**
	 * Constructs the geo settings parser.
	 *
	 * @param Settings                     $settings  Settings store.
	 * @param CurrencyRegistry             $registry  Currency registry.
	 * @param GeoRoutingRuleValidator|null $validator Rule validator.
	 */
	public function __construct(
		Settings $settings,
		CurrencyRegistry $registry,
		?GeoRoutingRuleValidator $validator = null
	) {
		$this->settings  = $settings;
		$this->registry  = $registry;
		$this->validator = $validator ?? new GeoRoutingRuleValidator( new GeoRegionRegistry() );
	}

	/**
	 * Parses POST into a geo settings array.
	 *
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null Null when validation failed.
	 */
	public function parse_post(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		if ( ! isset( $_POST['umc_geo'] ) || ! is_array( $_POST['umc_geo'] ) ) {
			return array(
				'geo'      => GeoDetectionSettings::default_array(),
				'warnings' => array(),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = wp_unslash( $_POST['umc_geo'] );

		$rules_raw = $this->ordered_rules_from_post( $raw );

		$rules = array();

		foreach ( $rules_raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = isset( $row['id'] ) ? strtolower( sanitize_text_field( (string) $row['id'] ) ) : '';

			if ( 1 !== preg_match( GeoRoutingRule::ID_PATTERN, $id ) ) {
				$id = GeoRoutingRule::generate_id();
			}

			$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : '';

			$value = isset( $row['value'] ) ? sanitize_text_field( (string) $row['value'] ) : '';

			if ( GeoRoutingRule::TYPE_REGION === $type ) {
				$value = strtolower( $value );
			} elseif ( GeoRoutingRule::TYPE_COUNTRY === $type ) {
				$value = strtoupper( $value );
			} else {
				$value = '';
			}

			$currency = isset( $row['currency'] ) ? strtoupper( sanitize_text_field( (string) $row['currency'] ) ) : '';

			$rule = GeoRoutingRule::from_array(
				array(
					'id'       => $id,
					'type'     => $type,
					'value'    => $value,
					'currency' => $currency,
				)
			);

			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		$enabled = ! empty( $raw['enabled'] );
		$mode    = GeoDetectionSettings::sanitize_mode( $raw['mode'] ?? GeoDetectionSettings::MODE_FIRST_VISIT );

		$checkout_raw = is_array( $raw['checkout'] ?? null ) ? $raw['checkout'] : array();

		$geo_array = array(
			'enabled'                       => $enabled,
			'mode'                          => $mode,
			'fallback_currency'             => GeoDetectionSettings::sanitize_currency_code( $raw['fallback_currency'] ?? '' ),
			'allow_wc_geolocation_fallback' => ! isset( $raw['allow_wc_geolocation_fallback'] ) || (bool) $raw['allow_wc_geolocation_fallback'],
			'rules'                         => array_map(
				static fn( GeoRoutingRule $rule ): array => $rule->to_array(),
				$rules
			),
			'checkout'                      => array(
				'lock_on_entry'                 => ! isset( $checkout_raw['lock_on_entry'] ) || (bool) $checkout_raw['lock_on_entry'],
				'reevaluate_on_billing_change'  => ! empty( $checkout_raw['reevaluate_on_billing_change'] ),
				'reevaluate_on_shipping_change' => ! empty( $checkout_raw['reevaluate_on_shipping_change'] ),
				'country_precedence'            => GeoDetectionSettings::sanitize_precedence( $checkout_raw['country_precedence'] ?? GeoDetectionSettings::PRECEDENCE_BILLING ),
			),
		);

		$selectable = $this->registry->get_selectable_codes();
		$validation = $this->validator->validate( $rules, $selectable, $enabled );

		if ( ! $validation->is_valid() ) {
			foreach ( $validation->errors() as $error ) {
				\WC_Admin_Settings::add_error( $error );
			}

			return null;
		}

		return array(
			'geo'      => GeoDetectionSettings::sanitize_raw( $geo_array ),
			'warnings' => $validation->warnings(),
		);
	}

	/**
	 * Reconstructs posted rules in submission order.
	 *
	 * @param array<string, mixed> $raw Raw umc_geo POST.
	 * @return list<array<string, mixed>>
	 */
	private function ordered_rules_from_post( array $raw ): array {
		$rows_by_id = array();

		if ( isset( $raw['rules'] ) && is_array( $raw['rules'] ) ) {
			foreach ( $raw['rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$id = isset( $row['id'] ) ? strtolower( sanitize_text_field( (string) $row['id'] ) ) : '';

				if ( '' === $id ) {
					continue;
				}

				$rows_by_id[ $id ] = $row;
			}
		}

		$order = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['umc_geo_rules_order'] ) && is_array( $_POST['umc_geo_rules_order'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( wp_unslash( $_POST['umc_geo_rules_order'] ) as $id ) {
				$id = strtolower( sanitize_text_field( (string) $id ) );

				if ( isset( $rows_by_id[ $id ] ) ) {
					$order[] = $rows_by_id[ $id ];
					unset( $rows_by_id[ $id ] );
				}
			}
		}

		foreach ( $rows_by_id as $row ) {
			$order[] = $row;
		}

		return $order;
	}
}
