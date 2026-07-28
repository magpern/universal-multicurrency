<?php
/**
 * Currency row admin actions.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\Settings;

/**
 * Handles add/remove/toggle currency admin-post actions.
 */
final class CurrencyActionController {

	/**
	 * Creates the currency row action controller.
	 *
	 * @param Settings                 $settings Merchant settings store.
	 * @param Currency                 $base     Store base currency.
	 * @param CurrencyMetadataProvider $metadata Currency metadata provider.
	 */
	public function __construct(
		private Settings $settings,
		private Currency $base,
		private CurrencyMetadataProvider $metadata
	) {
	}

	/**
	 * Registers admin-post handlers.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_currency_add', array( $this, 'handle_add' ) );
		add_action( 'admin_post_umc_currency_remove', array( $this, 'handle_remove' ) );
		add_action( 'admin_post_umc_currency_toggle', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Adds a configured currency from WooCommerce metadata defaults.
	 */
	public function handle_add(): void {
		$this->assert_capability();
		check_admin_referer( 'umc_currency_add' );

		$code = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $this->is_addable( $code ) ) {
			$this->redirect_with_notice(
				__( 'That currency could not be added. It may already exist or is not recognised.', 'universal-multicurrency' ),
				'warning'
			);
		}

		$meta = $this->metadata->get( $code );

		if ( null === $meta ) {
			$this->redirect_with_notice(
				__( 'That currency could not be added. It may already exist or is not recognised.', 'universal-multicurrency' ),
				'warning'
			);
		}

		$current                        = $this->settings->get();
		$current['currencies'][ $code ] = array(
			'enabled'             => true,
			'symbol'              => $meta->symbol(),
			'position'            => $meta->position(),
			'decimals'            => $meta->decimals(),
			'manual_rate'         => '',
			'provider_rate'       => '',
			'merchant_adjustment' => '0',
			'rate_mode'           => '',
			'rate_updated_at'     => 0,
		);

		$this->settings->save( $current );

		$this->redirect_with_notice(
			__( 'Currency added.', 'universal-multicurrency' ),
			'success',
			array(
				'section'  => 'currencies',
				'umc_edit' => $code,
			)
		);
	}

	/**
	 * Removes a configured currency.
	 */
	public function handle_remove(): void {
		$this->assert_capability();
		check_admin_referer( 'umc_currency_remove' );

		$code = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $this->is_removable( $code ) ) {
			$this->redirect_with_notice(
				__( 'That currency could not be removed.', 'universal-multicurrency' ),
				'warning'
			);
		}

		$current = $this->settings->get();
		unset( $current['currencies'][ $code ] );
		$this->settings->save( $current );

		$this->redirect_with_notice(
			__( 'Currency removed.', 'universal-multicurrency' ),
			'success'
		);
	}

	/**
	 * Enables or disables a configured currency.
	 */
	public function handle_toggle(): void {
		$this->assert_capability();
		check_admin_referer( 'umc_currency_toggle' );

		$code   = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$config = $this->settings->get_currency_config( $code );

		if ( null === $config || $code === $this->base->code() ) {
			$this->redirect_with_notice(
				__( 'That currency could not be updated.', 'universal-multicurrency' ),
				'warning'
			);
		}

		$config['enabled']              = '1' === $state;
		$current                        = $this->settings->get();
		$current['currencies'][ $code ] = $config;
		$this->settings->save( $current );

		$this->redirect_with_notice(
			$config['enabled']
				? __( 'Currency enabled.', 'universal-multicurrency' )
				: __( 'Currency disabled.', 'universal-multicurrency' ),
			'success'
		);
	}

	/**
	 * Whether a currency code can be added.
	 *
	 * @param string $code ISO currency code.
	 */
	public function is_addable( string $code ): bool {
		$code = strtoupper( trim( $code ) );

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) ) {
			return false;
		}

		if ( $code === $this->base->code() ) {
			return false;
		}

		if ( null !== $this->settings->get_currency_config( $code ) ) {
			return false;
		}

		return $this->metadata->is_known( $code );
	}

	/**
	 * Whether a currency code can be removed.
	 *
	 * @param string $code ISO currency code.
	 */
	public function is_removable( string $code ): bool {
		$code = strtoupper( trim( $code ) );

		if ( $code === $this->base->code() ) {
			return false;
		}

		return null !== $this->settings->get_currency_config( $code );
	}

	/**
	 * Ensures the current user may manage WooCommerce settings.
	 */
	private function assert_capability(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			wp_die( esc_html__( 'You do not have permission to manage currencies.', 'universal-multicurrency' ) );
		}
	}

	/**
	 * Redirects back to the settings tab with a flash notice.
	 *
	 * @param string               $message Notice message.
	 * @param string               $type    Notice type.
	 * @param array<string, mixed> $extra   Additional query args.
	 */
	private function redirect_with_notice( string $message, string $type, array $extra = array() ): void {
		$redirect = add_query_arg(
			array_merge(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'umc',
					'section' => 'currencies',
					'umc_msg' => rawurlencode( $message ),
					'umc_typ' => $type,
				),
				$extra
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
