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
	 * One-time redirect notice query var (legacy panel id that was requested).
	 */
	public const MOVED_QUERY_VAR = 'umc_geo_moved';

	/**
	 * Panel icon classes keyed by panel id.
	 *
	 * @var array<string, string>
	 */
	private const PANEL_ICONS = array(
		self::PANEL_OVERVIEW  => 'dashicons-dashboard',
		self::PANEL_DETECTION => 'dashicons-randomize',
		self::PANEL_SANDBOX   => 'dashicons-search',
	);

	/**
	 * Retired panel ids mapped to the panel that now covers their content.
	 *
	 * Universal Geo Context now owns provider configuration, trusted proxies,
	 * and geo diagnostics; Currency Routing absorbed the standalone Settings
	 * panel. Kept so bookmarked and previously-shared admin URLs redirect
	 * instead of silently landing on the Overview with no explanation — see
	 * {@see \UMC\Admin\Geo\GeoLegacyPanelRedirect} and ADR-0018.
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_PANELS = array(
		self::PANEL_SETTINGS    => self::PANEL_DETECTION,
		self::PANEL_PROVIDERS   => self::PANEL_OVERVIEW,
		self::PANEL_PROXIES     => self::PANEL_OVERVIEW,
		self::PANEL_DIAGNOSTICS => self::PANEL_OVERVIEW,
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
		);
	}

	/**
	 * Retired panel ids that now redirect elsewhere.
	 *
	 * @return list<string>
	 */
	public static function legacy_panel_ids(): array {
		return array_keys( self::LEGACY_PANELS );
	}

	/**
	 * Whether a panel id is retired.
	 *
	 * @param string $panel Panel id.
	 */
	public static function is_legacy_panel( string $panel ): bool {
		return array_key_exists( $panel, self::LEGACY_PANELS );
	}

	/**
	 * The panel a retired panel id now redirects to.
	 *
	 * @param string $panel Legacy panel id.
	 */
	public static function legacy_redirect_target( string $panel ): string {
		return self::LEGACY_PANELS[ $panel ] ?? self::PANEL_OVERVIEW;
	}

	/**
	 * Returns the raw, sanitized `geo_panel` request value, unconstrained by
	 * the current panel list — used to detect legacy panel ids before they
	 * are normalized away by {@see active_panel()}.
	 */
	public static function raw_requested_panel(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only routing.
		return isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ self::QUERY_VAR ] ) ) : '';
	}

	/**
	 * Returns the active panel from the current request.
	 */
	public static function active_panel(): string {
		$panel = self::raw_requested_panel();

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
		return self::PANEL_DETECTION === $panel;
	}

	/**
	 * Localized label for a panel.
	 *
	 * @param string $panel Panel id.
	 */
	public static function label( string $panel ): string {
		return match ( $panel ) {
			self::PANEL_DETECTION => __( 'Currency Routing', 'universal-multicurrency' ),
			self::PANEL_SANDBOX   => __( 'Currency Simulation', 'universal-multicurrency' ),
			default               => __( 'Overview', 'universal-multicurrency' ),
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
			self::PANEL_OVERVIEW  => __( 'Visitor Location overview', 'universal-multicurrency' ),
			self::PANEL_DETECTION => __( 'Currency Routing', 'universal-multicurrency' ),
			self::PANEL_SANDBOX   => __( 'Currency Simulation', 'universal-multicurrency' ),
			default               => __( 'Visitor Location', 'universal-multicurrency' ),
		};
	}

	/**
	 * Intro description for a panel page introduction block.
	 *
	 * @param string $panel Panel id.
	 */
	public static function intro_description( string $panel ): string {
		return match ( $panel ) {
			self::PANEL_OVERVIEW  => __( 'Check whether Universal Geo Context is connected, which country and currency it produces right now, and whether anything needs attention.', 'universal-multicurrency' ),
			self::PANEL_DETECTION => __( 'Define the ordered policy that turns a detected country into a currency, and how detection behaves at checkout.', 'universal-multicurrency' ),
			self::PANEL_SANDBOX   => __( 'Simulate the currency decision for a given country and shopper state without affecting live shoppers, your session, or storefront data.', 'universal-multicurrency' ),
			default               => __( 'Determine a visitor\'s country and automatically suggest the appropriate currency.', 'universal-multicurrency' ),
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
