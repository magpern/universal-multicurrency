<?php
/**
 * Currency identity exposed on the Store API cart endpoint.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\StoreApi;

use UMC\CurrencyContext;

/**
 * Publishes the plugin's currency state alongside the Store API cart response.
 *
 * Every converted amount and the whole `currency_*` identity already reach
 * clients through WooCommerce's own fields, so no monetary value is duplicated
 * here. What core cannot express is which currency the shopper is in and which
 * ones they may choose, which is what a client needs to render a selector
 * without a second request.
 *
 * Nothing beyond that is published. The exchange rate and the internal cache
 * identity derived from it are implementation details, not part of the contract
 * a client should be able to depend on, so they stay server-side.
 *
 * No update callback is registered. Switching currency reloads the page today,
 * so there is nothing for a client to POST; when an in-place switch is added it
 * belongs on `POST /cart/extensions` with this same namespace.
 */
final class CartExtensionData {

	/**
	 * Extension namespace, matching the plugin prefix.
	 */
	public const NAMESPACE_KEY = 'umc';

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the extension to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the cart endpoint extension, when the Store API is available.
	 */
	public function register(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart',
				'namespace'       => self::NAMESPACE_KEY,
				'data_callback'   => array( $this, 'data' ),
				'schema_callback' => array( $this, 'schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * The currency state for the current request.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {
		return array(
			'active_currency'       => $this->context->get_active_code(),
			'base_currency'         => $this->context->get_base_currency()->code(),
			'selectable_currencies' => $this->context->get_selectable_codes(),
		);
	}

	/**
	 * Schema for the exposed currency state.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		return array(
			'active_currency'       => array(
				'description' => __( 'Currency code the cart is presented in.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'base_currency'         => array(
				'description' => __( 'Currency code prices are authored in.', 'universal-multicurrency' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'selectable_currencies' => array(
				'description' => __( 'Currency codes the shopper may choose.', 'universal-multicurrency' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'readonly'    => true,
			),
		);
	}
}
