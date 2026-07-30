<?php
/**
 * Geo Detection settings admin field (hub router).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoDetectionUi;
use UMC\Admin\Geo\GeoPanelNavigation;
use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\Geo\GeoPanelRenderer;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoRegionRegistry;
use UMC\Settings;

/**
 * Renders the Geo Detection hub with secondary panel navigation.
 */
final class GeoDetectionSettingsField {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Shared geo UI helper.
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
	 * Constructs the geo settings field renderer.
	 *
	 * @param Settings                $settings Settings store.
	 * @param Currency                $base     Base currency.
	 * @param CurrencyRegistry        $registry Currency registry.
	 * @param GeoDetectionUi|null     $ui      Shared UI helper.
	 * @param GeoPanelRenderer|null   $panels Panel renderer.
	 * @param GeoPanelNavigation|null $navigation Navigation renderer.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		CurrencyRegistry $registry,
		?GeoDetectionUi $ui = null,
		?GeoPanelRenderer $panels = null,
		?GeoPanelNavigation $navigation = null
	) {
		$this->settings   = $settings;
		$this->registry   = $registry;
		$this->ui         = $ui ?? new GeoDetectionUi( $settings, $base, $registry, new GeoRegionRegistry() );
		$this->panels     = $panels ?? new GeoPanelRenderer( $this->ui );
		$this->navigation = $navigation ?? new GeoPanelNavigation();
	}

	/**
	 * Renders the Geo Detection hub field.
	 */
	public function render(): void {
		$active = GeoPanelRegistry::active_panel();

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-geo-settings" colspan="2">
				<div class="umc-display-card umc-geo-card" data-umc-geo-root>
					<div aria-live="polite" class="screen-reader-text" data-umc-geo-live></div>
					<?php $this->navigation->render( $active ); ?>
					<div class="umc-geo-panel" data-umc-geo-panel="<?php echo esc_attr( $active ); ?>">
						<?php $this->render_panel( $active ); ?>
					</div>
				</div>
			</td>
		</tr>
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
			GeoPanelRegistry::PANEL_DETECTION   => $this->panels->render_detection(),
			GeoPanelRegistry::PANEL_SETTINGS    => $this->panels->render_settings(),
			GeoPanelRegistry::PANEL_SANDBOX     => $this->panels->render_sandbox(),
			GeoPanelRegistry::PANEL_PROVIDERS     => $this->panels->render_providers(),
			GeoPanelRegistry::PANEL_PROXIES      => $this->panels->render_proxies(),
			GeoPanelRegistry::PANEL_DIAGNOSTICS  => $this->panels->render_diagnostics(),
			default                              => $this->panels->render_overview(),
		};
	}
}
