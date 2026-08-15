<?php
/**
 * Bounded, classified catalog listing for fixed-price coverage.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Fetches top-level catalog products (simple and variable, published),
 * classifies each via {@see FixedPriceCoverageReport}, and optionally
 * filters by coverage status — shared by the dedicated admin screen (WP3)
 * and `wp umc prices list` (WP4). The only sanctioned product-enumeration
 * API used is `wc_get_products()`, consistent with `OrderReportingRepository`'s
 * no-direct-SQL discipline.
 */
final class FixedPriceCatalogQuery {

	/**
	 * Binds the query to the shared coverage classifier.
	 *
	 * @param FixedPriceCoverageReport $coverage Coverage classifier.
	 */
	public function __construct(
		private FixedPriceCoverageReport $coverage
	) {
	}

	/**
	 * Classifies up to `$limit` matching products for one currency.
	 *
	 * @param string $currency_code Non-base currency code.
	 * @param string $status_filter One of {@see FixedPriceCoverageReport}'s
	 *                              STATUS_* constants, or '' for no filter.
	 * @param string $search        Optional product name/SKU search term.
	 * @param int    $limit         Maximum candidate products to fetch/classify.
	 * @return array{rows: array<int, array{product: \WC_Product, status: string}>, truncated: bool}
	 */
	public function classify_catalog( string $currency_code, string $status_filter, string $search, int $limit ): array {
		$args = array(
			'status'  => 'publish',
			'type'    => array( 'simple', 'variable' ),
			'limit'   => $limit + 1,
			'return'  => 'objects',
			'orderby' => 'title',
			'order'   => 'ASC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$products = wc_get_products( $args );

		if ( ! is_array( $products ) ) {
			$products = array();
		}

		$truncated = count( $products ) > $limit;

		if ( $truncated ) {
			$products = array_slice( $products, 0, $limit );
		}

		$rows = array();

		foreach ( $products as $product ) {
			$status = $this->coverage->classify( $product, $currency_code );

			if ( '' !== $status_filter && $status_filter !== $status ) {
				continue;
			}

			$rows[] = array(
				'product' => $product,
				'status'  => $status,
			);
		}

		return array(
			'rows'      => $rows,
			'truncated' => $truncated,
		);
	}
}
