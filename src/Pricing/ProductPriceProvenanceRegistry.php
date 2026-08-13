<?php
/**
 * Request-scoped product pricing provenance for checkout.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Pricing;

/**
 * Records whether each product used fixed or converted pricing this request.
 */
final class ProductPriceProvenanceRegistry {

	/**
	 * Provenance keyed by product ID.
	 *
	 * @var array<int, array{source:string,currency:string}>
	 */
	private array $records = array();

	/**
	 * Records provenance for the active price of a product.
	 *
	 * @param int    $product_id Product or variation ID.
	 * @param string $source     {@see ProductPriceResolution::SOURCE_FIXED} or converted.
	 * @param string $currency   Active currency code.
	 */
	public function record( int $product_id, string $source, string $currency ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		$this->records[ $product_id ] = array(
			'source'   => $source,
			'currency' => strtoupper( $currency ),
		);
	}

	/**
	 * Returns recorded provenance for a product ID.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array{source:string,currency:string}|null
	 */
	public function get( int $product_id ): ?array {
		return $this->records[ $product_id ] ?? null;
	}

	/**
	 * Clears all records (tests).
	 */
	public function clear(): void {
		$this->records = array();
	}
}
