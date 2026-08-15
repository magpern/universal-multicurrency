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

	/**
	 * Batched, unbounded iteration over every top-level catalog product
	 * (simple and variable, published) — the CLI's large-catalog path
	 * (`wp umc prices seed|clear --all`), unlike {@see classify_catalog()}
	 * which is deliberately capped for the admin screen. Pages lazily: each
	 * `wc_get_products()` call happens only as the generator is consumed, so
	 * memory stays bounded to one batch regardless of catalog size.
	 *
	 * @param int $batch_size Products fetched per underlying query.
	 * @return iterable Yields \WC_Product instances.
	 */
	public function each_product( int $batch_size ): iterable {
		$page = 1;

		do {
			$products = wc_get_products(
				array(
					'status'  => 'publish',
					'type'    => array( 'simple', 'variable' ),
					'limit'   => $batch_size,
					'page'    => $page,
					'return'  => 'objects',
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			);

			if ( ! is_array( $products ) ) {
				$products = array();
			}

			foreach ( $products as $product ) {
				yield $product;
			}

			$fetched = count( $products );
			++$page;
		} while ( $fetched === $batch_size );
	}

	/**
	 * Batched, unbounded classified iteration — `wp umc prices list` at true
	 * catalog scale (unlike {@see classify_catalog()}'s admin-bounded fetch).
	 *
	 * @param string $currency_code Non-base currency code.
	 * @param string $status_filter One of {@see FixedPriceCoverageReport}'s
	 *                              STATUS_* constants, or '' for no filter.
	 * @param int    $batch_size    Products fetched per underlying query.
	 * @return iterable Yields array{product: \WC_Product, status: string}.
	 */
	public function each_classified( string $currency_code, string $status_filter, int $batch_size ): iterable {
		foreach ( $this->each_product( $batch_size ) as $product ) {
			$status = $this->coverage->classify( $product, $currency_code );

			if ( '' !== $status_filter && $status_filter !== $status ) {
				continue;
			}

			yield array(
				'product' => $product,
				'status'  => $status,
			);
		}
	}
}
