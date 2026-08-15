<?php
/**
 * Fixed Pricing catalog operation execute handler.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceOperationResult;

/**
 * Handles the nonce-protected "Confirm" step of the Fixed Pricing preview →
 * confirm → execute flow (ADR-0029). Independently re-validates the nonce,
 * recomputes scope from submitted filter criteria (or re-checks the exact
 * submitted product IDs for a "checked" scope — never a stored/transient ID
 * list), re-checks per-product `edit_post` capability, re-enforces the scope
 * cap, and delegates the actual write to the shared
 * {@see FixedPriceCatalogOperationsService}. Never a second seed/clear
 * implementation.
 */
final class FixedPricingOperationController {

	/**
	 * Maximum products a single "all matching filter" execution may act on.
	 * Beyond this, the request is refused in favor of the CLI, which is not
	 * bounded in total catalog size.
	 */
	public const FILTERED_SCOPE_CAP = 500;

	public const SCOPE_CHECKED  = 'checked';
	public const SCOPE_FILTERED = 'filtered';

	public const ACTION_SEED  = 'seed';
	public const ACTION_CLEAR = 'clear';

	/**
	 * Binds the controller to its collaborators.
	 *
	 * @param FixedPriceCatalogOperationsService $service Shared seed/clear orchestration.
	 * @param FixedPriceCatalogQuery             $query   Shared classified catalog listing.
	 */
	public function __construct(
		private FixedPriceCatalogOperationsService $service,
		private FixedPriceCatalogQuery $query
	) {
	}

	/**
	 * Registers the admin-post execute handler.
	 */
	public function register(): void {
		add_action( 'admin_post_umc_fixed_pricing_execute', array( $this, 'handle' ) );
	}

	/**
	 * Handles the confirmed catalog operation request.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			wp_die( esc_html__( 'You do not have permission to manage fixed prices.', 'universal-multicurrency' ) );
		}

		check_admin_referer( 'umc_fixed_pricing_execute' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash applied per-field below.
		$post = $_POST;

		$action   = isset( $post['umc_fp_action'] ) ? sanitize_key( wp_unslash( (string) $post['umc_fp_action'] ) ) : '';
		$currency = isset( $post['umc_fp_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $post['umc_fp_currency'] ) ) ) : '';
		$scope    = isset( $post['umc_fp_scope'] ) ? sanitize_key( wp_unslash( (string) $post['umc_fp_scope'] ) ) : '';

		if ( ! in_array( $action, array( self::ACTION_SEED, self::ACTION_CLEAR ), true ) || '' === $currency ) {
			$this->redirect_with_notice( __( 'Invalid fixed pricing request.', 'universal-multicurrency' ), 'error' );
		}

		$resolution = $this->resolve_scope( $scope, $post );

		if ( null !== $resolution['error'] ) {
			$this->redirect_with_notice( $resolution['error'], 'error' );
		}

		$products = $resolution['products'];

		$result = self::ACTION_SEED === $action
			? $this->service->seed( $products, $currency )
			: $this->service->clear( $products, $currency );

		$this->redirect_with_notice(
			$this->message_for_result( $action, $result, $resolution['excluded'] ),
			$result->is_aborted() ? 'error' : 'success'
		);
	}

	/**
	 * Resolves the confirmed request into a concrete product scope.
	 *
	 * @param string               $scope One of the SCOPE_* constants.
	 * @param array<string, mixed> $post  Raw, unslashed-per-field $_POST.
	 * @return array{products: array<int, \WC_Product>, excluded: int, error: string|null}
	 */
	private function resolve_scope( string $scope, array $post ): array {
		if ( self::SCOPE_CHECKED === $scope ) {
			return $this->resolve_checked_scope( $post );
		}

		if ( self::SCOPE_FILTERED === $scope ) {
			return $this->resolve_filtered_scope( $post );
		}

		return array(
			'products' => array(),
			'excluded' => 0,
			'error'    => __( 'Invalid fixed pricing scope.', 'universal-multicurrency' ),
		);
	}

	/**
	 * Resolves an explicit, previewer-checked product ID list.
	 *
	 * @param array<string, mixed> $post Raw, unslashed-per-field $_POST.
	 * @return array{products: array<int, \WC_Product>, excluded: int, error: string|null}
	 */
	private function resolve_checked_scope( array $post ): array {
		$ids = isset( $post['product_ids'] ) && is_array( $post['product_ids'] )
			? array_map( 'absint', wp_unslash( $post['product_ids'] ) )
			: array();

		if ( array() === $ids ) {
			return array(
				'products' => array(),
				'excluded' => 0,
				'error'    => __( 'No products were selected.', 'universal-multicurrency' ),
			);
		}

		return array_merge( $this->load_and_authorize( $ids ), array( 'error' => null ) );
	}

