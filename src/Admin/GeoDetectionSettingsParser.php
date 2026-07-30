<?php
/**
 * Geo Detection settings POST parser.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
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
		$current = GeoDetectionSettings::from_array( $this->settings->get()['geo'] ?? array() );
		$panel   = $this->submitted_panel();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		if ( ! isset( $_POST['umc_geo'] ) || ! is_array( $_POST['umc_geo'] ) ) {
			if ( GeoPanelRegistry::PANEL_DETECTION === $panel ) {
				return array(
					'geo'      => GeoDetectionSettings::sanitize_raw(
						array_merge(
							$current->to_array(),
							array( 'rules' => array() )
						)
					),
					'warnings' => array(),
				);
			}

			return array(
				'geo'      => $current->to_array(),
				'warnings' => array(),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = wp_unslash( $_POST['umc_geo'] );

		if ( GeoPanelRegistry::PANEL_DETECTION === $panel ) {
			return $this->parse_detection_panel( $current, $raw );
		}

		if ( GeoPanelRegistry::PANEL_SETTINGS === $panel ) {
			return $this->parse_settings_panel( $current, $raw );
		}

		return $this->parse_full_submission( $raw );
	}

	/**
	 * Parses routing rules only and merges with current geo settings.
	 *
	 * @param GeoDetectionSettings $current Current settings.
	 * @param array<string, mixed> $raw     Raw umc_geo POST.
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null
	 */
	private function parse_detection_panel( GeoDetectionSettings $current, array $raw ): ?array {
		$rules = $this->parse_rules( $raw );

		$geo_array          = $current->to_array();
		$geo_array['rules'] = array_map(
			static fn( GeoRoutingRule $rule ): array => $rule->to_array(),
			$rules
		);

		return $this->validate_and_return( $geo_array, $rules, ! empty( $geo_array['enabled'] ) );
	}

	/**
	 * Parses operational settings and merges with current routing rules.
	 *
	 * @param GeoDetectionSettings $current Current settings.
	 * @param array<string, mixed> $raw     Raw umc_geo POST.
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null
	 */
	private function parse_settings_panel( GeoDetectionSettings $current, array $raw ): ?array {
		$checkout_raw = is_array( $raw['checkout'] ?? null ) ? $raw['checkout'] : array();

		$geo_array                                  = $current->to_array();
		$geo_array['enabled']                       = ! empty( $raw['enabled'] );
		$geo_array['mode']                          = GeoDetectionSettings::sanitize_mode( $raw['mode'] ?? GeoDetectionSettings::MODE_FIRST_VISIT );
		$geo_array['fallback_currency']             = GeoDetectionSettings::sanitize_currency_code( $raw['fallback_currency'] ?? '' );
		$geo_array['allow_wc_geolocation_fallback'] = ! isset( $raw['allow_wc_geolocation_fallback'] ) || (bool) $raw['allow_wc_geolocation_fallback'];
		$geo_array['checkout']                      = array(
			'lock_on_entry'                 => ! isset( $checkout_raw['lock_on_entry'] ) || (bool) $checkout_raw['lock_on_entry'],
			'reevaluate_on_billing_change'  => ! empty( $checkout_raw['reevaluate_on_billing_change'] ),
			'reevaluate_on_shipping_change' => ! empty( $checkout_raw['reevaluate_on_shipping_change'] ),
			'country_precedence'            => GeoDetectionSettings::sanitize_precedence( $checkout_raw['country_precedence'] ?? GeoDetectionSettings::PRECEDENCE_BILLING ),
		);

		$rules = array_map(
			static fn( array $row ): ?GeoRoutingRule => GeoRoutingRule::from_array( $row ),
			$geo_array['rules']
		);

		$rules = array_values(
			array_filter(
				$rules,
				static fn( $rule ): bool => $rule instanceof GeoRoutingRule
			)
		);

		return $this->validate_and_return( $geo_array, $rules, ! empty( $geo_array['enabled'] ) );
	}

	/**
	 * Parses a full geo submission (legacy / unknown panel).
	 *
	 * @param array<string, mixed> $raw Raw umc_geo POST.
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null
	 */
	private function parse_full_submission( array $raw ): ?array {
		$rules        = $this->parse_rules( $raw );
		$enabled      = ! empty( $raw['enabled'] );
		$mode         = GeoDetectionSettings::sanitize_mode( $raw['mode'] ?? GeoDetectionSettings::MODE_FIRST_VISIT );
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

		return $this->validate_and_return( $geo_array, $rules, $enabled );
	}

	/**
	 * Validates rules and returns sanitized geo settings.
	 *
	 * @param array<string, mixed>       $geo_array Geo settings array.
	 * @param array<int, GeoRoutingRule> $rules     Parsed rules.
	 * @param bool                       $enabled   Whether geo is enabled.
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null
	 */
	private function validate_and_return( array $geo_array, array $rules, bool $enabled ): ?array {
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
	 * Parses routing rules from POST.
	 *
	 * @param array<string, mixed> $raw Raw umc_geo POST.
	 * @return list<GeoRoutingRule>
	 */
	private function parse_rules( array $raw ): array {
		$rules_raw = $this->ordered_rules_from_post( $raw );
		$rules     = array();

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

		return $rules;
	}

	/**
	 * Reads the submitted geo panel id.
	 */
	private function submitted_panel(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		$panel = isset( $_POST['umc_geo_panel'] ) ? sanitize_key( wp_unslash( (string) $_POST['umc_geo_panel'] ) ) : '';

		if ( in_array( $panel, GeoPanelRegistry::panel_ids(), true ) ) {
			return $panel;
		}

		return GeoPanelRegistry::PANEL_OVERVIEW;
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
