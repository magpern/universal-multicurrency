<?php
/**
 * Wires the M20 product pricing graph for integration tests.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\Integration\PriceConversionService;
use UMC\Integration\PriceHooks;
use UMC\Order\LineItemPriceProvenance;
use UMC\Pricing\FixedPriceRepository;
use UMC\Pricing\ProductPriceProvenanceRegistry;
use UMC\Pricing\ProductPriceResolutionService;
use UMC\Pricing\ProductSaleStateResolver;

/**
 * Registers {@see PriceHooks} with the full fixed-price resolution stack.
 */
final class ProductPricingTestGraph {

	/**
	 * @param CurrencyContext           $context  Active currency facade.
	 * @param CurrencyRegistry          $registry Currency registry.
	 * @param FixedPriceRepository|null $repository Optional shared repository.
	 * @return ProductPriceProvenanceRegistry Request-scoped provenance map.
	 */
	public static function register(
		CurrencyContext $context,
		CurrencyRegistry $registry,
		?FixedPriceRepository $repository = null
	): ProductPriceProvenanceRegistry {
		$repository          = $repository ?? new FixedPriceRepository( $registry->get_base_code() );
		$service             = new PriceConversionService( $context );
		$provenance_registry = new ProductPriceProvenanceRegistry();
		$resolution          = new ProductPriceResolutionService(
			$repository,
			new ProductSaleStateResolver(),
			$service,
			$context,
			$registry,
			$provenance_registry
		);

		( new PriceHooks( $resolution, $context ) )->register();
		( new LineItemPriceProvenance( $provenance_registry ) )->register();

		return $provenance_registry;
	}
}
