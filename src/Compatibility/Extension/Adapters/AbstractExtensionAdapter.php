<?php
/**
 * Abstract base for extension compatibility adapters.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension\Adapters;

use UMC\Compatibility\Extension\ExtensionCompatibilityAdapterInterface;
use UMC\CurrencyContext;
use UMC\Integration\PriceConversionService;

/**
 * Shared dependencies for extension adapters.
 */
abstract class AbstractExtensionAdapter implements ExtensionCompatibilityAdapterInterface {

	/**
	 * Conversion seam.
	 *
	 * @var PriceConversionService
	 */
	protected PriceConversionService $service;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	protected CurrencyContext $context;

	/**
	 * Binds shared adapter dependencies.
	 *
	 * @param PriceConversionService $service Conversion seam.
	 * @param CurrencyContext        $context Request-scoped currency facade.
	 */
	public function __construct( PriceConversionService $service, CurrencyContext $context ) {
		$this->service = $service;
		$this->context = $context;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		return true;
	}

	/**
	 * Converts a base-authored amount once when convertible.
	 *
	 * @param mixed $amount Base-authored amount.
	 * @return mixed
	 */
	protected function convert_amount( $amount ) {
		if ( ! $this->context->is_convertible_request() || $this->context->is_base_active() ) {
			return $amount;
		}

		return $this->service->convert( $amount );
	}
}
