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
	 * Panel icon classes keyed by panel id.
	 *
	 * @var array<string, string>
	 */
	private const PANEL_ICONS = array(
		self::PANEL_OVERVIEW    => 'dashicons-dashboard',
		self::PANEL_DETECTION   => 'dashicons-location-alt',
		self::PANEL_SANDBOX     => 'dashicons-search',
		self::PANEL_PROVIDERS   => 'dashicons-admin-plugins',
		self::PANEL_PROXIES     => 'dashicons-shield',
		self::PANEL_DIAGNOSTICS => 'dashicons-heart',
		self::PANEL_SETTINGS    => 'dashicons-admin-generic',
	);

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
	 * Dashicon class for a panel pill.
	 *
	 * @param string $panel Panel id.
	 */
	public static function icon( string $panel ): string {
		return self::PANEL_ICONS[ $panel ] ?? 'dashicons-admin-generic';
	}

	/**
	 * Intro title for a panel page introduction block.
	 *
	 * @param string $panel Panel id.
	 */
	public static function intro_title( string $panel ): string {
		return match ( $panel ) {
			self::PANEL_OVERVIEW    => __( 'Visitor Location overview', 'universal-multicurrency' ),
			self::PANEL_DETECTION   => __( 'Detection rules', 'universal-multicurrency' ),
			self::PANEL_SANDBOX     => __( 'Geo Sandbox', 'universal-multicurrency' ),
			self::PANEL_PROVIDERS   => __( 'Detection providers', 'universal-multicurrency' ),
			self::PANEL_PROXIES     => __( 'Trusted proxies', 'universal-multicurrency' ),
			self::PANEL_DIAGNOSTICS => __( 'Visitor Location diagnostics', 'universal-multicurrency' ),
			self::PANEL_SETTINGS    => __( 'Visitor Location settings', 'universal-multicurrency' ),
			default                 => __( 'Visitor Location', 'universal-multicurrency' ),
		};
	}

	/**
	 * Intro description for a panel page introduction block.
	 *
	 * @param string $panel Panel id.
	 */
	public static function intro_description( string $panel ): string {
		return match ( $panel ) {
			self::PANEL_OVERVIEW    => __( 'Review current visitor location status and jump to common tasks.', 'universal-multicurrency' ),
			self::PANEL_DETECTION   => __( 'Configure how visitor location is detected before selecting an automatic currency.', 'universal-multicurrency' ),
			self::PANEL_SANDBOX     => __( 'Reproduce geographic context and inspect routing without changing storefront session, cookies, or active currency.', 'universal-multicurrency' ),
			self::PANEL_PROVIDERS   => __( 'Review which location providers are available to Universal Multicurrency.', 'universal-multicurrency' ),
			self::PANEL_PROXIES     => __( 'Trusted proxy configuration is managed by Universal Geo Context when installed.', 'universal-multicurrency' ),
			self::PANEL_DIAGNOSTICS => __( 'Inspect Geo Detection health signals and open related diagnostics tools.', 'universal-multicurrency' ),
			self::PANEL_SETTINGS    => __( 'Configure automatic currency selection, providers, fallback behavior, and checkout interactions.', 'universal-multicurrency' ),
			default                 => __( 'Determine a visitor\'s country and automatically suggest the appropriate currency.', 'universal-multicurrency' ),
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
