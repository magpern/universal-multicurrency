<?php
/**
 * Decision Inspector admin-post controller.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Settings;

/**
 * Stateless Decision Inspector runs via admin-post.
 *
 * Redirects back to the settings section with sanitized query args so the
 * explanation can be recomputed on render. No user-meta or transient storage.
 */
final class DecisionInspectorController {

	public const ACTION = 'umc_decision_inspect';

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Constructs the controller.
	 *
	 * @param Settings $settings Settings.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings = $settings;
		$this->base     = $base;
	}

	/**
	 * Registers admin-post handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles inspector POST and redirects with sanitized simulation args.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
			wp_die( esc_html__( 'You do not have permission to run Decision Inspector.', 'universal-multicurrency' ) );
		}

		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; sanitized in DecisionInspectorService::input_from_array().
		$post = isset( $_POST['umc_decision_inspector'] ) && is_array( $_POST['umc_decision_inspector'] ) ? wp_unslash( $_POST['umc_decision_inspector'] ) : array();

		$service = new DecisionInspectorService( $this->settings, $this->base );
		$input   = $service->input_from_array( is_array( $post ) ? $post : array() );

		$args = array(
			'page'                    => 'wc-settings',
			'tab'                     => 'umc',
			'section'                 => SettingsPage::SECTION_DECISION_INSPECTOR,
			'umc_inspected'           => '1',
			'umc_di_country'          => $input->country_code(),
			'umc_di_explicit'         => (string) ( $input->explicit_currency() ?? '' ),
			'umc_di_session'          => (string) ( $input->session_currency() ?? '' ),
			'umc_di_cookie'           => (string) ( $input->cookie_currency() ?? '' ),
			'umc_di_manual'           => $input->manual_selection() ? '1' : '0',
			'umc_di_origin'           => (string) ( $input->currency_origin() ?? '' ),
			'umc_di_geo_enabled'      => $input->geo_enabled() ? '1' : '0',
			'umc_di_checkout_locked'  => $input->checkout_locked() ? '1' : '0',
			'umc_di_include_checkout' => $input->include_checkout() ? '1' : '0',
			'umc_di_checkout_mode'    => $input->checkout_mode(),
			'umc_di_show_notice'      => $input->show_notice() ? '1' : '0',
			'umc_di_payment_required' => $input->payment_required() ? '1' : '0',
			'umc_di_gateway_supports' => $input->gateway_supports_display() ? '1' : '0',
			'umc_di_order_context'    => $input->order_context_active() ? '1' : '0',
		);

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
