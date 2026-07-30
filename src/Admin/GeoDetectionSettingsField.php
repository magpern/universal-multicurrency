<?php
/**
 * Geo Detection settings admin field.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\GeoRoutingRule;
use UMC\Settings;

/**
 * Renders the Geo Detection settings section.
 */
final class GeoDetectionSettingsField {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Store base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Display controls renderer.
	 *
	 * @var DisplayControlRenderer
	 */
	private DisplayControlRenderer $controls;

	/**
	 * Region registry.
	 *
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $regions;

	/**
	 * Constructs the geo settings field renderer.
	 *
	 * @param Settings                    $settings  Settings store.
	 * @param Currency                    $base      Base currency.
	 * @param CurrencyRegistry            $registry  Currency registry.
	 * @param DisplayControlRenderer|null $controls  Controls renderer.
	 * @param GeoRegionRegistry|null      $regions   Region registry.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		CurrencyRegistry $registry,
		?DisplayControlRenderer $controls = null,
		?GeoRegionRegistry $regions = null
	) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->registry = $registry;
		$this->controls = $controls ?? new DisplayControlRenderer();
		$this->regions  = $regions ?? new GeoRegionRegistry();
	}

	/**
	 * Renders the Geo Detection settings field.
	 */
	public function render(): void {
		$geo             = GeoDetectionSettings::from_array( $this->settings->get()['geo'] ?? array() );
		$selectable      = $this->registry->get_selectable_codes();
		$countries       = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
		$simulation      = $this->simulation_output();
		$display_url     = admin_url( 'admin.php?page=wc-settings&tab=umc&section=display' );
		$currencies_url  = admin_url( 'admin.php?page=wc-settings&tab=umc&section=currencies' );
		$compat_url      = admin_url( 'admin.php?page=wc-settings&tab=umc&section=compatibility' );
		$recommended_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_geo_add_recommended_rules' ),
			'umc_geo_add_recommended_rules'
		);

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-geo-settings" colspan="2">
				<div class="umc-display-card umc-geo-card" data-umc-geo-root>
					<div aria-live="polite" class="screen-reader-text" data-umc-geo-live></div>

					<h3 class="umc-display-card__title"><?php esc_html_e( 'Automatic currency selection', 'universal-multicurrency' ); ?></h3>
					<label class="umc-display-toggle-row" for="umc_geo_enabled">
						<input type="checkbox" name="umc_geo[enabled]" id="umc_geo_enabled" value="1" <?php checked( $geo->is_enabled() ); ?> />
						<span><?php esc_html_e( 'Enable automatic currency detection', 'universal-multicurrency' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Suggest a currency from the visitor\'s country on eligible visits.', 'universal-multicurrency' ); ?></p>

