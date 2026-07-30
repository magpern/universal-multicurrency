<?php
/**
 * Recommended European geo rules admin action.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettings;
use UMC\Geo\GeoRoutingRule;
use UMC\Geo\RecommendedGeoRules;
use UMC\Settings;

/**
 * Inserts recommended routing rules via admin-post.
 */
final class GeoRecommendedRulesController {

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
	 * Constructs the recommended rules controller.
	 *
	 * @param Settings $settings Settings store.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings = $settings;
		$this->registry = new CurrencyRegistry( $settings, $base );
	}

	/**
	 * Registers the admin-post handler.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_geo_add_recommended_rules', array( $this, 'handle' ) );
	}

	/**
	 * Handles the recommended-rules action.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to modify geo routing rules.', 'universal-multicurrency' ) );
		}

		check_admin_referer( 'umc_geo_add_recommended_rules' );

		$selectable = $this->registry->get_selectable_codes();
		$built      = RecommendedGeoRules::build_selectable_rules( $selectable );
		$data       = $this->settings->get();
		$geo        = GeoDetectionSettings::from_array( is_array( $data['geo'] ?? null ) ? $data['geo'] : array() );
		$existing   = $geo->rules();

		$existing_keys = array();

		foreach ( $existing as $rule ) {
			$key                   = $rule->type() . ':' . $rule->value() . ':' . $rule->currency();
			$existing_keys[ $key ] = true;
		}

		$has_other = false;
		$rules     = array();

		foreach ( $existing as $rule ) {
			if ( GeoRoutingRule::TYPE_OTHER === $rule->type() ) {
				$has_other = true;
			}
			$rules[] = $rule->to_array();
		}

		$added = 0;

		foreach ( $built['added'] as $row ) {
			$key = $row['type'] . ':' . $row['value'] . ':' . $row['currency'];

			if ( isset( $existing_keys[ $key ] ) ) {
				continue;
			}

			if ( GeoRoutingRule::TYPE_OTHER === $row['type'] && $has_other ) {
				continue;
			}

			if ( GeoRoutingRule::TYPE_OTHER === $row['type'] ) {
				$has_other = true;
			}

			$rules[]               = $row;
			$existing_keys[ $key ] = true;
			++$added;
		}

		if ( $has_other ) {
			$other = null;
			$rest  = array();

			foreach ( $rules as $row ) {
				if ( GeoRoutingRule::TYPE_OTHER === ( $row['type'] ?? '' ) ) {
					$other = $row;
					continue;
				}

				$rest[] = $row;
			}

			$rules = $rest;

			if ( null !== $other ) {
				$rules[] = $other;
			}
		}

		$geo_array          = $geo->to_array();
		$geo_array['rules'] = $rules;
		$data['geo']        = GeoDetectionSettings::sanitize_raw( $geo_array );

		$this->settings->save( $data );

		$message = sprintf(
			/* translators: %d: number of rules added */
			_n( 'Added %d recommended routing rule.', 'Added %d recommended routing rules.', $added, 'universal-multicurrency' ),
			$added
		);

		if ( array() !== $built['skipped'] ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated currency codes */
				__( '%s were skipped because those currencies are not enabled.', 'universal-multicurrency' ),
				implode( ', ', $built['skipped'] )
			);
		}

		$this->redirect_notice( $message, $added > 0 ? 'success' : 'warning' );
	}

	/**
	 * Redirects back to geo settings with an admin notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type.
	 */
	private function redirect_notice( string $message, string $type ): void {
		$url = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'umc',
				'section' => SettingsPage::SECTION_GEO_DETECTION,
				'umc_msg' => rawurlencode( $message ),
				'umc_typ' => $type,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
