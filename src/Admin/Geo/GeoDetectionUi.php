<?php
/**
 * Shared Geo Detection admin UI helpers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

use UMC\Admin\AdminComponentRenderer;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\GeoRoutingRule;
use UMC\Settings;

/**
 * Shared rendering helpers for Geo hub panels.
 */
final class GeoDetectionUi {

	/**
	 * Merchant settings store.
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
	 * Store currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Geographic region registry.
	 *
	 * @var GeoRegionRegistry
	 */
	private GeoRegionRegistry $regions;

	/**
	 * Constructs the shared Geo Detection UI helper.
	 *
	 * @param Settings          $settings Settings store.
	 * @param Currency          $base     Base currency.
	 * @param CurrencyRegistry  $registry Currency registry.
	 * @param GeoRegionRegistry $regions  Region registry.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		CurrencyRegistry $registry,
		GeoRegionRegistry $regions
	) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->registry = $registry;
		$this->regions  = $regions;
	}

	/**
	 * Current geo settings value object.
	 */
	public function geo_settings(): GeoDetectionSettings {
		return GeoDetectionSettings::from_array( $this->settings->get()['geo'] ?? array() );
	}

	/**
	 * Returns selectable currency codes for rule rows.
	 *
	 * @return array<int, string>
	 */
	public function selectable_codes(): array {
		return $this->registry->get_selectable_codes();
	}

	/**
	 * Returns WooCommerce country labels keyed by ISO code.
	 *
	 * @return array<string, string>
	 */
	public function countries(): array {
		return function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
	}

	/**
	 * Store base currency code.
	 */
	public function base_code(): string {
		return $this->base->code();
	}

	/**
	 * Returns the currency registry.
	 */
	public function registry(): CurrencyRegistry {
		return $this->registry;
	}

	/**
	 * Renders provider cards using the shared component renderer.
	 *
	 * @param AdminComponentRenderer $components Component renderer.
	 */
	public function render_provider_cards( AdminComponentRenderer $components ): void {
		$ugc_available = function_exists( 'universal_geo_get_country_code' ) && function_exists( 'universal_geo_api_version' );

		echo '<div class="umc-geo-provider-list">';
		echo $components->provider_card(
			__( 'Universal Geo Context', 'universal-multicurrency' ),
			__( 'Primary detection provider for visitor country resolution.', 'universal-multicurrency' ),
			$ugc_available ? __( 'Available', 'universal-multicurrency' ) : __( 'Missing', 'universal-multicurrency' ),
			$ugc_available ? 'available' : 'missing'
		);
		echo $components->provider_card(
			__( 'WooCommerce fallback', 'universal-multicurrency' ),
			__( 'Uses WooCommerce geolocation when Universal Geo Context is unavailable.', 'universal-multicurrency' ),
			__( 'Available', 'universal-multicurrency' ),
			'available'
		);
		echo '</div>';
	}

	/**
	 * Renders provider availability status list.
	 * @deprecated 0.13.0 Use render_provider_cards().
	 */
	public function render_provider_status(): void {
		$components = new AdminComponentRenderer();
		$this->render_provider_cards( $components );
	}

	/**
	 * Renders one routing rule row.
	 *
	 * @param GeoRoutingRule             $rule       Rule.
	 * @param int                        $position   1-based position.
	 * @param int                        $total      Total rules.
	 * @param array<string,string>       $countries  WC countries.
	 * @param array<int,string>          $selectable Selectable currencies.
	 * @param array<int, GeoRoutingRule> $all_rules  All rules.
	 */
	public function render_rule_row( GeoRoutingRule $rule, int $position, int $total, array $countries, array $selectable, array $all_rules ): void {
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
				<button type="button" class="button button-small" data-umc-geo-move="up" <?php disabled( 1 === $position ); ?>><?php esc_html_e( 'Move up', 'universal-multicurrency' ); ?></button>
				<button type="button" class="button button-small" data-umc-geo-move="down" <?php disabled( $position === $total ); ?>><?php esc_html_e( 'Move down', 'universal-multicurrency' ); ?></button>
				<button type="button" class="button button-small" data-umc-geo-remove><?php esc_html_e( 'Remove', 'universal-multicurrency' ); ?></button>
			</span>
			<?php echo $this->shadow_warning_markup( $rule, $position - 1, $all_rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</li>
		<?php
	}

	/**
	 * Renders the JS template row for new rules.
	 *
	 * @param array<string,string> $countries  Countries.
	 * @param array<int,string>    $selectable Currencies.
	 */
	public function render_rule_row_template( array $countries, array $selectable ): void {
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
	 * Renders the match control for one routing rule row.
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
			return;
		}

		echo '<select name="' . esc_attr( $prefix ) . '[value]">';
		foreach ( $countries as $code => $name ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $rule->value(), $code, false ), esc_html( $name ) );
		}
		echo '</select>';
	}

	/**
	 * Returns region preset options for rule rows.
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
	 * Returns a localized label for a rule type.
	 *
	 * @param string $type Rule type.
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
	 * Builds shadow-rule warning markup when a country rule is unreachable.
	 *
	 * @param GeoRoutingRule             $rule      Rule.
	 * @param int                        $index     Zero-based index.
	 * @param array<int, GeoRoutingRule> $all_rules All rules.
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
}