					<fieldset class="umc-display-fieldset umc-geo-modes">
						<legend class="screen-reader-text"><?php esc_html_e( 'Detection timing', 'universal-multicurrency' ); ?></legend>
						<?php
						$modes = array(
							GeoDetectionSettings::MODE_FIRST_VISIT   => __( 'First eligible visit', 'universal-multicurrency' ),
							GeoDetectionSettings::MODE_SESSION       => __( 'Once per session', 'universal-multicurrency' ),
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
					<p class="umc-geo-note"><?php esc_html_e( 'Manual currency selection always takes precedence over automatic detection.', 'universal-multicurrency' ); ?></p>
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
					<?php $this->render_provider_status(); ?>
					<label class="umc-display-toggle-row" for="umc_geo_wc_fallback">
						<input type="checkbox" name="umc_geo[allow_wc_geolocation_fallback]" id="umc_geo_wc_fallback" value="1" <?php checked( $geo->allow_wc_geolocation_fallback() ); ?> />
						<span><?php esc_html_e( 'Use WooCommerce geolocation when Universal Geo Context is unavailable', 'universal-multicurrency' ); ?></span>
					</label>

					<h3 class="umc-display-card__title"><?php esc_html_e( 'Geographic routing', 'universal-multicurrency' ); ?></h3>
					<p class="umc-geo-routing-intro"><strong><?php esc_html_e( 'Rules are evaluated from top to bottom. The first matching rule determines the visitor\'s currency.', 'universal-multicurrency' ); ?></strong></p>

					<ol class="umc-geo-rules" data-umc-geo-rules>
						<?php
						$index = 0;
						foreach ( $geo->rules() as $rule ) {
							++$index;
							$this->render_rule_row( $rule, $index, count( $geo->rules() ), $countries, $selectable, $geo->rules() );
						}
						?>
					</ol>

					<p class="umc-geo-rule-actions">
						<button type="button" class="button" data-umc-geo-add="country"><?php esc_html_e( 'Add country rule', 'universal-multicurrency' ); ?></button>
						<button type="button" class="button" data-umc-geo-add="region"><?php esc_html_e( 'Add region rule', 'universal-multicurrency' ); ?></button>
						<button type="button" class="button" data-umc-geo-add="other"><?php esc_html_e( 'Add catch-all rule', 'universal-multicurrency' ); ?></button>
						<a class="button button-secondary" href="<?php echo esc_url( $recommended_url ); ?>"><?php esc_html_e( 'Add recommended European rules', 'universal-multicurrency' ); ?></a>
					</p>

					<template id="umc-geo-rule-template">
						<?php $this->render_rule_row_template( $countries, $selectable ); ?>
					</template>

					<h3 class="umc-display-card__title"><?php esc_html_e( 'Technical fallback', 'universal-multicurrency' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Other countries handles valid countries unmatched by earlier rules. The technical fallback handles missing or invalid country context.', 'universal-multicurrency' ); ?></p>
					<label for="umc_geo_fallback_currency"><?php esc_html_e( 'Technical fallback currency', 'universal-multicurrency' ); ?></label>
					<select name="umc_geo[fallback_currency]" id="umc_geo_fallback_currency">
						<option value=""><?php echo esc_html( sprintf( /* translators: %s: store base currency code */ __( 'Store default (%s)', 'universal-multicurrency' ), $this->base->code() ) ); ?></option>
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

					<h3 class="umc-display-card__title"><?php esc_html_e( 'Test detection', 'universal-multicurrency' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="umc_geo_simulate" />
						<?php wp_nonce_field( 'umc_geo_simulate' ); ?>
						<label for="umc_geo_sim_country"><?php esc_html_e( 'Simulated country', 'universal-multicurrency' ); ?></label>
						<select name="sim_country" id="umc_geo_sim_country">
							<?php foreach ( $countries as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p>
							<label><input type="checkbox" name="sim_explicit" value="1" /> <?php esc_html_e( 'Explicit currency exists', 'universal-multicurrency' ); ?></label>
							<label><input type="checkbox" name="sim_session" value="1" /> <?php esc_html_e( 'Session currency exists', 'universal-multicurrency' ); ?></label>
							<label><input type="checkbox" name="sim_cookie" value="1" /> <?php esc_html_e( 'Cookie currency exists', 'universal-multicurrency' ); ?></label>
							<label><input type="checkbox" name="sim_checkout_locked" value="1" /> <?php esc_html_e( 'Checkout locked', 'universal-multicurrency' ); ?></label>
						</p>
						<button type="submit" class="button"><?php esc_html_e( 'Run simulation', 'universal-multicurrency' ); ?></button>
					</form>
					<?php if ( '' !== $simulation ) : ?>
						<pre class="umc-geo-simulation-output"><?php echo esc_html( $simulation ); ?></pre>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses geo settings from POST (delegates to parser).
	 *
	 * @return array<string, mixed>|null Null when validation failed.
	 */
	public function parse_post(): ?array {
		$parser = new GeoDetectionSettingsParser( $this->settings, $this->registry );

		return $parser->parse_post();
	}

	/**
	 * Renders one geographic routing rule row.
	 *
	 * @param GeoRoutingRule             $rule       Rule.
	 * @param int                        $position   1-based position.
	 * @param int                        $total      Total rules.
	 * @param array<string,string>       $countries  WC countries.
	 * @param array<int,string>          $selectable Selectable currencies.
	 * @param array<int, GeoRoutingRule> $all_rules  All rules for shadow hints.
	 */
	private function render_rule_row( GeoRoutingRule $rule, int $position, int $total, array $countries, array $selectable, array $all_rules ): void {
		$prefix = 'umc_geo[rules][' . esc_attr( $rule->id() ) . ']';
		?>
		<li class="umc-geo-rule" data-umc-geo-rule data-rule-id="<?php echo esc_attr( $rule->id() ); ?>">
			<input type="hidden" name="umc_geo_rules_order[]" value="<?php echo esc_attr( $rule->id() ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $rule->id() ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $rule->type() ); ?>" data-umc-geo-type />
			<span class="umc-geo-rule__position"><?php echo esc_html( (string) $position ); ?></span>
			<span class="umc-geo-rule__badge"><?php echo esc_html( $this->type_label( $rule->type() ) ); ?></span>
			<span class="umc-geo-rule__match">
				<?php $this->render_match_control( $rule, $prefix, $countries ); ?>
			</span>
			<span class="umc-geo-rule__currency">
				<select name="<?php echo esc_attr( $prefix ); ?>[currency]">
					<?php foreach ( $selectable as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $rule->currency(), $code ); ?>><?php echo esc_html( $code ); ?></option>
					<?php endforeach; ?>
				</select>
			</span>
			<span class="umc-geo-rule__actions">
				<button type="button" class="button button-small" data-umc-geo-move="up" <?php disabled( 1 === $position ); ?> aria-label="<?php esc_attr_e( 'Move up', 'universal-multicurrency' ); ?>"><?php esc_html_e( 'Move up', 'universal-multicurrency' ); ?></button>
				<button type="button" class="button button-small" data-umc-geo-move="down" <?php disabled( $position === $total ); ?> aria-label="<?php esc_attr_e( 'Move down', 'universal-multicurrency' ); ?>"><?php esc_html_e( 'Move down', 'universal-multicurrency' ); ?></button>
				<button type="button" class="button button-small" data-umc-geo-remove aria-label="<?php esc_attr_e( 'Remove rule', 'universal-multicurrency' ); ?>"><?php esc_html_e( 'Remove', 'universal-multicurrency' ); ?></button>
			</span>
			<?php echo $this->shadow_warning_markup( $rule, $position - 1, $all_rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in method. ?>
		</li>
		<?php
	}

	/**
	 * Renders the client-side rule row template.
	 *
	 * @param array<string,string> $countries  Countries.
	 * @param array<int,string>    $selectable Currencies.
	 */
	private function render_rule_row_template( array $countries, array $selectable ): void {
		$id   = GeoRoutingRule::generate_id();
		$rule = GeoRoutingRule::from_array(
			array(
				'id'       => $id,
				'type'     => GeoRoutingRule::TYPE_COUNTRY,
				'value'    => 'SE',
				'currency' => $selectable[0] ?? $this->base->code(),
			)
		);

		if ( null === $rule ) {
			return;
		}

		$this->render_rule_row( $rule, 0, 0, $countries, $selectable, array() );
	}

	/**
	 * Renders the match control for a rule row.
	 *
	 * @param GeoRoutingRule       $rule      Rule.
	 * @param string               $prefix    Input prefix.
	 * @param array<string,string> $countries Countries.
	 */
	private function render_match_control( GeoRoutingRule $rule, string $prefix, array $countries ): void {
		if ( GeoRoutingRule::TYPE_OTHER === $rule->type() ) {
			echo '<span>' . esc_html__( 'Other countries', 'universal-multicurrency' ) . '</span>';
			echo '<input type="hidden" name="' . esc_attr( $prefix ) . '[value]" value="" />';
			return;
		}

		if ( GeoRoutingRule::TYPE_REGION === $rule->type() ) {
			echo '<select name="' . esc_attr( $prefix ) . '[value]">';
			foreach ( $this->region_options() as $id => $label ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $id ), selected( $rule->value(), $id, false ), esc_html( $label ) );
			}
			echo '</select>';
			printf(
				'<button type="button" class="button-link" data-umc-geo-region-preview="%s">%s</button>',
				esc_attr( $rule->value() ),
				esc_html__( 'View countries', 'universal-multicurrency' )
			);
			return;
		}

		echo '<select name="' . esc_attr( $prefix ) . '[value]">';
		foreach ( $countries as $code => $name ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $rule->value(), $code, false ), esc_html( $name ) );
		}
		echo '</select>';
	}

	/**
	 * Region preset options for rule rows.
	 *
	 * @return array<string,string>
	 */
	private function region_options(): array {
		return array(
			GeoRegionRegistry::REGION_EU       => __( 'European Union', 'universal-multicurrency' ),
			GeoRegionRegistry::REGION_EUROZONE => __( 'Eurozone', 'universal-multicurrency' ),
			GeoRegionRegistry::REGION_EEA      => __( 'European Economic Area', 'universal-multicurrency' ),
		);
	}

	/**
	 * Localized label for a rule type.
	 *
	 * @param string $type Rule type identifier.
	 */
	private function type_label( string $type ): string {
		if ( GeoRoutingRule::TYPE_REGION === $type ) {
			return __( 'Region', 'universal-multicurrency' );
		}

		if ( GeoRoutingRule::TYPE_OTHER === $type ) {
			return __( 'Other', 'universal-multicurrency' );
		}

		return __( 'Country', 'universal-multicurrency' );
	}

	/**
	 * Renders detection provider availability status.
	 */
	private function render_provider_status(): void {
		$ugc = function_exists( 'universal_geo_get_country_code' ) && function_exists( 'universal_geo_api_version' );
		echo '<ul class="umc-geo-provider-status">';
		echo '<li>' . esc_html( $ugc ? __( 'Universal Geo Context: available', 'universal-multicurrency' ) : __( 'Universal Geo Context: not installed', 'universal-multicurrency' ) ) . '</li>';
		echo '<li>' . esc_html__( 'WooCommerce fallback: available when enabled below', 'universal-multicurrency' ) . '</li>';
		echo '</ul>';
	}

	/**
	 * Renders shadow warning markup when a country rule is unreachable.
	 *
	 * @param GeoRoutingRule             $rule      Rule being rendered.
	 * @param int                        $index     Zero-based index.
	 * @param array<int, GeoRoutingRule> $all_rules All rules for shadow detection.
	 */
	private function shadow_warning_markup( GeoRoutingRule $rule, int $index, array $all_rules ): string {
		if ( GeoRoutingRule::TYPE_COUNTRY !== $rule->type() || $index <= 0 ) {
			return '';
		}

		for ( $i = 0; $i < $index; ++$i ) {
			$earlier = $all_rules[ $i ] ?? null;

			if ( ! $earlier instanceof GeoRoutingRule || GeoRoutingRule::TYPE_REGION !== $earlier->type() ) {
				continue;
			}

			if ( $this->regions->contains( $rule->value(), $earlier->value() ) ) {
				return '<p class="umc-geo-shadow-warning">' . esc_html(
					sprintf(
						/* translators: 1: country code, 2: region id */
						__( 'The %1$s rule will never be reached because an earlier %2$s rule already matches %1$s.', 'universal-multicurrency' ),
						$rule->value(),
						$earlier->value()
					)
				) . '</p>';
			}
		}

		return '';
	}

	/**
	 * Reads simulation output from the query string.
	 */
	private function simulation_output(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['umc_geo_sim'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return sanitize_textarea_field( wp_unslash( (string) $_GET['umc_geo_sim'] ) );
	}
}
