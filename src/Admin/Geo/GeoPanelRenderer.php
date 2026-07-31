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
use UMC\Geo\GeoCurrencyRuleEvaluator;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\GeoRoutingRule;
use UMC\Geo\GeoRuleEvaluationResult;
use UMC\Geo\UgcIntegrationStatus;

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
	 * Renders the Overview panel: a merchant dashboard answering exactly four
	 * questions — is Visitor Location connected, which country is currently
	 * detected, which currency would that produce, and is anything wrong.
	 * Provider, source, confidence, and version detail live behind a
	 * collapsed "Technical details" section, never on the first screen.
	 */
	public function render_overview(): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
		$geo      = $this->ui->geo_settings();
		$status   = new UgcIntegrationStatus();
		$country  = $this->live_country( $status );
		$decision = $this->evaluate_live_country( $geo, $country );

		echo $this->components->feature_section_open(
			__( 'Visitor Location Provider', 'universal-multicurrency' ),
			''
		);
		echo $this->ugc_integration_health_card( $status );
		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Detected country and resulting currency', 'universal-multicurrency' ),
			''
		);
		echo $this->components->settings_card_open( '', '' );
		echo $this->detection_and_outcome_card( $status, $geo, $country, $decision );
		echo $this->components->settings_card_close();
		echo $this->components->feature_section_close();

		$attention = $this->needs_attention( $status, $geo, $decision );

		if ( array() !== $attention ) {
			echo $this->components->feature_section_open(
				__( 'Needs attention', 'universal-multicurrency' ),
				''
			);
			foreach ( $attention as $message ) {
				echo $this->components->warning_panel( '', $message );
			}
			echo $this->components->feature_section_close();
		}

		echo $this->components->feature_section_open(
			__( 'At a glance', 'universal-multicurrency' ),
			''
		);
		echo $this->components->statistics_grid_open();
		echo $this->components->statistics_card(
			__( 'Detection status', 'universal-multicurrency' ),
			$geo->is_enabled()
				? sprintf(
					/* translators: %s: detection mode */
					__( 'Enabled (%s)', 'universal-multicurrency' ),
					$geo->mode()
				)
				: __( 'Disabled', 'universal-multicurrency' )
		);
		echo $this->components->statistics_card(
			__( 'Routing rules', 'universal-multicurrency' ),
			sprintf( '%d / %d', count( $geo->rules() ), GeoRoutingRule::MAX_RULES )
		);
		echo $this->components->statistics_card(
			__( 'Fallback currency', 'universal-multicurrency' ),
			'' !== $geo->fallback_currency() ? $geo->fallback_currency() : $this->ui->base_code()
		);
		echo $this->components->statistics_grid_close();
		echo $this->components->info_panel(
			'',
			__( 'Manual currency selection always takes precedence over automatic detection.', 'universal-multicurrency' )
		);
		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Quick actions', 'universal-multicurrency' ),
			''
		);
		echo $this->components->quick_actions_panel(
			__( 'Quick actions', 'universal-multicurrency' ),
			$this->quick_actions( $status )
		);
		echo $this->components->feature_section_close();

		echo $this->technical_details( $status, $country, $decision );
	}

	/**
	 * Renders the Currency Routing panel: automatic detection, the ordered
	 * routing policy, location sources, fallback currency, and checkout
	 * behaviour — the single saveable panel in the Visitor Location hub.
	 */
	public function render_detection(): void {
		$geo         = $this->ui->geo_settings();
		$selectable  = $this->ui->selectable_codes();
		$countries   = $this->ui->countries();
		$display_url = admin_url( 'admin.php?page=wc-settings&tab=umc&section=display' );
		$compat_url  = admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' );
		$recommended = wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_geo_add_recommended_rules' ),
			'umc_geo_add_recommended_rules'
		);

		echo $this->components->feature_section_open(
			__( 'Automatic currency selection', 'universal-multicurrency' ),
			__( 'Control when automatic currency detection runs for each visitor.', 'universal-multicurrency' )
		);

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

		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Routing policy', 'universal-multicurrency' ),
			__( 'Rules are evaluated in order, top to bottom. The first matching rule decides the currency.', 'universal-multicurrency' )
		);

		echo $this->components->settings_card_open(
			__( 'Country and region rules', 'universal-multicurrency' ),
			__( 'Each rule matches a country or region and assigns a currency. Add an "Other countries" rule last to catch every remaining valid country.', 'universal-multicurrency' )
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

		echo $this->components->feature_section_close();

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

		echo $this->components->feature_section_open(
			__( 'Technical fallback', 'universal-multicurrency' ),
			__( 'Applies when the visitor\'s country is missing or invalid — not when a valid country simply has no matching rule.', 'universal-multicurrency' )
		);

		echo $this->components->settings_card_open(
			__( 'Fallback currency', 'universal-multicurrency' ),
			__( 'An "Other countries" rule handles valid countries unmatched by earlier rules. The technical fallback below only handles missing or invalid country context.', 'universal-multicurrency' )
		);
		echo $this->components->select_row(
			'umc_geo[fallback_currency]',
			__( 'Fallback currency', 'universal-multicurrency' ),
			'',
			$fallback_select,
			array( 'id' => 'umc_geo_fallback_currency' )
		);
		echo $this->components->settings_card_close();

		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Location sources', 'universal-multicurrency' ),
			__( 'Universal Geo Context supplies country facts when available. Provider configuration, trusted proxies, and detection diagnostics live there — not in this plugin.', 'universal-multicurrency' )
		);

		echo $this->components->settings_card_open(
			__( 'Detection provider', 'universal-multicurrency' ),
			__( 'Universal Multicurrency never duplicates location detection — it consumes Universal Geo Context and falls back to WooCommerce when it is unavailable.', 'universal-multicurrency' )
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

		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Checkout behaviour', 'universal-multicurrency' ),
			__( 'How Visitor Location interacts with checkout once a session begins.', 'universal-multicurrency' )
		);

		echo $this->components->settings_card_open(
			__( 'Checkout locking', 'universal-multicurrency' ),
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

		echo $this->components->feature_section_close();

		echo $this->components->feature_section_open(
			__( 'Compatibility', 'universal-multicurrency' ),
			__( 'How Visitor Location interacts with caching and proxy setups.', 'universal-multicurrency' )
		);

		echo $this->components->info_panel(
			__( 'Cache and proxy compatibility', 'universal-multicurrency' ),
			__( 'Geo Detection reuses the existing currency cookie and WooCommerce session. Full-page caching may affect first-page currency display until the session is established.', 'universal-multicurrency' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $compat_url ),
				esc_html__( 'Review Compatibility diagnostics', 'universal-multicurrency' )
			)
		);

		echo $this->components->feature_section_close();
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

		echo $this->components->feature_section_open(
			__( 'Simulation', 'universal-multicurrency' ),
			__( 'Test routing safely without affecting live shoppers.', 'universal-multicurrency' )
		);

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

		echo $this->components->feature_section_close();

		if ( is_array( $result ) ) {
			echo $this->components->feature_section_open(
				__( 'Results', 'universal-multicurrency' ),
				__( 'Output from your most recent sandbox run.', 'universal-multicurrency' )
			);

			echo $this->components->settings_card_open(
				__( 'Last sandbox result', 'universal-multicurrency' ),
				__( 'Most recent simulation output for your account.', 'universal-multicurrency' )
			);
			?>
			<pre class="umc-geo-sandbox-output"><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
			<?php
			echo $this->components->settings_card_close();

			echo $this->components->feature_section_close();
		}
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

	/**
	 * Reads the country Universal Geo Context resolves for the current
	 * (admin) request, via the same public functions the storefront adapter
	 * uses. Never falls back to WooCommerce geolocation here — running that
	 * just to populate a dashboard would be a side-effect-free page doing
	 * something that isn't side-effect-free.
	 *
	 * @param UgcIntegrationStatus $status Integration status.
	 */
	private function live_country( UgcIntegrationStatus $status ): ?string {
		if ( ! $status->is_available() || ! function_exists( 'universal_geo_get_country_code' ) ) {
			return null;
		}

		$country = universal_geo_get_country_code();

		if ( ! is_string( $country ) || '' === $country ) {
			return null;
		}

		$country = strtoupper( trim( $country ) );

		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : null;
	}

	/**
	 * Evaluates the currency routing policy against the live detected
	 * country. This is a preview of the policy only — it never touches a
	 * shopper's session, cookie, or manual selection.
	 *
	 * @param GeoDetectionSettings $geo     Current geo settings.
	 * @param string|null          $country Live detected country, if any.
	 */
	private function evaluate_live_country( GeoDetectionSettings $geo, ?string $country ): GeoRuleEvaluationResult {
		$evaluator = new GeoCurrencyRuleEvaluator( new GeoRegionRegistry() );

		return $evaluator->evaluate(
			(string) $country,
			$geo->rules(),
			$this->ui->selectable_codes(),
			$geo->fallback_currency(),
			$this->ui->base_code()
		);
	}

	/**
	 * Renders the integration-health card: connection state, simulation
	 * state, and a deep link — never a version number in the healthy state.
	 *
	 * @param UgcIntegrationStatus $status Integration status.
	 */
	private function ugc_integration_health_card( UgcIntegrationStatus $status ): string {
		$lines = array();

		if ( UgcIntegrationStatus::STATE_AVAILABLE === $status->state() ) {
			$lines[] = $status->is_simulating()
				? __( 'Simulation active', 'universal-multicurrency' )
				: __( 'Simulation inactive', 'universal-multicurrency' );
			$lines[] = __( 'Status healthy', 'universal-multicurrency' );
		} elseif ( UgcIntegrationStatus::STATE_MISCONFIGURED === $status->state() ) {
			$lines[] = sprintf(
				/* translators: %s: installed Universal Geo Context version */
				__( 'Installed version %s does not expose a compatible detection API', 'universal-multicurrency' ),
				$status->version() ?? __( 'unknown', 'universal-multicurrency' )
			);
		} elseif ( $this->ui->geo_settings()->allow_wc_geolocation_fallback() ) {
			$lines[] = __( 'Not installed — using the WooCommerce fallback', 'universal-multicurrency' );
		} else {
			$lines[] = __( 'Not installed — no automatic detection is active', 'universal-multicurrency' );
		}

		$badge_label = match ( $status->state() ) {
			UgcIntegrationStatus::STATE_AVAILABLE     => __( 'Universal Geo Context connected', 'universal-multicurrency' ),
			UgcIntegrationStatus::STATE_MISCONFIGURED => __( 'Needs attention', 'universal-multicurrency' ),
			default                                   => __( 'Not connected', 'universal-multicurrency' ),
		};

		return $this->components->provider_card(
			__( 'Visitor Location Provider', 'universal-multicurrency' ),
			implode( ' · ', $lines ),
			$badge_label,
			$status->state(),
			$this->ugc_action_link( $status, $status->overview_url(), __( 'Open Universal Geo Context', 'universal-multicurrency' ) )
		);
	}

	/**
	 * Renders the detected-country / matched-rule / resulting-currency card.
	 *
	 * @param UgcIntegrationStatus    $status   Integration status.
	 * @param GeoDetectionSettings    $geo      Current geo settings.
	 * @param string|null             $country  Live detected country.
	 * @param GeoRuleEvaluationResult $decision Evaluated routing decision.
	 */
	private function detection_and_outcome_card( UgcIntegrationStatus $status, GeoDetectionSettings $geo, ?string $country, GeoRuleEvaluationResult $decision ): string {
		if ( null === $country && ! $status->is_available() ) {
			return $this->components->empty_state(
				'dashicons-location-alt',
				__( 'No detection provider available', 'universal-multicurrency' ),
				__( 'Install Universal Geo Context for automatic country detection, or enable the WooCommerce fallback on Currency Routing.', 'universal-multicurrency' )
			);
		}

		$countries    = $this->ui->countries();
		$country_text = null !== $country ? $this->country_label( $country, $countries ) : __( 'Not determined', 'universal-multicurrency' );
		$rule_text    = null !== $decision->matched_rule_label()
			? $decision->matched_rule_label()
			: ( $decision->technical_fallback_used() ? __( 'Technical fallback', 'universal-multicurrency' ) : __( 'No match', 'universal-multicurrency' ) );

		$html  = sprintf( '<p class="umc-ui-field-row__description">%s</p>', esc_html__( 'Detected for your admin session — storefront visitors are resolved individually.', 'universal-multicurrency' ) );
		$html .= $this->components->status_badge(
			sprintf(
				/* translators: %s: detected country label */
				__( 'Country: %s', 'universal-multicurrency' ),
				$country_text
			),
			null !== $country ? 'active' : 'warning'
		);
		$html .= ' ';
		$html .= $this->components->status_badge(
			sprintf(
				/* translators: %s: matched routing rule */
				__( 'Rule: %s', 'universal-multicurrency' ),
				$rule_text
			),
			'recommended'
		);
		$html .= ' ';
		$html .= $this->components->status_badge(
			sprintf(
				/* translators: %s: resulting currency code */
				__( 'Currency: %s', 'universal-multicurrency' ),
				(string) $decision->currency()
			),
			'active'
		);

		if ( ! $geo->is_enabled() ) {
			$html .= $this->components->info_panel(
				'',
				__( 'This is a preview of your routing policy. Automatic currency detection itself is currently disabled — see Currency Routing.', 'universal-multicurrency' )
			);
		}

		return $html;
	}

	/**
	 * Builds the "needs attention" warning list — empty when everything is healthy.
	 *
	 * @param UgcIntegrationStatus    $status   Integration status.
	 * @param GeoDetectionSettings    $geo      Current geo settings.
	 * @param GeoRuleEvaluationResult $decision Evaluated routing decision.
	 *
	 * @return list<string>
	 */
	private function needs_attention( UgcIntegrationStatus $status, GeoDetectionSettings $geo, GeoRuleEvaluationResult $decision ): array {
		$attention = array();

		if ( $geo->is_enabled() && array() === $geo->rules() ) {
			$attention[] = __( 'Automatic detection is enabled but no routing rules are configured. Visitors will always receive the fallback currency.', 'universal-multicurrency' );
		}

		if ( ! $status->is_available() && ! $geo->allow_wc_geolocation_fallback() ) {
			$attention[] = __( 'No detection provider is available: Universal Geo Context is not installed and the WooCommerce fallback is disabled.', 'universal-multicurrency' );
		}

		foreach ( $decision->warnings() as $warning ) {
			$attention[] = $warning;
		}

		if ( $status->is_simulating() ) {
			$attention[] = __( 'Universal Geo Context is simulating a country for your admin session. Turn off simulation there when you are done testing.', 'universal-multicurrency' );
		}

		return $attention;
	}

	/**
	 * Builds the Quick actions list, capability- and availability-gated for
	 * every Universal Geo Context deep link.
	 *
	 * @param UgcIntegrationStatus $status Integration status.
	 *
	 * @return list<array<string, string>>
	 */
	private function quick_actions( UgcIntegrationStatus $status ): array {
		$actions   = array();
		$actions[] = array(
			'label'       => __( 'Manage currency routing rules', 'universal-multicurrency' ),
			'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_DETECTION ),
			'description' => __( 'Edit the ordered country and region rules.', 'universal-multicurrency' ),
		);
		$actions[] = array(
			'label'       => __( 'Simulate a currency decision', 'universal-multicurrency' ),
			'url'         => GeoPanelRegistry::panel_url( GeoPanelRegistry::PANEL_SANDBOX ),
			'description' => __( 'Test routing safely without affecting live shoppers.', 'universal-multicurrency' ),
		);

		if ( ! current_user_can( 'manage_options' ) || ! $status->is_available() ) {
			return $actions;
		}

		$actions[] = array(
			'label'       => __( 'Simulate a country', 'universal-multicurrency' ),
			'url'         => $status->simulation_url(),
			'description' => __( 'Test detection in Universal Geo Context.', 'universal-multicurrency' ),
		);
		$actions[] = array(
			'label'       => __( 'Inspect current detection', 'universal-multicurrency' ),
			'url'         => $status->detection_url(),
			'description' => __( 'Open the Universal Geo Context Detection Inspector.', 'universal-multicurrency' ),
		);
		$actions[] = array(
			'label'       => __( 'Manage providers', 'universal-multicurrency' ),
			'url'         => $status->providers_url(),
			'description' => __( 'Configure detection providers in Universal Geo Context.', 'universal-multicurrency' ),
		);
		$actions[] = array(
			'label'       => __( 'View geo diagnostics', 'universal-multicurrency' ),
			'url'         => $status->diagnostics_url(),
			'description' => __( 'Open detailed detection diagnostics.', 'universal-multicurrency' ),
		);

		return $actions;
	}

	/**
	 * Renders the collapsed "Technical details" section: the provider,
	 * source, confidence, and version facts merchants rarely need but
	 * support conversations do.
	 *
	 * @param UgcIntegrationStatus    $status   Integration status.
	 * @param string|null             $country  Live detected country.
	 * @param GeoRuleEvaluationResult $decision Evaluated routing decision.
	 */
	private function technical_details( UgcIntegrationStatus $status, ?string $country, GeoRuleEvaluationResult $decision ): string {
		$source     = ( $status->is_available() && function_exists( 'universal_geo_get_source' ) ) ? (string) universal_geo_get_source() : __( 'none', 'universal-multicurrency' );
		$confidence = ( $status->is_available() && function_exists( 'universal_geo_get_confidence' ) ) ? (string) universal_geo_get_confidence() : '—';

		$rows   = array();
		$rows[] = array( __( 'Provider order', 'universal-multicurrency' ), __( 'Universal Geo Context, then WooCommerce fallback', 'universal-multicurrency' ) );
		$rows[] = array( __( 'Detection source', 'universal-multicurrency' ), $source );
		$rows[] = array( __( 'Confidence', 'universal-multicurrency' ), $confidence );
		$rows[] = array( __( 'Detected country', 'universal-multicurrency' ), $country ?? __( 'none', 'universal-multicurrency' ) );
		$rows[] = array( __( 'Matched rule index', 'universal-multicurrency' ), null !== $decision->matched_rule_index() ? (string) ( $decision->matched_rule_index() + 1 ) : __( 'n/a', 'universal-multicurrency' ) );
		$rows[] = array( __( 'Universal Geo Context version', 'universal-multicurrency' ), $status->version() ?? __( 'not installed', 'universal-multicurrency' ) );
		$rows[] = array( __( 'Universal Geo Context API version', 'universal-multicurrency' ), null !== $status->api_version() ? (string) $status->api_version() : __( 'n/a', 'universal-multicurrency' ) );

		$items = '';

		foreach ( $rows as $row ) {
			$items .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $row[0] ), esc_html( $row[1] ) );
		}

		return sprintf(
			'<details class="umc-geo-technical-details"><summary>%s</summary><ul>%s</ul></details>',
			esc_html__( 'Technical details', 'universal-multicurrency' ),
			$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html() above.
		);
	}

	/**
	 * Optional capability- and availability-gated deep-link action markup.
	 *
	 * @param UgcIntegrationStatus $status Integration status.
	 * @param string               $url    Target URL.
	 * @param string               $label  Link label.
	 */
	private function ugc_action_link( UgcIntegrationStatus $status, string $url, string $label ): string {
		if ( ! $status->is_available() || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf( '<a href="%s">%s →</a>', esc_url( $url ), esc_html( $label ) );
	}
}
