<?php
/**
 * Visitor Location settings admin field (hub router).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoDetectionUi;
use UMC\Admin\Geo\GeoPanelNavigation;
use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\Geo\GeoPanelRenderer;
use UMC\Admin\GeoSandboxController;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\UgcIntegrationStatus;
use UMC\Settings;

/**
 * Renders the Visitor Location hub with secondary panel navigation.
 */
final class GeoDetectionSettingsField {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Shared visitor location UI helper.
	 *
	 * @var GeoDetectionUi
	 */
	private GeoDetectionUi $ui;

	/**
	 * Panel content renderer.
	 *
	 * @var GeoPanelRenderer
	 */
	private GeoPanelRenderer $panels;

	/**
	 * Secondary navigation renderer.
	 *
	 * @var GeoPanelNavigation
	 */
	private GeoPanelNavigation $navigation;

	/**
	 * Shared admin component renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $components;

	/**
	 * Currency registry for POST parsing.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Constructs the visitor location settings field renderer.
	 *
	 * @param Settings                    $settings   Settings store.
	 * @param Currency                    $base       Base currency.
	 * @param CurrencyRegistry            $registry   Currency registry.
	 * @param GeoDetectionUi|null         $ui         Shared UI helper.
	 * @param GeoPanelRenderer|null       $panels     Panel renderer.
	 * @param GeoPanelNavigation|null     $navigation Navigation renderer.
	 * @param AdminComponentRenderer|null $components Component renderer.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		CurrencyRegistry $registry,
		?GeoDetectionUi $ui = null,
		?GeoPanelRenderer $panels = null,
		?GeoPanelNavigation $navigation = null,
		?AdminComponentRenderer $components = null
	) {
		$this->settings   = $settings;
		$this->registry   = $registry;
		$this->components = $components ?? new AdminComponentRenderer();
		$this->ui         = $ui ?? new GeoDetectionUi( $settings, $base, $registry, new GeoRegionRegistry() );
		$this->panels     = $panels ?? new GeoPanelRenderer( $this->ui, $this->components );
		$this->navigation = $navigation ?? new GeoPanelNavigation( $this->components );
	}

	/**
	 * Renders the Visitor Location hub field.
	 */
	public function render(): void {
		$active = GeoPanelRegistry::active_panel();

		if ( GeoPanelRegistry::PANEL_SANDBOX === $active ) {
			add_action( 'admin_footer', array( $this, 'render_sandbox_form_anchor' ), 20 );
		}

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-geo-settings" colspan="2">
				<div class="umc-geo-hub" data-umc-geo-root data-umc-sticky-root="visitor-location">
					<div aria-live="polite" class="screen-reader-text" data-umc-geo-live></div>
					<?php $this->navigation->render( $active ); ?>
					<?php $this->maybe_render_moved_notice(); ?>
					<div class="umc-geo-panel umc-geo-panel-stack <?php echo esc_attr( GeoPanelRegistry::PANEL_OVERVIEW === $active ? 'umc-ui-layout--wide' : 'umc-ui-layout--readable' ); ?>" data-umc-geo-panel="<?php echo esc_attr( $active ); ?>">
						<?php
						echo $this->components->page_intro( GeoPanelRegistry::intro_title( $active ), GeoPanelRegistry::intro_description( $active ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
						$this->render_panel( $active );
						?>
					</div>
					<?php if ( GeoPanelRegistry::is_saveable_panel( $active ) ) : ?>
						<?php
						echo $this->components->sticky_save_bar( 'visitor-location' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
						?>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the sandbox POST form anchor outside the WooCommerce settings form.
	 */
	public function render_sandbox_form_anchor(): void {
		?>
		<form id="umc-geo-sandbox-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="umc-geo-sandbox-form-anchor">
			<input type="hidden" name="action" value="<?php echo esc_attr( GeoSandboxController::ACTION ); ?>" />
			<?php wp_nonce_field( GeoSandboxController::ACTION ); ?>
		</form>
		<?php
	}

	/**
	 * Parses geo settings from POST (delegates to parser).
	 *
	 * @return array{geo:array<string,mixed>,warnings:list<string>}|null Null when validation failed.
	 */
	public function parse_post(): ?array {
		$parser = new GeoDetectionSettingsParser( $this->settings, $this->registry );

		return $parser->parse_post();
	}

	/**
	 * Dispatches rendering to the active panel.
	 *
	 * @param string $panel Panel id.
	 */
	private function render_panel( string $panel ): void {
		match ( $panel ) {
			GeoPanelRegistry::PANEL_DETECTION => $this->panels->render_detection(),
			GeoPanelRegistry::PANEL_SANDBOX   => $this->panels->render_sandbox(),
			default                           => $this->panels->render_overview(),
		};
	}

	/**
	 * Renders a one-time notice when a retired panel URL was requested.
	 */
	private function maybe_render_moved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, one-time notice after a redirect.
		$legacy = isset( $_GET[ GeoPanelRegistry::MOVED_QUERY_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ GeoPanelRegistry::MOVED_QUERY_VAR ] ) ) : '';

		if ( ! GeoPanelRegistry::is_legacy_panel( $legacy ) ) {
			return;
		}

		echo $this->components->info_panel( '', $this->moved_notice_message( $legacy ), $this->moved_notice_action( $legacy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
	}

	/**
	 * Notice copy for a retired panel id.
	 *
	 * @param string $legacy Legacy panel id.
	 */
	private function moved_notice_message( string $legacy ): string {
		return match ( $legacy ) {
			GeoPanelRegistry::PANEL_SETTINGS    => __( 'Visitor Location settings now live on the Currency Routing panel.', 'universal-multicurrency' ),
			GeoPanelRegistry::PANEL_PROVIDERS   => __( 'Provider configuration is managed in Universal Geo Context.', 'universal-multicurrency' ),
			GeoPanelRegistry::PANEL_PROXIES     => __( 'Trusted proxies are managed in Universal Geo Context.', 'universal-multicurrency' ),
			GeoPanelRegistry::PANEL_DIAGNOSTICS => __( 'Geo diagnostics are managed in Universal Geo Context. Currency-routing status is shown here.', 'universal-multicurrency' ),
			default                             => __( 'This page has moved.', 'universal-multicurrency' ),
		};
	}

	/**
	 * Optional deep-link action markup for a retired panel notice.
	 *
	 * @param string $legacy Legacy panel id.
	 */
	private function moved_notice_action( string $legacy ): string {
		if ( GeoPanelRegistry::PANEL_SETTINGS === $legacy || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$status = new UgcIntegrationStatus();

		if ( ! $status->is_available() ) {
			return '';
		}

		$url = match ( $legacy ) {
			GeoPanelRegistry::PANEL_PROVIDERS   => $status->providers_url(),
			GeoPanelRegistry::PANEL_PROXIES     => $status->trusted_proxies_url(),
			GeoPanelRegistry::PANEL_DIAGNOSTICS => $status->diagnostics_url(),
			default                             => '',
		};

		if ( '' === $url ) {
			return '';
		}

		return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Open Universal Geo Context', 'universal-multicurrency' ) );
	}
}
