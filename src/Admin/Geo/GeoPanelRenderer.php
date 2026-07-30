<?php
/**
 * Visitor Location hub panel renderers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

use UMC\Admin\AdminComponentRenderer;
use UMC\Admin\GeoSandboxController;
use UMC\Geo\GeoDetectionSettings;

/**
 * Renders individual Visitor Location hub panels.
 */
final class GeoPanelRenderer {

	/**
	 * Shared Visitor Location UI helper.
	 *
	 * @var GeoDetectionUi
	 */
	private GeoDetectionUi $ui;

	/**
	 * Admin component renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $components;

	/**
	 * Constructs the panel renderer.
	 *
	 * @param GeoDetectionUi              $ui         Shared UI helper.
	 * @param AdminComponentRenderer|null $components Component renderer.
	 */
	public function __construct( GeoDetectionUi $ui, ?AdminComponentRenderer $components = null ) {
		$this->ui         = $ui;
		$this->components = $components ?? new AdminComponentRenderer();
	}

	/**
	 * Renders the Overview panel.
	 */
	public function render_overview(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
		$geo = $this->ui->geo_settings();

		echo $this->components->statistics_grid_open(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->components->statistics_card(
			__( 'Detection status', 'universal-multicurrency' ),
			$geo->is_enabled() ? __( 'Enabled', 'universal-multicurrency' ) : __( 'Disabled', 'universal-multicurrency' )
		);
		echo $this->components->statistics_card(
			__( 'Active provider', 'universal-multicurrency' ),
			$this->active_provider_label()
		);
		echo $this->components->statistics_card(
			__( 'Current visitor country', 'universal-multicurrency' ),
			__( 'Not available', 'universal-multicurrency' )
		);
		echo $this->components->statistics_card(
			__( 'Suggested currency', 'universal-multicurrency' ),
			__( 'Not detected', 'universal-multicurrency' )
		);
		echo $this->components->statistics_card(
			__( 'Active rules', 'universal-multicurrency' ),
			(string) count( $geo->rules() )
		);
		echo $this->components->statistics_card(
			__( 'Last updated', 'universal-multicurrency' ),
			__( 'Not configured', 'universal-multicurrency' )
		);
		echo $this->components->statistics_grid_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $this->components->settings_card_open( __( 'Status summary', 'universal-multicurrency' ), __( 'Overall Visitor Location configuration at a glance.', 'universal-multicurrency' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->components->status_badge(
			$geo->is_enabled() ? __( 'Active', 'universal-multicurrency' ) : __( 'Disabled', 'universal-multicurrency' ),
			$geo->is_enabled() ? 'active' : 'disabled'
		);
		echo ' ';
		echo $this->components->status_badge(
			sprintf(
				/* translators: %s: detection mode */
				__( 'Mode: %s', 'universal-multicurrency' ),
				$geo->mode()
			),
			'recommended'
		);
		echo $this->components->settings_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $this->components->quick_actions_panel(
			__( 'Quick actions', 'universal-multicurrency' ),
			array(
				array(
					'label'       => __( 'Open Geo Sandbox', 'universal-multicurrency' ),
					'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SANDBOX ),
					'description' => __( 'Test visitor location routing safely.', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'View detection rules', 'universal-multicurrency' ),
					'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_DETECTION ),
					'description' => __( 'Edit geographic routing rules.', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'View providers', 'universal-multicurrency' ),
					'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_PROVIDERS ),
					'description' => __( 'Review available location providers.', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'View diagnostics', 'universal-multicurrency' ),
					'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_DIAGNOSTICS ),
					'description' => __( 'Open Geo Detection diagnostics tools.', 'universal-multicurrency' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo $this->components->info_panel(
			'',
			__( 'Manual currency selection always takes precedence over automatic detection.', 'universal-multicurrency' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		echo $this->components->settings_card_open(
			__( 'Geographic routing', 'universal-multicurrency' ),
			__( 'Rules are evaluated from top to bottom. The first matching rule determines the visitor\'s currency.', 'universal-multicurrency' )
		);
		?>
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
		</p>
		<template id="umc-geo-rule-template">
			<?php $this->ui->render_rule_row_template( $countries, $selectable ); ?>
		</template>
		<?php
		echo $this->components->settings_card_footer(
			sprintf(
				'<a class="button button-secondary" href="%s">%s</a>',
				esc_url( $recommended ),
				esc_html__( 'Add recommended European rules', 'universal-multicurrency' )
			)
		);
	}

	/**
	 * Renders the Settings panel.
	 */
	public function render_settings(): void {
		$geo         = $this->ui->geo_settings();
		$selectable  = $this->ui->selectable_codes();
		$display_url = admin_url( 'admin.php?page=wc-settings&tab=umc&section=display' );
		$compat_url  = admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' );
		echo $this->components->settings_card_open(
			__( 'Automatic currency selection', 'universal-multicurrency' ),
			__( 'Enable automatic currency detection and choose when it should apply.', 'universal-multicurrency' )
		);
		echo $this->components->toggle_row(
			'umc_geo[enabled]',
			$geo->is_enabled(),
			__( 'Enable automatic currency detection', 'universal-multicurrency' ),
			'',
			array( 'id' => 'umc_geo_enabled' )
		);
		echo $this->components->choice_cards_open();
		$modes = array(
			GeoDetectionSettings::MODE_FIRST_VISIT  => array(
				__( 'First eligible visit', 'universal-multicurrency' ),
				__( 'Detect only when no currency has previously been selected.', 'universal-multicurrency' ),
			),
			GeoDetectionSettings::MODE_SESSION      => array(
				__( 'Once per session', 'universal-multicurrency' ),
				__( 'Re-evaluate on every new browsing session.', 'universal-multicurrency' ),
			),
			GeoDetectionSettings::MODE_UNTIL_MANUAL => array(
				__( 'Until manually selected', 'universal-multicurrency' ),
				__( 'Continue detecting until the customer explicitly selects a currency.', 'universal-multicurrency' ),
			),
		);
		foreach ( $modes as $value => $labels ) {
			echo $this->components->choice_card(
				'umc_geo[mode]',
				$value,
				$geo->mode() === $value,
				$labels[0],
				$labels[1]
			);
		}
		echo $this->components->choice_cards_close();
		echo $this->components->settings_card_footer(
			sprintf(
				/* translators: %s: link to Display settings */
				wp_kses_post( __( 'Remembered customer selection is controlled in <a href="%s">Display settings</a>.', 'universal-multicurrency' ) ),
				esc_url( $display_url )
			)
		);

		echo $this->components->settings_card_open(
			__( 'Detection provider', 'universal-multicurrency' ),
			__( 'Review available location providers and configure fallback behavior.', 'universal-multicurrency' )
		);
		$this->ui->render_provider_cards( $this->components );
		echo $this->components->toggle_row(
			'umc_geo[allow_wc_geolocation_fallback]',
			$geo->allow_wc_geolocation_fallback(),
			__( 'WooCommerce fallback', 'universal-multicurrency' ),
			__( 'Use WooCommerce geolocation whenever Universal Geo Context cannot resolve the visitor.', 'universal-multicurrency' ),
			array( 'id' => 'umc_geo_wc_fallback' )
		);
		echo $this->components->settings_card_close();

		$fallback_select = sprintf(
			'<select name="umc_geo[fallback_currency]" id="umc_geo_fallback_currency"><option value="">%s</option>',
			esc_html(
				sprintf(
					/* translators: %s: store base currency code */
					__( 'Store default (%s)', 'universal-multicurrency' ),
					$this->ui->base_code()
				)
			)
		);
		foreach ( $selectable as $code ) {
			$fallback_select .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( $geo->fallback_currency(), $code, false ),
				esc_html( $code )
			);
		}
		$fallback_select .= '</select>';

		echo $this->components->settings_card_open(
			__( 'Technical fallback', 'universal-multicurrency' ),
			__( 'Other countries handles valid countries unmatched by earlier rules. The technical fallback handles missing or invalid country context.', 'universal-multicurrency' )
		);
		echo $this->components->select_row(
			'umc_geo[fallback_currency]',
			__( 'Fallback currency', 'universal-multicurrency' ),
			'',
			$fallback_select,
			array( 'id' => 'umc_geo_fallback_currency' )
		);
		echo $this->components->settings_card_close();

		echo $this->components->settings_card_open(
			__( 'Checkout behaviour', 'universal-multicurrency' ),
			__( 'Once checkout has started, currency is locked to keep totals consistent throughout the order.', 'universal-multicurrency' )
		);
		echo $this->components->toggle_row( 'umc_geo[checkout][lock_on_entry]', $geo->lock_on_entry(), __( 'Lock currency when checkout begins', 'universal-multicurrency' ) );
		echo $this->components->toggle_row( 'umc_geo[checkout][reevaluate_on_billing_change]', $geo->reevaluate_on_billing_change(), __( 'Re-evaluate when billing country changes (before lock)', 'universal-multicurrency' ) );
		echo $this->components->toggle_row( 'umc_geo[checkout][reevaluate_on_shipping_change]', $geo->reevaluate_on_shipping_change(), __( 'Re-evaluate when shipping country changes (before lock)', 'universal-multicurrency' ) );
		$precedence_select = sprintf(
			'<select name="umc_geo[checkout][country_precedence]" id="umc_geo_country_precedence"><option value="billing" %s>%s</option><option value="shipping" %s>%s</option></select>',
			selected( $geo->country_precedence(), 'billing', false ),
			esc_html__( 'Billing country', 'universal-multicurrency' ),
			selected( $geo->country_precedence(), 'shipping', false ),
			esc_html__( 'Shipping country', 'universal-multicurrency' )
		);
		echo $this->components->select_row(
			'umc_geo[checkout][country_precedence]',
			__( 'Checkout country precedence', 'universal-multicurrency' ),
			'',
			$precedence_select,
			array( 'id' => 'umc_geo_country_precedence' )
		);
		echo $this->components->settings_card_close();

		echo $this->components->info_panel(
			__( 'Cache and proxy compatibility', 'universal-multicurrency' ),
			__( 'Geo Detection reuses the existing currency cookie and WooCommerce session. Full-page caching may affect first-page currency display until the session is established.', 'universal-multicurrency' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $compat_url ),
				esc_html__( 'Review Compatibility diagnostics', 'universal-multicurrency' )
			)
		);
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
		$form_id   = 'umc-geo-sandbox-form';

		echo $this->components->settings_card_open(
			__( 'Simulation', 'universal-multicurrency' ),
			__( 'Choose a country context and run a sandbox simulation.', 'universal-multicurrency' )
		);
		?>
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
			<select id="umc_geo_sandbox_browse" data-umc-geo-browse-select form="<?php echo esc_attr( $form_id ); ?>">
				<?php foreach ( $countries as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<input type="hidden" name="umc_geo_sandbox[country]" id="umc_geo_sandbox_country" form="<?php echo esc_attr( $form_id ); ?>" value="<?php echo esc_attr( $selected ); ?>" />

		<details class="umc-geo-sandbox-advanced">
			<summary><?php esc_html_e( 'Shopper precedence simulation', 'universal-multicurrency' ); ?></summary>
			<p>
				<label><input type="checkbox" name="umc_geo_sandbox[explicit_currency]" form="<?php echo esc_attr( $form_id ); ?>" value="1" /> <?php esc_html_e( 'Explicit currency exists', 'universal-multicurrency' ); ?></label>
				<label><input type="checkbox" name="umc_geo_sandbox[session_currency]" form="<?php echo esc_attr( $form_id ); ?>" value="1" /> <?php esc_html_e( 'Session currency exists', 'universal-multicurrency' ); ?></label>
				<label><input type="checkbox" name="umc_geo_sandbox[cookie_currency]" form="<?php echo esc_attr( $form_id ); ?>" value="1" /> <?php esc_html_e( 'Cookie currency exists', 'universal-multicurrency' ); ?></label>
				<label><input type="checkbox" name="umc_geo_sandbox[checkout_locked]" form="<?php echo esc_attr( $form_id ); ?>" value="1" /> <?php esc_html_e( 'Checkout locked', 'universal-multicurrency' ); ?></label>
			</p>
		</details>

		<p><button type="submit" class="button button-primary" form="<?php echo esc_attr( $form_id ); ?>"><?php esc_html_e( 'Run sandbox', 'universal-multicurrency' ); ?></button></p>
		<?php
		echo $this->components->settings_card_close();

		if ( is_array( $result ) ) {
			echo $this->components->settings_card_open(
				__( 'Last sandbox result', 'universal-multicurrency' ),
				__( 'Most recent simulation output for your account.', 'universal-multicurrency' )
			);
			?>
			<pre class="umc-geo-sandbox-output"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<?php
			echo $this->components->settings_card_close();
		}
	}

	/**
	 * Renders the Providers panel (read-only v0.12).
	 */
	public function render_providers(): void {
		echo $this->components->settings_card_open(
			__( 'Provider chain', 'universal-multicurrency' ),
			__( 'Provider chain configuration is read-only in this release. Universal Geo Context is preferred when available; WooCommerce billing/shipping and geolocation provide fallback signals.', 'universal-multicurrency' )
		);
		$this->ui->render_provider_cards( $this->components );
		echo $this->components->settings_card_close();

		echo $this->components->empty_state(
			'dashicons-admin-plugins',
			__( 'Advanced provider controls coming soon', 'universal-multicurrency' ),
			__( 'Editable provider priorities, enable/disable controls, and health metrics are planned for a future release.', 'universal-multicurrency' )
		);
	}

	/**
	 * Renders the Trusted Proxies panel (UGC boundary).
	 */
	public function render_proxies(): void {
		echo $this->components->empty_state(
			'dashicons-shield',
			__( 'Managed by Universal Geo Context', 'universal-multicurrency' ),
			__( 'Universal Multicurrency does not manage trusted proxy configuration. When Universal Geo Context is installed, it handles proxy and CDN country signals.', 'universal-multicurrency' )
		);
	}

	/**
	 * Renders the Diagnostics panel (stub v0.12).
	 */
	public function render_diagnostics(): void {
		$geo = $this->ui->geo_settings();

		echo $this->components->settings_card_open(
			__( 'Health overview', 'universal-multicurrency' ),
			__( 'Geo Detection health signals for this store.', 'universal-multicurrency' )
		);
		echo $this->components->status_badge(
			sprintf(
				/* translators: %d: number of routing rules */
				__( 'Routing rules: %d', 'universal-multicurrency' ),
				count( $geo->rules() )
			),
			count( $geo->rules() ) > 0 ? 'active' : 'warning'
		);
		echo ' ';
		echo $this->components->status_badge(
			sprintf(
				/* translators: %d: region registry version */
				__( 'Region registry version: %d', 'universal-multicurrency' ),
				\UMC\Geo\GeoRegionRegistry::VERSION
			),
			'available'
		);
		echo $this->components->settings_card_close();

		echo $this->components->info_panel(
			__( 'Diagnostics tools', 'universal-multicurrency' ),
			__( 'Open related diagnostics to inspect Geo Detection configuration alongside other store health checks.', 'universal-multicurrency' ),
			sprintf(
				'<a href="%1$s">%2$s</a> · <a href="%3$s">%4$s</a>',
				esc_url( admin_url( 'site-health.php' ) ),
				esc_html__( 'Open Site Health', 'universal-multicurrency' ),
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' ) ),
				esc_html__( 'Open Compatibility diagnostics', 'universal-multicurrency' )
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Returns the active provider label for overview statistics.
	 */
	private function active_provider_label(): string {
		if ( function_exists( 'universal_geo_get_country_code' ) && function_exists( 'universal_geo_api_version' ) ) {
			return __( 'Universal Geo Context', 'universal-multicurrency' );
		}

		return __( 'WooCommerce fallback', 'universal-multicurrency' );
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
