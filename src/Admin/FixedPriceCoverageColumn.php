<?php
/**
 * Passive fixed-price coverage column on the WooCommerce Products list.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCoverageReport;
use WC_Product;

/**
 * Read-only discovery signal (ADR-0029 § Products-list column): one compact
 * badge per enabled non-base currency, linking into the dedicated Fixed
 * Pricing screen pre-filtered to that product/currency. No write behavior —
 * no WordPress Products-list bulk-action dropdown entries are registered
 * anywhere in this plugin.
 */
final class FixedPriceCoverageColumn {

	public const COLUMN_ID = 'umc_fixed_pricing';

	/**
	 * Binds the column to its collaborators.
	 *
	 * @param FixedPriceCoverageReport $coverage Coverage classifier.
	 * @param CurrencyRegistry         $registry Currency configuration.
	 */
	public function __construct(
		private FixedPriceCoverageReport $coverage,
		private CurrencyRegistry $registry
	) {
	}

	/**
	 * Registers the products-list column hooks.
	 */
	public function register(): void {
		add_filter( 'manage_edit-product_columns', array( $this, 'add_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Appends the coverage column when at least one non-base currency exists.
	 *
	 * @param array<string, string> $columns Existing product list columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		if ( array() === $this->non_base_currencies() ) {
			return $columns;
		}

		$columns[ self::COLUMN_ID ] = __( 'Fixed Pricing', 'universal-multicurrency' );

		return $columns;
	}

	/**
	 * Renders one product row's coverage badges.
	 *
	 * @param string $column  Column ID being rendered.
	 * @param int    $post_id Product post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_ID !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		foreach ( $this->non_base_currencies() as $currency ) {
			$status = $this->coverage->classify( $product, $currency->code() );

			printf(
				'<a href="%1$s" class="umc-fp-coverage-badge umc-fp-coverage-badge--%2$s">%3$s: %4$s</a><br />',
				esc_url( $this->screen_url( $product, $currency->code() ) ),
				esc_attr( $status ),
				esc_html( $currency->code() ),
				esc_html( $this->status_label( $status ) )
			);
		}
	}

	/**
	 * Builds the pre-filtered Fixed Pricing screen URL for one product/currency.
	 *
	 * @param WC_Product $product       Product being linked from.
	 * @param string     $currency_code Currency code.
	 */
	private function screen_url( WC_Product $product, string $currency_code ): string {
		$search = '' !== $product->get_sku() ? $product->get_sku() : $product->get_name();

		return add_query_arg(
			array(
				'page'            => 'wc-settings',
				'tab'             => 'umc',
				'section'         => SettingsPage::SECTION_FIXED_PRICING,
				'umc_fp_currency' => $currency_code,
				'umc_fp_search'   => rawurlencode( $search ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Non-base currencies configured in the registry.
	 *
	 * @return array<int, \UMC\Currency>
	 */
	private function non_base_currencies(): array {
		return array_values(
			array_filter(
				$this->registry->get_currencies(),
				fn( $currency ): bool => ! $this->registry->is_base( $currency->code() )
			)
		);
	}

	/**
	 * Human-readable label for a coverage status constant.
	 *
	 * @param string $status STATUS_* constant.
	 */
	private function status_label( string $status ): string {
		return match ( $status ) {
			FixedPriceCoverageReport::STATUS_FIXED                   => __( 'Fixed', 'universal-multicurrency' ),
			FixedPriceCoverageReport::STATUS_PARTIAL                 => __( 'Partial', 'universal-multicurrency' ),
			FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS => __( 'No priceable variations', 'universal-multicurrency' ),
			default                                                    => __( 'FX fallback', 'universal-multicurrency' ),
		};
	}
}
