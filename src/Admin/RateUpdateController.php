<?php
/**
 * Manual rate-update admin controller.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Rates\RateFetchResult;
use UMC\Rates\RateUpdateService;
use UMC\Rates\UpdateInProgressException;

/**
 * Handles synchronous admin-post rate refresh actions.
 */
final class RateUpdateController {

	/**
	 * Rate update orchestration service.
	 *
	 * @var RateUpdateService
	 */
	private RateUpdateService $service;

	/**
	 * Binds the controller to the update service.
	 *
	 * @param RateUpdateService $service Rate update orchestration service.
	 */
	public function __construct( RateUpdateService $service ) {
		$this->service = $service;
	}

	/**
	 * Registers the admin-post handler.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_update_rates', array( $this, 'handle' ) );
	}

	/**
	 * Handles a manual rate-update request.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			wp_die( esc_html__( 'You do not have permission to update exchange rates.', 'universal-multicurrency' ) );
		}

		check_admin_referer( 'umc_update_rates' );

		$scope = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['scope'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
		$code  = isset( $_GET['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		try {
			$result = 'all' === $scope ? $this->service->update( null ) : $this->service->update( array( $code ) );
			$this->redirect_with_notice( $this->message_for_result( $result ), 'success' );
		} catch ( UpdateInProgressException $exception ) {
			unset( $exception );
			$this->redirect_with_notice( __( 'A rate update is already in progress. Try again shortly.', 'universal-multicurrency' ), 'warning' );
		}
	}

	/**
	 * Builds the admin notice message for a fetch result.
	 *
	 * @param RateFetchResult $result Fetch outcome.
	 */
	private function message_for_result( RateFetchResult $result ): string {
		if ( $result->is_not_modified() ) {
			return __( 'Rates are already up to date.', 'universal-multicurrency' );
		}

		if ( $result->is_total_failure() ) {
			return __( 'Rate update failed. Last known rates were preserved.', 'universal-multicurrency' );
		}

		if ( $result->is_partial_failure() ) {
			return __( 'Rates were partially updated. Check currency status for details.', 'universal-multicurrency' );
		}

		return __( 'Exchange rates updated successfully.', 'universal-multicurrency' );
	}

	/**
	 * Redirects back to the settings tab with a flash notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Notice type (`success` or `warning`).
	 */
	private function redirect_with_notice( string $message, string $type ): void {
		$redirect = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'umc',
				'umc_msg' => rawurlencode( $message ),
				'umc_typ' => $type,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
