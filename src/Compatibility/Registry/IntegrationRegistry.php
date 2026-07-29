<?php
/**
 * Central integration detection definitions for the Compatibility center.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Registry;

/**
 * Conservative integration metadata and detection signatures.
 */
final class IntegrationRegistry {

	/**
	 * Integration groups.
	 */
	public const GROUP_CURRENCY_SWITCHER = 'currency_switcher';

	public const GROUP_MULTILINGUAL = 'multilingual';

	public const GROUP_WOO_EXTENSION = 'woocommerce_extension';

	public const GROUP_GATEWAY = 'gateway';

	public const GROUP_PAGE_BUILDER = 'page_builder';

	public const GROUP_OBJECT_CACHE = 'object_cache';

	/**
	 * Returns integration definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions(): array {
		return array(
			array(
				'id'           => 'aelia',
				'label'        => 'Aelia Currency Switcher',
				'group'        => self::GROUP_CURRENCY_SWITCHER,
				'status_label' => 'Untested',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-aelia-currency-switcher/woocommerce-aelia-currency-switcher.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Aelia_CurrencySwitcher',
					),
				),
			),
			array(
				'id'           => 'wpml',
				'label'        => 'WPML',
				'group'        => self::GROUP_MULTILINGUAL,
				'status_label' => 'No known conflict',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'sitepress-multilingual-cms/sitepress.php',
					),
					array(
						'type'   => 'constant',
						'needle' => 'ICL_SITEPRESS_VERSION',
					),
				),
			),
			array(
				'id'           => 'polylang',
				'label'        => 'Polylang',
				'group'        => self::GROUP_MULTILINGUAL,
				'status_label' => 'Untested',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'polylang/polylang.php',
					),
					array(
						'type'   => 'function',
						'needle' => 'pll_current_language',
					),
				),
			),
			array(
				'id'           => 'woocommerce_subscriptions',
				'label'        => 'WooCommerce Subscriptions',
				'group'        => self::GROUP_WOO_EXTENSION,
				'status_label' => 'No known conflict',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-subscriptions/woocommerce-subscriptions.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Subscriptions',
					),
				),
			),
			array(
				'id'           => 'woocommerce_payments',
				'label'        => 'WooCommerce Payments',
				'group'        => self::GROUP_WOO_EXTENSION,
				'status_label' => 'Detected',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-payments/woocommerce-payments.php',
					),
				),
			),
			array(
				'id'           => 'stripe_gateway',
				'label'        => 'Stripe gateway',
				'group'        => self::GROUP_GATEWAY,
				'status_label' => 'Detected',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php',
					),
				),
			),
			array(
				'id'           => 'paypal_payments',
				'label'        => 'PayPal Payments',
				'group'        => self::GROUP_GATEWAY,
				'status_label' => 'Detected',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-paypal-payments/woocommerce-paypal-payments.php',
					),
				),
			),
			array(
				'id'           => 'elementor',
				'label'        => 'Elementor',
				'group'        => self::GROUP_PAGE_BUILDER,
				'status_label' => 'No known conflict',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'elementor/elementor.php',
					),
				),
			),
			array(
				'id'           => 'redis_object_cache',
				'label'        => 'Redis Object Cache',
				'group'        => self::GROUP_OBJECT_CACHE,
				'status_label' => 'Usually safe',
				'conflict'     => false,
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'redis-cache/redis-cache.php',
					),
				),
			),
		);
	}

	/**
	 * Detects whether an integration is installed and active.
	 *
	 * @param array<string, mixed> $definition Integration definition.
	 * @param array<string, mixed> $plugins    Installed plugins.
	 * @param array<int, string>   $active     Active plugin paths.
	 * @return array{installed: bool, active: bool, plugin_file: string, version: string}
	 */
	public static function detect( array $definition, array $plugins, array $active ): array {
		$plugin_file = self::match_plugin_file( $definition['signatures'] ?? array(), $plugins );

		if ( null === $plugin_file && self::match_runtime_signature( $definition['signatures'] ?? array() ) ) {
			return array(
				'installed'   => true,
				'active'      => true,
				'plugin_file' => '',
				'version'     => '',
			);
		}

		if ( null === $plugin_file ) {
			return array(
				'installed'   => false,
				'active'      => false,
				'plugin_file' => '',
				'version'     => '',
			);
		}

		return array(
			'installed'   => true,
			'active'      => in_array( $plugin_file, $active, true ),
			'plugin_file' => $plugin_file,
			'version'     => (string) ( $plugins[ $plugin_file ]['Version'] ?? '' ),
		);
	}

	/**
	 * Matches a plugin bootstrap file from signatures.
	 *
	 * @param array<int, array<string, string>> $signatures Signature definitions.
	 * @param array<string, mixed>              $plugins    Installed plugins.
	 */
	private static function match_plugin_file( array $signatures, array $plugins ): ?string {
		foreach ( $signatures as $signature ) {
			if ( 'plugin_path' !== ( $signature['type'] ?? '' ) ) {
				continue;
			}

			$needle = (string) ( $signature['needle'] ?? '' );
			if ( '' === $needle ) {
				continue;
			}

			if ( isset( $plugins[ $needle ] ) ) {
				return $needle;
			}

			foreach ( array_keys( $plugins ) as $plugin_file ) {
				if ( str_ends_with( $plugin_file, $needle ) ) {
					return $plugin_file;
				}
			}
		}

		return null;
	}

	/**
	 * Matches non-path runtime signatures.
	 *
	 * @param array<int, array<string, string>> $signatures Signature definitions.
	 */
	private static function match_runtime_signature( array $signatures ): bool {
		foreach ( $signatures as $signature ) {
			$type   = (string) ( $signature['type'] ?? '' );
			$needle = (string) ( $signature['needle'] ?? '' );

			if ( '' === $needle ) {
				continue;
			}

			switch ( $type ) {
				case 'class':
					if ( class_exists( $needle, false ) ) {
						return true;
					}
					break;
				case 'function':
					if ( function_exists( $needle ) ) {
						return true;
					}
					break;
				case 'constant':
					if ( defined( $needle ) ) {
						return true;
					}
					break;
			}
		}

		return false;
	}
}
