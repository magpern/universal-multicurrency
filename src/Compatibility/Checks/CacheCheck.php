<?php
/**
 * Cache compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\Registry\CachePluginRegistry;
use UMC\Compatibility\Registry\IntegrationRegistry;
use UMC\CurrencyContext;

/**
 * Distinguishes object cache from full-page cache and provides guidance.
 */
final class CacheCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$results = array();

		if ( 'external' === ( $inventory->facts()['object_cache'] ?? '' ) ) {
			$results[] = new CompatibilityResult(
				'cache.object_dropin',
				CompatibilityCategory::CACHE,
				CompatibilitySeverity::INFO,
				__( 'External object cache detected', 'universal-multicurrency' ),
				__( 'An external object cache is active. Object caching is usually safe for Universal Multicurrency.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		foreach ( CachePluginRegistry::definitions() as $definition ) {
			$match = IntegrationRegistry::detect(
				$definition,
				$inventory->plugins(),
				$inventory->active_plugins()
			);

			if ( ! $match['installed'] || ! $match['active'] ) {
				continue;
			}

			$type     = (string) ( $definition['type'] ?? '' );
			$severity = CachePluginRegistry::TYPE_OBJECT === $type
				? CompatibilitySeverity::INFO
				: CompatibilitySeverity::WARNING;

			$results[] = new CompatibilityResult(
				'cache.plugin.' . (string) $definition['id'],
				CompatibilityCategory::CACHE,
				$severity,
				(string) $definition['label'],
				$this->summary_for_type( $type, (string) $definition['label'] ),
				CompatibilityDeterminism::HEURISTIC,
				array(
					'type'         => $type,
					'status_label' => (string) ( $definition['status_label'] ?? '' ),
					'version'      => (string) $match['version'],
				),
				array(),
				$this->guidance_for_type( $type )
			);
		}

		if ( array() === $results ) {
			$results[] = new CompatibilityResult(
				'cache.none_detected',
				CompatibilityCategory::CACHE,
				CompatibilitySeverity::INFO,
				__( 'No page-cache plugin detected from WordPress', 'universal-multicurrency' ),
				__( 'No supported page-cache plugin was detected. Server-level or edge cache configuration cannot be verified from WordPress alone.', 'universal-multicurrency' ),
				CompatibilityDeterminism::HEURISTIC
			);
		} else {
			$results[] = new CompatibilityResult(
				'cache.edge_note',
				CompatibilityCategory::CACHE,
				CompatibilitySeverity::INFO,
				__( 'Server and edge cache may still be present', 'universal-multicurrency' ),
				__( 'Reverse proxies, CDN edge caches, and web-server caches cannot always be detected from WordPress.', 'universal-multicurrency' ),
				CompatibilityDeterminism::FACT
			);
		}

		return $results;
	}

	/**
	 * Summary text for one cache type.
	 *
	 * @param string $type  Cache type.
	 * @param string $label Plugin label.
	 */
	private function summary_for_type( string $type, string $label ): string {
		if ( CachePluginRegistry::TYPE_OBJECT === $type ) {
			return sprintf(
				/* translators: %s: cache plugin name */
				__( '%s is active. Object caching is usually safe.', 'universal-multicurrency' ),
				$label
			);
		}

		return sprintf(
			/* translators: %s: cache plugin name */
			__( '%s is active. Full-page caching may serve the wrong currency to first-time visitors or when currency selection changes unless cache is configured appropriately.', 'universal-multicurrency' ),
			$label
		);
	}

	/**
	 * Guidance lines for one cache type.
	 *
	 * @param string $type Cache type.
	 * @return array<int, string>
	 */
	private function guidance_for_type( string $type ): array {
		if ( CachePluginRegistry::TYPE_OBJECT === $type ) {
			return array();
		}

		return array(
			sprintf(
				/* translators: %s: cookie name used by Universal Multicurrency */
				__( 'Vary or bypass page cache when currency selection changes via the switcher (%1$s cookie, WooCommerce session key %2$s, or explicit currency query requests).', 'universal-multicurrency' ),
				CurrencyContext::COOKIE_NAME,
				CurrencyContext::SESSION_KEY
			),
			__( 'If Visitor Location (first-visit country-based detection) is enabled, exclude landing and category pages from full-page cache or configure cache to vary on the currency cookie, so new visitors do not see a cached page from a different country.', 'universal-multicurrency' ),
			__( 'Exclude cart, checkout, and account pages from full-page cache when prices must reflect the selected currency.', 'universal-multicurrency' ),
			__( 'Confirm AJAX or fragment behavior for the currency switcher under cache.', 'universal-multicurrency' ),
		);
	}
}
