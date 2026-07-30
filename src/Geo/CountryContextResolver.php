<?php
/**
 * Country context provider chain.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Prefers Universal Geo Context, then WooCommerce fallback.
 */
final class CountryContextResolver {

	/**
	 * Ordered country context providers.
	 *
	 * @var list<CountryContextProviderInterface>
	 */
	private array $providers;

	/**
	 * Constructs the provider chain.
	 *
	 * @param array<int, CountryContextProviderInterface> $providers Ordered providers.
	 */
	public function __construct( array $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Resolves the best available country context.
	 */
	public function resolve(): CountryContext {
		foreach ( $this->providers as $provider ) {
			if ( ! $provider->is_available() ) {
				continue;
			}

			$context = $provider->resolve();

			if ( null !== $context && $context->is_known() ) {
				/**
				 * Fires after country context is resolved for geo routing.
				 *
				 * @since 0.11.0
				 *
				 * @param CountryContext $context Resolved context.
				 */
				do_action( 'umc_geo_country_resolved', $context );

				return $context;
			}
		}

		return CountryContext::unknown();
	}
}
