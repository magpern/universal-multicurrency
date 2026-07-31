<?php
/**
 * Reports the health of the optional Universal Geo Context integration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Single source of truth for Universal Geo Context availability.
 *
 * {@see UniversalGeoContextAdapter::is_available()} delegates here so the
 * storefront provider chain and every admin/diagnostics surface read the
 * same answer. Universal Geo Context exposes no version constant for its
 * public API, only `universal_geo_api_version()`; this class is the only
 * place that number is compared against the minimum this plugin requires.
 */
final class UgcIntegrationStatus {

	public const STATE_AVAILABLE = 'available';

	public const STATE_MISSING = 'missing';

	public const STATE_MISCONFIGURED = 'misconfigured';

	/**
	 * Minimum Universal Geo Context public API version this plugin consumes.
	 */
	private const MIN_API_VERSION = 1;

	/**
	 * Documented-stable Universal Geo Context admin page slugs.
	 *
	 * These are a soft contract (not part of Universal Geo Context's frozen
	 * public API), so links built from them must always be capability-gated
	 * and tolerate Universal Geo Context being inactive.
	 *
	 * @var array<string, string>
	 */
	private const SLUGS = array(
		'overview'        => 'universal-geo-context',
		'detection'       => 'universal-geo-context-detection',
		'providers'       => 'universal-geo-context-providers',
		'trusted_proxies' => 'universal-geo-context-trusted-proxies',
		'diagnostics'     => 'universal-geo-context-diagnostics',
		'settings'        => 'universal-geo-context-settings',
	);

	/**
	 * Integration state: available, missing, or misconfigured.
	 */
	public function state(): string {
		if ( ! function_exists( 'universal_geo_get_country_code' ) || ! function_exists( 'universal_geo_api_version' ) ) {
			return self::STATE_MISSING;
		}

		if ( universal_geo_api_version() < self::MIN_API_VERSION ) {
			return self::STATE_MISCONFIGURED;
		}

		return self::STATE_AVAILABLE;
	}

	/**
	 * Whether Universal Geo Context is installed and speaks a compatible API.
	 */
	public function is_available(): bool {
		return self::STATE_AVAILABLE === $this->state();
	}

	/**
	 * Universal Geo Context plugin version, for display only — never for gating.
	 */
	public function version(): ?string {
		return defined( 'UNIVERSAL_GEO_VERSION' ) ? (string) UNIVERSAL_GEO_VERSION : null;
	}

	/**
	 * Reported Universal Geo Context public API version, or null when absent.
	 */
	public function api_version(): ?int {
		return function_exists( 'universal_geo_api_version' ) ? (int) universal_geo_api_version() : null;
	}

	/**
	 * Whether Universal Geo Context is currently simulating a country for this request.
	 */
	public function is_simulating(): bool {
		if ( ! $this->is_available() || ! function_exists( 'universal_geo_get_source' ) ) {
			return false;
		}

		return 'simulation' === (string) universal_geo_get_source();
	}

	/**
	 * Deep link to the Universal Geo Context Overview page.
	 */
	public function overview_url(): string {
		return $this->page_url( self::SLUGS['overview'] );
	}

	/**
	 * Deep link to the Universal Geo Context Detection Inspector.
	 */
	public function detection_url(): string {
		return $this->page_url( self::SLUGS['detection'] );
	}

	/**
	 * Deep link to the Universal Geo Context country simulation tab.
	 */
	public function simulation_url(): string {
		return $this->page_url( self::SLUGS['detection'] ) . '&tab=simulation';
	}

	/**
	 * Deep link to the Universal Geo Context Providers page.
	 */
	public function providers_url(): string {
		return $this->page_url( self::SLUGS['providers'] );
	}

	/**
	 * Deep link to the Universal Geo Context Trusted Proxies page.
	 */
	public function trusted_proxies_url(): string {
		return $this->page_url( self::SLUGS['trusted_proxies'] );
	}

	/**
	 * Deep link to the Universal Geo Context Diagnostics page.
	 */
	public function diagnostics_url(): string {
		return $this->page_url( self::SLUGS['diagnostics'] );
	}

	/**
	 * Deep link to the Universal Geo Context Settings page.
	 */
	public function settings_url(): string {
		return $this->page_url( self::SLUGS['settings'] );
	}

	/**
	 * Builds a Universal Geo Context admin page URL from a stable page slug.
	 *
	 * @param string $slug Universal Geo Context admin page slug.
	 */
	private function page_url( string $slug ): string {
		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
	}
}
