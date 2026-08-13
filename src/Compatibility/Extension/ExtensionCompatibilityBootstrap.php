<?php
/**
 * Boots extension compatibility adapters when extensions are active.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

use UMC\Compatibility\Extension\Adapters\BundlesAdapter;
use UMC\Compatibility\Extension\Adapters\ProductAddonsAdapter;
use UMC\Compatibility\Extension\Adapters\SubscriptionsAdapter;
use UMC\CurrencyContext;
use UMC\Integration\PriceConversionService;
use UMC\Order\OrderCurrencyContext;

/**
 * Wires M19 extension adapters with injected UMC services.
 */
final class ExtensionCompatibilityBootstrap {

	/**
	 * Conversion seam.
	 *
	 * @var PriceConversionService
	 */
	private PriceConversionService $service;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Order currency context for renewal surfaces.
	 *
	 * @var OrderCurrencyContext|null
	 */
	private ?OrderCurrencyContext $order_context;

	/**
	 * Creates the bootstrap with injected UMC services.
	 *
	 * @param PriceConversionService    $service       Conversion seam.
	 * @param CurrencyContext           $context       Request-scoped currency.
	 * @param OrderCurrencyContext|null $order_context Order currency context.
	 */
	public function __construct(
		PriceConversionService $service,
		CurrencyContext $context,
		?OrderCurrencyContext $order_context = null
	) {
		$this->service       = $service;
		$this->context       = $context;
		$this->order_context = $order_context;
	}

	/**
	 * Registers active extension adapters on woocommerce_init.
	 */
	public function register(): void {
		add_action(
			'woocommerce_init',
			function (): void {
				$this->register_active_adapters();
			},
			20
		);
	}

	/**
	 * Registers adapters for detected active extensions.
	 */
	private function register_active_adapters(): void {
		$plugins = ExtensionRuntimeInventory::installed_plugins();
		$active  = ExtensionRuntimeInventory::active_plugins();

		foreach ( ExtensionCompatibilityRegistry::definitions() as $definition ) {
			$detection = ExtensionDetector::detect( $definition, $plugins, $active );
			if ( ! $detection['active'] && ! $this->is_test_double_active( (string) ( $definition['id'] ?? '' ) ) ) {
				continue;
			}

			$adapter = $this->create_adapter( (string) ( $definition['adapter_class'] ?? '' ) );
			if ( null !== $adapter ) {
				$adapter->register();
			}
		}
	}

	/**
	 * Creates an adapter instance with injected dependencies.
	 *
	 * @param string $adapter_class Adapter class name.
	 */
	private function create_adapter( string $adapter_class ): ?ExtensionCompatibilityAdapterInterface {
		if ( '' === $adapter_class ) {
			return null;
		}

		return match ( $adapter_class ) {
			SubscriptionsAdapter::class => new SubscriptionsAdapter(
				$this->service,
				$this->context,
				$this->order_context
			),
			ProductAddonsAdapter::class => new ProductAddonsAdapter( $this->service, $this->context ),
			BundlesAdapter::class => new BundlesAdapter( $this->service, $this->context ),
			default => null,
		};
	}

	/**
	 * Whether a UMC test-double plugin is active for an extension id.
	 *
	 * @param string $extension_id Extension registry id.
	 */
	private function is_test_double_active( string $extension_id ): bool {
		if ( ! defined( 'UMC_TEST_EXTENSION_FIXTURES' ) || ! UMC_TEST_EXTENSION_FIXTURES ) {
			return false;
		}

		$map = array(
			'woocommerce_subscriptions'   => 'umc-test-extension-subscriptions/umc-test-extension-subscriptions.php',
			'woocommerce_product_addons'  => 'umc-test-extension-product-addons/umc-test-extension-product-addons.php',
			'woocommerce_product_bundles' => 'umc-test-extension-bundles/umc-test-extension-bundles.php',
		);

		$plugin_file = $map[ $extension_id ] ?? '';
		if ( '' === $plugin_file ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}
}
