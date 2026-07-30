<?php
/**
 * Geo Sandbox admin controller.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Admin\Geo\GeoSandboxRecentStore;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoContextSerializer;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoCurrencyRuleEvaluator;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\GeoRegionRegistry;
use UMC\Geo\GeoSandboxService;
use UMC\Settings;

/**
 * Read-only Geo Sandbox runs via admin-post.
 */
final class GeoSandboxController {

	public const ACTION = 'umc_geo_sandbox_run';

	public const RESULT_META = 'umc_geo_sandbox_last_result';

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Store currency registry.
	 *
	 * @var CurrencyRegistry
	 */
	private CurrencyRegistry $registry;

	/**
	 * Geo Sandbox execution service.
	 *
	 * @var GeoSandboxService
	 */
	private GeoSandboxService $sandbox;

	/**
	 * Constructs the Geo Sandbox controller.
	 *
	 * @param Settings $settings Settings store.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings = $settings;
		$this->registry = new CurrencyRegistry( $settings, $base );
		$repository     = new GeoDetectionSettingsRepository( $settings );
		$this->sandbox  = new GeoSandboxService(
			new GeoCurrencyDecisionService(
				$repository,
				new GeoCurrencyRuleEvaluator( new GeoRegionRegistry() )
			),
			$this->registry,
			$repository
		);
	}

	/**
	 * Registers admin-post handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles sandbox POST.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to run the Geo Sandbox.', 'universal-multicurrency' ) );
		}

		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$post = isset( $_POST['umc_geo_sandbox'] ) && is_array( $_POST['umc_geo_sandbox'] ) ? wp_unslash( $_POST['umc_geo_sandbox'] ) : array();

		$selectable = $this->registry->get_selectable_codes();
		$sample     = $selectable[0] ?? $this->registry->get_base_code();
		$input      = GeoContextSerializer::from_sandbox_post( $post, $sample );
		$output     = $this->sandbox->run( $input );

		$country = $input->country_code();

		if ( is_string( $country ) && '' !== $country ) {
			( new GeoSandboxRecentStore() )->push( $country );
		}

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::RESULT_META, GeoContextSerializer::encode( $output->to_array() ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                      => 'wc-settings',
					'tab'                       => 'umc',
					'section'                   => GeoPanelRegistry::SECTION,
					GeoPanelRegistry::QUERY_VAR => GeoPanelRegistry::PANEL_SANDBOX,
					'umc_sandbox_ran'           => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Reads the last sandbox result for the current admin user.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function last_result_for_current_user(): ?array {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return null;
		}

		$stored = get_user_meta( $user_id, self::RESULT_META, true );

		if ( ! is_string( $stored ) || '' === $stored ) {
			return null;
		}

		$context = GeoContextSerializer::decode( $stored );

		return null === $context ? null : $context->to_array();
	}
}
