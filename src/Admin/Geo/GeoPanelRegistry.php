<?php
/**
 * Geo Detection hub panel registry.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

/**
 * Canonical Geo hub panel identifiers and helpers.
 */
final class GeoPanelRegistry {

	public const SECTION = 'geo_detection';

	public const PANEL_OVERVIEW = 'overview';

	public const PANEL_DETECTION = 'detection';

	public const PANEL_SANDBOX = 'sandbox';

	public const PANEL_PROVIDERS = 'providers';

	public const PANEL_PROXIES = 'proxies';

	public const PANEL_DIAGNOSTICS = 'diagnostics';

	public const PANEL_SETTINGS = 'settings';

	public const QUERY_VAR = 'geo_panel';

	/**
	 * All valid panel ids in display order.
	 *
	 * @return list<string>
	 */
	public static function panel_ids(): array {
		return array(
			self::PANEL_OVERVIEW,
			self::PANEL_DETECTION,
			self::PANEL_SANDBOX,
			self::PANEL_PROVIDERS,
			self::PANEL_PROXIES,
			self::PANEL_DIAGNOSTICS,
			self::PANEL_SETTINGS,
		);
	}

	/**
	 * Returns the active panel from the current request.
	 */
	public static function active_panel(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
		$panel = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ) : '';

		if ( in_array( $panel, self::panel_ids(), true ) ) {
			return $panel;
		}

		return self::PANEL_OVERVIEW;
	}

	/**
	 * Whether a panel exposes WooCommerce settings save controls.
	 *
	 * @param string $panel Panel id.
	 */
	public static function is_saveable_panel( string $panel ): bool {
		return in_array( $panel, array( self::PANEL_DETECTION, self::PANEL_SETTINGS ), true );
	}

	/**
	 * Localized label for a panel.
	 *
	 * @param string $panel Panel id.
	 */
	public static function label( string $panel ): string {
		return match ( $panel ) {
			self::PANEL_OVERVIEW    => __( 'Overview', 'universal-multicurrency' ),
			self::PANEL_DETECTION   => __( 'Detection', 'universal-multicurrency' ),
			self::PANEL_SANDBOX     => __( 'Geo Sandbox', 'universal-multicurrency' ),
			self::PANEL_PROVIDERS   => __( 'Providers', 'universal-multicurrency' ),
			self::PANEL_PROXIES     => __( 'Trusted Proxies', 'universal-multicurrency' ),
			self::PANEL_DIAGNOSTICS => __( 'Diagnostics', 'universal-multicurrency' ),
			self::PANEL_SETTINGS    => __( 'Settings', 'universal-multicurrency' ),
			default                 => __( 'Overview', 'universal-multicurrency' ),
		};
	}

	/**
	 * Admin URL for a Geo hub panel.
	 *
	 * @param string $panel Panel id.
	 */
	public static function panel_url( string $panel ): string {
		return add_query_arg(
			array(
				'page'          => 'wc-settings',
				'tab'           => 'umc',
				'section'       => self::SECTION,
				self::QUERY_VAR => $panel,
			),
			admin_url( 'admin.php' )
		);
	}
}
