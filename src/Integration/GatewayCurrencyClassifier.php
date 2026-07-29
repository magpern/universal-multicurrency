<?php
/**
 * Pure gateway currency classifier.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

/**
 * Classifies WooCommerce's incoming gateway map for a currency.
 *
 * WordPress-free and unit-testable. The sole currency classifier in UMC.
 */
final class GatewayCurrencyClassifier {

	/**
	 * Classifies gateways for a currency.
	 *
	 * @param array<string, object> $gateways              Incoming gateway map.
	 * @param string                $currency              Active currency code.
	 * @param callable              $supported_currencies  `( object $gateway ): ?array` resolver.
	 * @param int                   $enabled_gateway_count Enabled gateways in store.
	 */
	public function apply(
		array $gateways,
		string $currency,
		callable $supported_currencies,
		int $enabled_gateway_count = 0
	): GatewayCurrencyFilterResult {
		$currency = strtoupper( $currency );

		$before_ids = array_keys( $gateways );
		$retained   = array();
		$removed    = array();
		$unknown    = array();
		$filtered   = $gateways;

		foreach ( $gateways as $id => $gateway ) {
			$supported = $supported_currencies( $gateway );

			if ( null === $supported ) {
				$retained[ (string) $id ] = (string) $id;
				$unknown[ (string) $id ]  = (string) $id;
				continue;
			}

			if ( in_array( $currency, $supported, true ) ) {
				$retained[ (string) $id ] = (string) $id;
				continue;
			}

			unset( $filtered[ $id ] );
			$removed[ (string) $id ] = (string) $id;
		}

		$after_ids = array_keys( $filtered );

		$before_count     = count( $before_ids );
		$removed_count    = count( $removed );
		$unknown_count    = count( $unknown );
		$after_count      = count( $after_ids );
		$umc_caused_empty = $before_count > 0
			&& 0 === $unknown_count
			&& 0 === $after_count
			&& $removed_count === $before_count;

		$evaluation = new GatewayCurrencyEvaluation(
			$currency,
			array_values( array_map( 'strval', $before_ids ) ),
			array_values( $retained ),
			array_values( $removed ),
			array_values( $unknown ),
			array_values( array_map( 'strval', $after_ids ) ),
			max( 0, $enabled_gateway_count ),
			$umc_caused_empty
		);

		return new GatewayCurrencyFilterResult( $filtered, $evaluation );
	}
}
