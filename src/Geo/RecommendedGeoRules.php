<?php
/**
 * Recommended European geo routing presets.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Canonical recommended rule definitions for the admin preset action.
 */
final class RecommendedGeoRules {

	/**
	 * Recommended rules in display order.
	 *
	 * @return list<array{type:string,value:string,currency:string,label_key:string}>
	 */
	public static function definitions(): array {
		return array(
			array(
				'type'      => GeoRoutingRule::TYPE_COUNTRY,
				'value'     => 'SE',
				'currency'  => 'SEK',
				'label_key' => 'sweden',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_COUNTRY,
				'value'     => 'DK',
				'currency'  => 'DKK',
				'label_key' => 'denmark',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_COUNTRY,
				'value'     => 'NO',
				'currency'  => 'NOK',
				'label_key' => 'norway',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_COUNTRY,
				'value'     => 'PL',
				'currency'  => 'PLN',
				'label_key' => 'poland',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_COUNTRY,
				'value'     => 'GB',
				'currency'  => 'GBP',
				'label_key' => 'united_kingdom',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_REGION,
				'value'     => GeoRegionRegistry::REGION_EU,
				'currency'  => 'EUR',
				'label_key' => 'eu',
			),
			array(
				'type'      => GeoRoutingRule::TYPE_OTHER,
				'value'     => '',
				'currency'  => 'EUR',
				'label_key' => 'other',
			),
		);
	}

	/**
	 * Builds rule rows for currencies that are selectable.
	 *
	 * @param array<int, string> $selectable Selectable currency codes.
	 * @return array{added:list<array<string,string>>,skipped:list<string>}
	 */
	public static function build_selectable_rules( array $selectable ): array {
		$selectable = array_map( 'strtoupper', $selectable );
		$added      = array();
		$skipped    = array();

		foreach ( self::definitions() as $definition ) {
			$currency = strtoupper( $definition['currency'] );

			if ( ! in_array( $currency, $selectable, true ) ) {
				$skipped[] = $currency;
				continue;
			}

			$added[] = array(
				'id'       => GeoRoutingRule::generate_id(),
				'type'     => $definition['type'],
				'value'    => GeoRoutingRule::TYPE_REGION === $definition['type'] ? $definition['value'] : ( GeoRoutingRule::TYPE_COUNTRY === $definition['type'] ? $definition['value'] : '' ),
				'currency' => $currency,
			);
		}

		return array(
			'added'   => $added,
			'skipped' => array_values( array_unique( $skipped ) ),
		);
	}
}
