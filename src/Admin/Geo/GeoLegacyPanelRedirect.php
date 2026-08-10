<?php
/**
 * Redirects retired Visitor Location panel URLs to their new home.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

/**
 * Keeps bookmarked admin URLs for the removed Providers, Trusted Proxies,
 * Diagnostics, and Settings panels working after M14 folded or removed them.
 *
 * Runs on `admin_init` — before WooCommerce renders the settings page body —
 * so a 302 is still possible. See ADR-0018 for the retirement schedule.
 */
final class GeoLegacyPanelRedirect {

	/**
	 * Registers the redirect hook.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Redirects a legacy `geo_panel` request to its replacement, once.
	 */
	public function maybe_redirect(): void {
		if ( ! $this->is_visitor_location_request() ) {
			return;
		}

		$requested = GeoPanelRegistry::raw_requested_panel();

		if ( ! GeoPanelRegistry::is_legacy_panel( $requested ) ) {
			return;
		}

		$target = GeoPanelRegistry::legacy_redirect_target( $requested );

		wp_safe_redirect(
			add_query_arg(
				GeoPanelRegistry::MOVED_QUERY_VAR,
				$requested,
				GeoPanelRegistry::panel_url( $target )
			)
		);
		exit;
	}

	/**
	 * Whether the current request targets the Visitor Location section.
	 */
	private function is_visitor_location_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
		if ( ! isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
		return 'wc-settings' === sanitize_key( wp_unslash( (string) $_GET['page'] ) )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
			&& 'umc' === sanitize_key( wp_unslash( (string) $_GET['tab'] ) )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
			&& GeoPanelRegistry::SECTION === sanitize_key( wp_unslash( (string) $_GET['section'] ) );
	}
}
