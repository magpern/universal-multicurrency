<?php
/**
 * Central catalog of third-party extension compatibility definitions.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

use UMC\Compatibility\Extension\Adapters\BundlesAdapter;
use UMC\Compatibility\Extension\Adapters\ProductAddonsAdapter;
use UMC\Compatibility\Extension\Adapters\SubscriptionsAdapter;

/**
 * Static registry of extension metadata, evidence, and adapter resolution.
 */
final class ExtensionCompatibilityRegistry {

	/**
	 * Memoized runtime records for the current request.
	 *
	 * @var array<string, ExtensionCompatibilityRecord>|null
	 */
	private static ?array $records = null;

	/**
	 * Returns all extension definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions(): array {
		/**
		 * Filters extension compatibility definitions.
		 *
		 * @since 0.18.0
		 *
		 * @param array<int, array<string, mixed>> $definitions Extension definitions.
		 */
		return (array) apply_filters( 'umc_extension_compatibilities', self::built_in_definitions() );
	}

	/**
	 * Built-in extension catalog.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function built_in_definitions(): array {
		return array(
			array(
				'id'             => 'woocommerce_subscriptions',
				'label'          => 'WooCommerce Subscriptions',
				'status'         => ExtensionCompatibilityStatus::CHARACTERIZED,
				'evidence_tier'  => ExtensionEvidenceTier::E2,
				'tested_through' => '',
				'adapter_class'  => SubscriptionsAdapter::class,
				'surfaces'       => array(
					'product_price',
					'signup_fee',
					'recurring_total',
					'initial_order',
					'renewal_order',
					'manual_renewal',
					'automatic_renewal',
				),
				'limitations'    => array(
					'Integrated status requires E3 real-extension validation.',
					'Rate policy on renewals characterized from public WCS docs; E3 pending.',
				),
				'evidence_tests' => array(
					'UMC\\Tests\\Unit\\Compatibility\\Extension\\ExtensionEvidenceTest',
					'UMC\\Tests\\Unit\\Compatibility\\Extension\\SubscriptionsContractTest',
					'UMC\\Tests\\Integration\\Compatibility\\SubscriptionsAdapterTest',
				),
				'signatures'     => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-subscriptions/woocommerce-subscriptions.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Subscriptions',
					),
					array(
						'type'   => 'constant',
						'needle' => 'WCS_INIT_PLUGIN_FILE',
					),
				),
			),
			array(
				'id'             => 'woocommerce_product_addons',
				'label'          => 'Product Add-Ons',
				'status'         => ExtensionCompatibilityStatus::CHARACTERIZED,
				'evidence_tier'  => ExtensionEvidenceTier::E2,
				'tested_through' => '',
				'adapter_class'  => ProductAddonsAdapter::class,
				'surfaces'       => array(
					'flat_fee_addon',
					'quantity_addon',
					'percentage_addon',
					'cart',
					'checkout',
				),
				'limitations'    => array(
					'Hook contract from public Product Add-Ons documentation.',
					'Integrated status requires E3 real-extension validation.',
				),
				'evidence_tests' => array(
					'UMC\\Tests\\Unit\\Compatibility\\Extension\\ProductAddonsContractTest',
					'UMC\\Tests\\Integration\\Compatibility\\ProductAddonsAdapterTest',
				),
				'signatures'     => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-product-addons/woocommerce-product-addons.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Product_Addons',
					),
				),
			),
			array(
				'id'             => 'woocommerce_product_bundles',
				'label'          => 'Product Bundles',
				'status'         => ExtensionCompatibilityStatus::CHARACTERIZED,
				'evidence_tier'  => ExtensionEvidenceTier::E2,
				'tested_through' => '',
				'adapter_class'  => BundlesAdapter::class,
				'surfaces'       => array(
					'parent_price',
					'child_price',
					'bundled_discount',
					'cart_line_total',
				),
				'limitations'    => array(
					'Composite Products not adapted in M19.',
					'Integrated status requires E3 real-extension validation.',
				),
				'evidence_tests' => array(
					'UMC\\Tests\\Integration\\Compatibility\\BundlesAdapterTest',
				),
				'signatures'     => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-product-bundles/woocommerce-product-bundles.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Bundles',
					),
					array(
						'type'   => 'version_constant',
						'needle' => 'WC_PB_VERSION',
					),
				),
			),
			array(
				'id'             => 'woocommerce_composite_products',
				'label'          => 'Composite Products',
				'status'         => ExtensionCompatibilityStatus::NOT_EVALUATED,
				'evidence_tier'  => ExtensionEvidenceTier::E0,
				'tested_through' => '',
				'adapter_class'  => '',
				'surfaces'       => array(),
				'limitations'    => array(
					'M19 investigation deferred; Bundles adapter chosen as bounded integration.',
					'Parent/child price ownership requires E3 evidence before adapter.',
				),
				'evidence_tests' => array(),
				'signatures'     => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-composite-products/woocommerce-composite-products.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Composite_Products',
					),
					array(
						'type'   => 'version_constant',
						'needle' => 'WC_CP_VERSION',
					),
				),
			),
			array(
				'id'             => 'woocommerce_bookings',
				'label'          => 'WooCommerce Bookings',
				'status'         => ExtensionCompatibilityStatus::NOT_EVALUATED,
				'evidence_tier'  => ExtensionEvidenceTier::E0,
				'tested_through' => '',
				'adapter_class'  => '',
				'surfaces'       => array(),
				'limitations'    => array(
					'M19 audit-only: calculated pricing surfaces deferred to future milestone.',
				),
				'evidence_tests' => array(),
				'signatures'     => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'woocommerce-bookings/woocommerce-bookings.php',
					),
					array(
						'type'   => 'class',
						'needle' => 'WC_Bookings',
					),
				),
			),
		);
	}

	/**
	 * Resolves compatibility records for all definitions (memoized per request).
	 *
	 * @param array<string, mixed> $plugins Installed plugins.
	 * @param array<int, string>   $active  Active plugin paths.
	 * @return array<string, ExtensionCompatibilityRecord>
	 */
	public static function records( array $plugins, array $active ): array {
		if ( null !== self::$records ) {
			return self::$records;
		}

		self::$records = array();

		foreach ( self::definitions() as $definition ) {
			$id = (string) ( $definition['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}

			$detection = ExtensionDetector::detect( $definition, $plugins, $active );

			self::$records[ $id ] = new ExtensionCompatibilityRecord(
				$id,
				(string) ( $definition['label'] ?? $id ),
				(string) ( $definition['status'] ?? ExtensionCompatibilityStatus::NOT_EVALUATED ),
				(string) ( $definition['evidence_tier'] ?? ExtensionEvidenceTier::E0 ),
				(string) ( $definition['tested_through'] ?? '' ),
				$detection['version'],
				$detection['installed'],
				$detection['active'],
				self::is_adapter_active( $definition, $detection['active'] ),
				(array) ( $definition['surfaces'] ?? array() ),
				(array) ( $definition['limitations'] ?? array() ),
				(string) ( $definition['adapter_class'] ?? '' ),
				(array) ( $definition['evidence_tests'] ?? array() ),
				$detection['plugin_file']
			);
		}

		return self::$records;
	}

	/**
	 * Returns one record by id.
	 *
	 * @param string               $id      Extension id.
	 * @param array<string, mixed> $plugins Installed plugins.
	 * @param array<int, string>   $active  Active plugin paths.
	 */
	public static function record( string $id, array $plugins, array $active ): ?ExtensionCompatibilityRecord {
		$records = self::records( $plugins, $active );

		return $records[ $id ] ?? null;
	}

	/**
	 * Clears memoized records (testing).
	 */
	public static function reset(): void {
		self::$records = null;
		ExtensionCompatibilityContext::reset();
	}

	/**
	 * Whether an adapter should be considered active for diagnostics.
	 *
	 * @param array<string, mixed> $definition Extension definition.
	 * @param bool                 $active     Whether extension is active.
	 */
	private static function is_adapter_active( array $definition, bool $active ): bool {
		if ( ! $active ) {
			return false;
		}

		$class = (string) ( $definition['adapter_class'] ?? '' );

		return '' !== $class;
	}
}
