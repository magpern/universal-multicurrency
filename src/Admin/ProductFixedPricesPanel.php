<?php
/**
 * WooCommerce product editor fixed-price controls.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceDocumentMerger;
use UMC\Pricing\FixedPriceRepository;
use UMC\Settings;

/**
 * Renders and saves non-base fixed prices on simple products and variations.
 */
final class ProductFixedPricesPanel {

	/**
	 * Shared mutation authority (ADR-0030), built from the same repository
	 * this panel already receives.
	 *
	 * @var FixedPriceDocumentMerger
	 */
	private FixedPriceDocumentMerger $merger;

	/**
	 * Binds admin dependencies for fixed-price editing.
	 *
	 * @param Settings             $settings   Plugin settings.
	 * @param CurrencyRegistry     $registry   Currency registry.
	 * @param FixedPriceRepository $repository Fixed price persistence.
	 */
	public function __construct(
		private Settings $settings,
		private CurrencyRegistry $registry,
		private FixedPriceRepository $repository
	) {
		$this->merger = new FixedPriceDocumentMerger( $repository );
	}

	/**
	 * Registers product editor hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_simple_panel' ), 25 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_product' ), 20, 1 );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 25, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 20, 2 );
	}

	/**
	 * Renders fixed-price fields on the General tab for simple products.
	 */
	public function render_simple_panel(): void {
		global $product_object;

		if ( ! $product_object instanceof \WC_Product || $product_object->is_type( 'variable' ) ) {
			return;
		}

		echo '<div class="options_group umc-fixed-prices-panel">';
		$this->render_fields_for_product( (int) $product_object->get_id(), 'simple' );
		echo '</div>';
	}

	/**
	 * Renders fixed-price fields on a variation row.
	 *
	 * @param int   $loop           Variation loop index.
	 * @param array $variation_data Variation data.
	 * @param mixed $variation      Variation post object.
	 */
	public function render_variation_fields( int $loop, array $variation_data, $variation ): void {
		unset( $variation_data );

		$variation_id = is_object( $variation ) ? (int) $variation->ID : 0;

		if ( $variation_id <= 0 ) {
			return;
		}

		echo '<div class="umc-fixed-prices-variation-panel form-row form-row-full">';
		$this->render_fields_for_product( $variation_id, 'variation', $loop );
		echo '</div>';
	}

	/**
	 * Outputs fixed-price inputs for one product or variation.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $context    simple|variation.
	 * @param int    $loop       Variation loop index when applicable.
	 */
	private function render_fields_for_product( int $product_id, string $context, int $loop = 0 ): void {
		$document = $this->repository->get( $product_id );
		$base     = $this->registry->get_base_code();
		$prefix   = 'variation' === $context ? "umc_fixed_prices_var[$loop]" : 'umc_fixed_prices';

		echo '<p class="form-field"><strong>' . esc_html__( 'Multicurrency fixed prices', 'universal-multicurrency' ) . '</strong></p>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: store base currency code */
				__( 'Base prices remain in WooCommerce (%s). Leave blank to use automatic conversion. Fixed sale amounts follow the product\'s WooCommerce sale schedule.', 'universal-multicurrency' ),
				$base
			)
		) . '</p>';

		foreach ( $this->registry->get_currencies() as $currency ) {
			$code = $currency->code();

			if ( $this->registry->is_base( $code ) ) {
				continue;
			}

			$entry    = $document->get_currency( $code );
			$regular  = null !== $entry ? $entry->regular() : '';
			$sale     = null !== $entry ? $entry->sale() : '';
			$disabled = ! $currency->is_enabled();
			$name_reg = $prefix . "[{$code}][regular]";
			$name_sal = $prefix . "[{$code}][sale]";
			$readonly = $disabled ? ' readonly="readonly"' : '';

			printf(
				'<p class="form-field umc-fixed-price-row%s"><label>%s</label>',
				$disabled ? ' umc-fixed-price-row--inactive' : '',
				esc_html( $code ) . ( $disabled ? ' <span class="description">(' . esc_html__( 'currency disabled', 'universal-multicurrency' ) . ')</span>' : '' )
			);
			printf(
				'<input type="text" class="short wc_input_price" name="%s" value="%s" placeholder="%s"%s /> ',
				esc_attr( $name_reg ),
				esc_attr( $regular ),
				esc_attr__( 'Auto convert', 'universal-multicurrency' ),
				$readonly // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			printf(
				'<input type="text" class="short wc_input_price" name="%s" value="%s" placeholder="%s"%s />',
				esc_attr( $name_sal ),
				esc_attr( $sale ),
				esc_attr__( 'Auto convert', 'universal-multicurrency' ),
				$readonly // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			echo '</p>';
		}
	}

	/**
	 * Saves simple product fixed prices.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_simple_product( int $product_id ): void {
		if ( ! $this->can_save_product( $product_id ) ) {
			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce product save; sanitized in persist_submission().
		$submitted = isset( $_POST['umc_fixed_prices'] ) ? wp_unslash( $_POST['umc_fixed_prices'] ) : array();
		$this->persist_submission( $product_id, $submitted );
	}

	/**
	 * Saves variation fixed prices.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 */
	public function save_variation( int $variation_id, int $loop ): void {
		if ( ! $this->can_save_product( $variation_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce variation save; sanitized in persist_submission().
		$all = isset( $_POST['umc_fixed_prices_var'] ) ? wp_unslash( $_POST['umc_fixed_prices_var'] ) : array();
		$row = is_array( $all ) && isset( $all[ $loop ] ) && is_array( $all[ $loop ] ) ? $all[ $loop ] : array();

		$this->persist_submission( $variation_id, $row );
	}

	/**
	 * Normalizes and persists posted fixed prices.
	 *
	 * @param int   $product_id Product or variation ID.
	 * @param mixed $submitted  Posted currency map.
	 */
	private function persist_submission( int $product_id, mixed $submitted ): void {
		if ( ! is_array( $submitted ) ) {
			return;
		}

		$document = $this->merger->merge_and_save( $product_id, $submitted, $this->registry->get_base_code() );

		/**
		 * Fires after fixed product prices are saved.
		 *
		 * @since 0.19.0
		 *
		 * @param int                $product_id Product or variation ID.
		 * @param FixedPriceDocument $document   Saved document.
		 */
		do_action( 'umc_fixed_prices_saved', $product_id, $document );
	}

	/**
	 * Whether the current user may save fixed prices for a product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function can_save_product( int $product_id ): bool {
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return false;
		}

		return true;
	}
}