	/**
	 * Recomputes the "all matching filter" scope from the submitted filter
	 * criteria at execute time — never from a resubmitted ID list.
	 *
	 * @param array<string, mixed> $post Raw, unslashed-per-field $_POST.
	 * @return array{products: array<int, \WC_Product>, excluded: int, error: string|null}
	 */
	private function resolve_filtered_scope( array $post ): array {
		$status   = isset( $post['umc_fp_status'] ) ? sanitize_key( wp_unslash( (string) $post['umc_fp_status'] ) ) : '';
		$search   = isset( $post['umc_fp_search'] ) ? sanitize_text_field( wp_unslash( (string) $post['umc_fp_search'] ) ) : '';
		$currency = isset( $post['umc_fp_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $post['umc_fp_currency'] ) ) ) : '';

		$classified = $this->query->classify_catalog( $currency, $status, $search, self::FILTERED_SCOPE_CAP );

		if ( $classified['truncated'] ) {
			return array(
				'products' => array(),
				'excluded' => 0,
				'error'    => sprintf(
					/* translators: %d: maximum number of products the admin screen can process in one operation */
					__( 'This filter matches more than %d products. Use `wp umc prices` on the command line for catalogs this size.', 'universal-multicurrency' ),
					self::FILTERED_SCOPE_CAP
				),
			);
		}

		$ids = array_map(
			static fn( array $row ): int => (int) $row['product']->get_id(),
			$classified['rows']
		);

		return array_merge( $this->load_and_authorize( $ids ), array( 'error' => null ) );
	}

	/**
	 * Loads products by ID and excludes any the current user cannot edit.
	 *
	 * @param array<int, int> $ids Product IDs.
	 * @return array{products: array<int, \WC_Product>, excluded: int}
	 */
	private function load_and_authorize( array $ids ): array {
		$products = array();
		$excluded = 0;

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				++$excluded;
				continue;
			}

			$product = wc_get_product( $id );

			if ( $product instanceof \WC_Product ) {
				$products[] = $product;
			}
		}

		return array(
			'products' => $products,
			'excluded' => $excluded,
		);
	}

	/**
	 * Builds the flash-notice message for a completed or aborted operation.
	 *
	 * @param string                    $action   ACTION_SEED or ACTION_CLEAR.
	 * @param FixedPriceOperationResult $result Operation outcome.
	 * @param int                       $excluded Products excluded by capability before execution.
	 */
	private function message_for_result( string $action, FixedPriceOperationResult $result, int $excluded ): string {
		if ( $result->is_aborted() ) {
			return match ( $result->abort_reason() ) {
				FixedPriceOperationResult::ABORT_BASE_CURRENCY    => __( 'The base currency cannot have fixed prices.', 'universal-multicurrency' ),
				FixedPriceOperationResult::ABORT_UNKNOWN_CURRENCY => __( 'That currency is not configured.', 'universal-multicurrency' ),
				FixedPriceOperationResult::ABORT_NO_RATE          => __( 'No exchange rate is available for that currency; nothing was seeded.', 'universal-multicurrency' ),
				default                                            => __( 'The fixed pricing operation could not be completed.', 'universal-multicurrency' ),
			};
		}

		$succeeded = count( $result->succeeded() );
		$skipped   = count( $result->skipped() );

		$message = self::ACTION_SEED === $action
			? sprintf(
				/* translators: 1: number of products/variations seeded, 2: exchange rate used */
				__( 'Seeded fixed prices for %1$d product(s)/variation(s) at rate %2$s.', 'universal-multicurrency' ),
				$succeeded,
				(string) $result->rate_used()
			)
			: sprintf(
				/* translators: %d: number of products/variations cleared */
				__( 'Cleared fixed prices for %d product(s)/variation(s).', 'universal-multicurrency' ),
				$succeeded
			);

		if ( $skipped > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of products/variations skipped */
				__( '%d were skipped (no authored source price, or nothing to clear).', 'universal-multicurrency' ),
				$skipped
			);
		}

		if ( $excluded > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: number of products excluded from the operation */
				__( '%d were excluded because you do not have permission to edit them.', 'universal-multicurrency' ),
				$excluded
			);
		}

		return $message;
	}

	/**
	 * Redirects back to the Fixed Pricing screen with a flash notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type    'success' or 'error'.
	 */
	private function redirect_with_notice( string $message, string $type ): void {
		$redirect = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'umc',
				'section' => SettingsPage::SECTION_FIXED_PRICING,
				'umc_msg' => rawurlencode( $message ),
				'umc_typ' => $type,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
