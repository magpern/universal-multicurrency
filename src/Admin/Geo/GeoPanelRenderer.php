<?php
/**
 * Geo Detection hub panel renderers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

use UMC\Admin\GeoSandboxController;
use UMC\Geo\GeoDetectionSettings;

/**
 * Renders individual Geo hub panels.
 */
final class GeoPanelRenderer {

	/**
	 * Shared Geo Detection UI helper.
	 *
	 * @var GeoDetectionUi
	 */
	private GeoDetectionUi $ui;

	/**
	 * Constructs the panel renderer.
	 *
	 * @param GeoDetectionUi $ui Shared UI helper.
	 */
	public function __construct( GeoDetectionUi $ui ) {
		$this->ui = $ui;
	}

	/**
	 * Renders the Overview panel.
	 */
	public function render_overview(): void {
		$geo           = $this->ui->geo_settings();
		$settings_url  = GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SETTINGS );
		$detection_url = GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_DETECTION );
		$sandbox_url   = GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SANDBOX );
		?>
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Geo Detection overview', 'universal-multicurrency' ); ?></h3>
		<ul class="umc-geo-overview-stats">
			<li><?php echo esc_html( $geo->is_enabled() ? __( 'Status: Enabled', 'universal-multicurrency' ) : __( 'Status: Disabled', 'universal-multicurrency' ) ); ?></li>
			<li>
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: detection mode */
					__( 'Mode: %s', 'universal-multicurrency' ),
					$geo->mode()
				)
			);
			?>
			</li>
			<li>
			<?php
			echo esc_html(
				sprintf(
				/* translators: %d: number of routing rules */
					_n( '%d routing rule', '%d routing rules', count( $geo->rules() ), 'universal-multicurrency' ),
					count( $geo->rules() )
				)
			);
			?>
			</li>
		</ul>
		<?php $this->ui->render_provider_status(); ?>
		<p class="umc-geo-note"><?php esc_html_e( 'Manual currency selection always takes precedence over automatic detection.', 'universal-multicurrency' ); ?></p>
		<p class="umc-geo-overview-actions">
			<a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure settings', 'universal-multicurrency' ); ?></a>
			<a class="button" href="<?php echo esc_url( $detection_url ); ?>"><?php esc_html_e( 'Edit routing rules', 'universal-multicurrency' ); ?></a>
			<a class="button" href="<?php echo esc_url( $sandbox_url ); ?>"><?php esc_html_e( 'Open Geo Sandbox', 'universal-multicurrency' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Renders the Detection (routing rules) panel.
	 */
	public function render_detection(): void {
		$geo         = $this->ui->geo_settings();
		$selectable  = $this->ui->selectable_codes();
		$countries   = $this->ui->countries();
		$recommended = wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_geo_add_recommended_rules' ),
			'umc_geo_add_recommended_rules'
		);
		?>
		<input type="hidden" name="umc_geo_panel" value="<?php echo esc_attr( GeoPanelRegistry::PANEL_DETECTION ); ?>" />
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Geographic routing', 'universal-multicurrency' ); ?></h3>
		<p class="umc-geo-routing-intro"><strong><?php esc_html_e( 'Rules are evaluated from top to bottom. The first matching rule determines the visitor\'s currency.', 'universal-multicurrency' ); ?></strong></p>
		<ol class="umc-geo-rules" data-umc-geo-rules>
			<?php
			$index = 0;
			foreach ( $geo->rules() as $rule ) {
				++$index;
				$this->ui->render_rule_row( $rule, $index, count( $geo->rules() ), $countries, $selectable, $geo->rules() );
			}
			?>
		</ol>
		<p class="umc-geo-rule-actions">
			<button type="button" class="button" data-umc-geo-add="country"><?php esc_html_e( 'Add country rule', 'universal-multicurrency' ); ?></button>
			<button type="button" class="button" data-umc-geo-add="region"><?php esc_html_e( 'Add region rule', 'universal-multicurrency' ); ?></button>
			<button type="button" class="button" data-umc-geo-add="other"><?php esc_html_e( 'Add catch-all rule', 'universal-multicurrency' ); ?></button>
			<a class="button button-secondary" href="<?php echo esc_url( $recommended ); ?>"><?php esc_html_e( 'Add recommended European rules', 'universal-multicurrency' ); ?></a>
		</p>
		<template id="umc-geo-rule-template">
			<?php $this->ui->render_rule_row_template( $countries, $selectable ); ?>
		</template>
		<?php
	}

	/**
	 * Renders the Settings panel.
	 */
	public function render_settings(): void {
		$geo         = $this->ui->geo_settings();
		$selectable  = $this->ui->selectable_codes();
		$display_url = admin_url( 'admin.php?page=wc-settings&tab=umc&section=display' );
		$compat_url  = admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' );
		?>
		<input type="hidden" name="umc_geo_panel" value="<?php echo esc_attr( GeoPanelRegistry::PANEL_SETTINGS ); ?>" />
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Automatic currency selection', 'universal-multicurrency' ); ?></h3>
		<label class="umc-display-toggle-row" for="umc_geo_enabled">
			<input type="checkbox" name="umc_geo[enabled]" id="umc_geo_enabled" value="1" <?php checked( $geo->is_enabled() ); ?> />
			<span><?php esc_html_e( 'Enable automatic currency detection', 'universal-multicurrency' ); ?></span>
		</label>
		<fieldset class="umc-display-fieldset umc-geo-modes">
			<legend class="screen-reader-text"><?php esc_html_e( 'Detection timing', 'universal-multicurrency' ); ?></legend>
			<?php
			$modes = array(
				GeoDetectionSettings::MODE_FIRST_VISIT  => __( 'First eligible visit', 'universal-multicurrency' ),
				GeoDetectionSettings::MODE_SESSION      => __( 'Once per session', 'universal-multicurrency' ),
				GeoDetectionSettings::MODE_UNTIL_MANUAL => __( 'Until manually selected', 'universal-multicurrency' ),
			);
			foreach ( $modes as $value => $label ) {
				printf(
					'<label class="umc-display-choice-card umc-geo-mode"><input type="radio" name="umc_geo[mode]" value="%1$s"%2$s /><span class="umc-display-choice-card__content"><span class="umc-display-choice-card__title">%3$s</span></span></label>',
					esc_attr( $value ),
					checked( $geo->mode(), $value, false ),
					esc_html( $label )
				);
			}
			?>
		</fieldset>
		<p class="umc-geo-note">
			<?php
			printf(
				/* translators: %s: link to Display settings */
				wp_kses_post( __( 'Remembered customer selection is controlled in <a href="%s">Display settings</a>.', 'universal-multicurrency' ) ),
				esc_url( $display_url )
			);
			?>
		</p>

		<h3 class="umc-display-card__title"><?php esc_html_e( 'Detection provider', 'universal-multicurrency' ); ?></h3>
		<?php $this->ui->render_provider_status(); ?>
		<label class="umc-display-toggle-row" for="umc_geo_wc_fallback">
			<input type="checkbox" name="umc_geo[allow_wc_geolocation_fallback]" id="umc_geo_wc_fallback" value="1" <?php checked( $geo->allow_wc_geolocation_fallback() ); ?> />
			<span><?php esc_html_e( 'Use WooCommerce geolocation when Universal Geo Context is unavailable', 'universal-multicurrency' ); ?></span>
		</label>

		<h3 class="umc-display-card__title"><?php esc_html_e( 'Technical fallback', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Other countries handles valid countries unmatched by earlier rules. The technical fallback handles missing or invalid country context.', 'universal-multicurrency' ); ?></p>
		<select name="umc_geo[fallback_currency]" id="umc_geo_fallback_currency">
			<option value=""><?php echo esc_html( sprintf( /* translators: %s: store base currency code */ __( 'Store default (%s)', 'universal-multicurrency' ), $this->ui->base_code() ) ); ?></option>
			<?php foreach ( $selectable as $code ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $geo->fallback_currency(), $code ); ?>><?php echo esc_html( $code ); ?></option>
			<?php endforeach; ?>
		</select>

		<h3 class="umc-display-card__title"><?php esc_html_e( 'Checkout behavior', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Once checkout has started, currency is locked to keep totals consistent throughout the order.', 'universal-multicurrency' ); ?></p>
		<label class="umc-display-toggle-row"><input type="checkbox" name="umc_geo[checkout][lock_on_entry]" value="1" <?php checked( $geo->lock_on_entry() ); ?> /> <?php esc_html_e( 'Lock currency when checkout begins', 'universal-multicurrency' ); ?></label>
		<label class="umc-display-toggle-row"><input type="checkbox" name="umc_geo[checkout][reevaluate_on_billing_change]" value="1" <?php checked( $geo->reevaluate_on_billing_change() ); ?> /> <?php esc_html_e( 'Re-evaluate when billing country changes (before lock)', 'universal-multicurrency' ); ?></label>
		<label class="umc-display-toggle-row"><input type="checkbox" name="umc_geo[checkout][reevaluate_on_shipping_change]" value="1" <?php checked( $geo->reevaluate_on_shipping_change() ); ?> /> <?php esc_html_e( 'Re-evaluate when shipping country changes (before lock)', 'universal-multicurrency' ); ?></label>
		<p>
			<label for="umc_geo_country_precedence"><?php esc_html_e( 'Checkout country precedence', 'universal-multicurrency' ); ?></label>
			<select name="umc_geo[checkout][country_precedence]" id="umc_geo_country_precedence">
				<option value="billing" <?php selected( $geo->country_precedence(), 'billing' ); ?>><?php esc_html_e( 'Billing country', 'universal-multicurrency' ); ?></option>
				<option value="shipping" <?php selected( $geo->country_precedence(), 'shipping' ); ?>><?php esc_html_e( 'Shipping country', 'universal-multicurrency' ); ?></option>
			</select>
		</p>

		<h3 class="umc-display-card__title"><?php esc_html_e( 'Cache and proxy compatibility', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Geo Detection reuses the existing currency cookie and WooCommerce session. Full-page caching may affect first-page currency display until the session is established.', 'universal-multicurrency' ); ?></p>
		<p><a href="<?php echo esc_url( $compat_url ); ?>"><?php esc_html_e( 'Review Compatibility diagnostics', 'universal-multicurrency' ); ?></a></p>
		<?php
	}

	/**
	 * Renders the Geo Sandbox panel.
	 */
	public function render_sandbox(): void {
		$countries = $this->ui->countries();
		$recent    = ( new GeoSandboxRecentStore() )->get_recent();
		$quick     = GeoSandboxRecentStore::quick_pick_codes();
		$result    = GeoSandboxController::last_result_for_current_user();
		$selected  = is_array( $result ) ? (string) ( $result['geo']['country'] ?? 'DE' ) : 'DE';
		?>
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Geo Sandbox', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Reproduce geographic context and inspect routing without changing storefront session, cookies, or active currency.', 'universal-multicurrency' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="umc-geo-sandbox-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( GeoSandboxController::ACTION ); ?>" />
			<?php wp_nonce_field( GeoSandboxController::ACTION ); ?>

			<div class="umc-geo-sandbox-presets">
				<?php if ( array() !== $recent ) : ?>
					<h4><?php esc_html_e( 'Recently used', 'universal-multicurrency' ); ?></h4>
					<div class="umc-geo-sandbox-preset-row">
						<?php foreach ( $recent as $code ) : ?>
							<button type="button" class="button umc-geo-sandbox-preset" data-umc-geo-preset-country="<?php echo esc_attr( $code ); ?>">
								<?php echo esc_html( $this->country_label( $code, $countries ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<h4><?php esc_html_e( 'Quick picks', 'universal-multicurrency' ); ?></h4>
				<div class="umc-geo-sandbox-preset-row">
					<?php foreach ( $quick as $code ) : ?>
						<button type="button" class="button umc-geo-sandbox-preset" data-umc-geo-preset-country="<?php echo esc_attr( $code ); ?>">
							<?php echo esc_html( $this->country_label( $code, $countries ) ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<p>
				<button type="button" class="button-link" data-umc-geo-toggle-browse><?php esc_html_e( 'Browse all countries…', 'universal-multicurrency' ); ?></button>
			</p>
			<p class="umc-geo-sandbox-browse" hidden>
				<label for="umc_geo_sandbox_browse"><?php esc_html_e( 'All countries', 'universal-multicurrency' ); ?></label>
				<select id="umc_geo_sandbox_browse" data-umc-geo-browse-select>
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<input type="hidden" name="umc_geo_sandbox[country]" id="umc_geo_sandbox_country" value="<?php echo esc_attr( $selected ); ?>" />

			<details class="umc-geo-sandbox-advanced">
				<summary><?php esc_html_e( 'Shopper precedence simulation', 'universal-multicurrency' ); ?></summary>
				<p>
					<label><input type="checkbox" name="umc_geo_sandbox[explicit_currency]" value="1" /> <?php esc_html_e( 'Explicit currency exists', 'universal-multicurrency' ); ?></label>
					<label><input type="checkbox" name="umc_geo_sandbox[session_currency]" value="1" /> <?php esc_html_e( 'Session currency exists', 'universal-multicurrency' ); ?></label>
					<label><input type="checkbox" name="umc_geo_sandbox[cookie_currency]" value="1" /> <?php esc_html_e( 'Cookie currency exists', 'universal-multicurrency' ); ?></label>
					<label><input type="checkbox" name="umc_geo_sandbox[checkout_locked]" value="1" /> <?php esc_html_e( 'Checkout locked', 'universal-multicurrency' ); ?></label>
				</p>
			</details>

			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Run sandbox', 'universal-multicurrency' ); ?></button></p>
		</form>

		<?php if ( is_array( $result ) ) : ?>
			<div class="umc-geo-sandbox-result">
				<h4><?php esc_html_e( 'Last sandbox result', 'universal-multicurrency' ); ?></h4>
				<pre class="umc-geo-sandbox-output"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the Providers panel (read-only v0.12).
	 */
	public function render_providers(): void {
		?>
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Detection providers', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Provider chain configuration is read-only in this release. Universal Geo Context is preferred when available; WooCommerce billing/shipping and geolocation provide fallback signals.', 'universal-multicurrency' ); ?></p>
		<?php $this->ui->render_provider_status(); ?>
		<p class="umc-geo-note"><?php esc_html_e( 'Editable provider priorities, enable/disable controls, and health metrics are planned for a future release.', 'universal-multicurrency' ); ?></p>
		<?php
	}

	/**
	 * Renders the Trusted Proxies panel (UGC boundary).
	 */
	public function render_proxies(): void {
		?>
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Trusted proxies', 'universal-multicurrency' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Universal Multicurrency does not manage trusted proxy configuration. When Universal Geo Context is installed, it handles proxy and CDN country signals.', 'universal-multicurrency' ); ?></p>
		<p class="umc-geo-note"><?php esc_html_e( 'This panel will mirror Universal Geo Context proxy status when that API becomes available.', 'universal-multicurrency' ); ?></p>
		<?php
	}

	/**
	 * Renders the Diagnostics panel (stub v0.12).
	 */
	public function render_diagnostics(): void {
		$geo = $this->ui->geo_settings();
		?>
		<h3 class="umc-display-card__title"><?php esc_html_e( 'Geo diagnostics', 'universal-multicurrency' ); ?></h3>
		<ul class="umc-geo-overview-stats">
			<li><?php echo esc_html( sprintf( /* translators: %d: number of routing rules */ __( 'Routing rules: %d', 'universal-multicurrency' ), count( $geo->rules() ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d: region registry version */ __( 'Region registry version: %d', 'universal-multicurrency' ), \UMC\Geo\GeoRegionRegistry::VERSION ) ); ?></li>
		</ul>
		<p><a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php esc_html_e( 'Open Site Health', 'universal-multicurrency' ); ?></a></p>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' ) ); ?>"><?php esc_html_e( 'Open Compatibility diagnostics', 'universal-multicurrency' ); ?></a></p>
		<?php
	}

	/**
	 * Formats a country preset label with ISO code.
	 *
	 * @param string               $code      ISO code.
	 * @param array<string,string> $countries Country map.
	 */
	private function country_label( string $code, array $countries ): string {
		$name = $countries[ $code ] ?? $code;

		return $name . ' (' . $code . ')';
	}
}
