<?php
/**
 * Version-controlled geographic region membership.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Deterministic ISO alpha-2 membership sets for region routing presets.
 *
 * Membership is embedded and versioned; no network or external mutable source.
 */
final class GeoRegionRegistry {

	public const VERSION = 1;

	public const REGION_EU = 'eu';

	public const REGION_EUROZONE = 'eurozone';

	public const REGION_EEA = 'eea';

	/**
	 * EU member states (27, post-Brexit).
	 *
	 * @var list<string>
	 */
	private const EU_MEMBERS = array(
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SK',
		'SI',
		'ES',
		'SE',
	);

	/**
	 * Eurozone members using EUR.
	 *
	 * @var list<string>
	 */
	private const EUROZONE_MEMBERS = array(
		'AT',
		'BE',
		'HR',
		'CY',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'IE',
		'IT',
		'LV',
		'LT',
		'LU',
		'MT',
		'NL',
		'PT',
		'SK',
		'SI',
		'ES',
	);

	/**
	 * EEA = EU + Iceland, Liechtenstein, Norway.
	 *
	 * @var list<string>
	 */
	private const EEA_MEMBERS = array(
		'AT',
		'BE',
		'BG',
		'HR',
		'CY',
		'CZ',
		'DK',
		'EE',
		'FI',
		'FR',
		'DE',
		'GR',
		'HU',
		'IE',
		'IS',
		'IT',
		'LI',
		'LT',
		'LU',
		'LV',
		'MT',
		'NL',
		'NO',
		'PL',
		'PT',
		'RO',
		'SE',
		'SI',
		'SK',
		'ES',
	);

	/**
	 * Region id to ISO alpha-2 membership map.
	 *
	 * @var array<string, list<string>>
	 */
	private const MEMBERSHIP = array(
		self::REGION_EU       => self::EU_MEMBERS,
		self::REGION_EUROZONE => self::EUROZONE_MEMBERS,
		self::REGION_EEA      => self::EEA_MEMBERS,
	);

	/**
	 * Whether a region identifier is supported.
	 *
	 * @param string $region_id Region id.
	 */
	public static function is_valid_region( string $region_id ): bool {
		return isset( self::MEMBERSHIP[ strtolower( trim( $region_id ) ) ] );
	}

	/**
	 * All supported region identifiers.
	 *
	 * @return list<string>
	 */
	public static function region_ids(): array {
		return array_keys( self::MEMBERSHIP );
	}

	/**
	 * Whether a country belongs to a region.
	 *
	 * @param string $country_code Uppercase ISO alpha-2.
	 * @param string $region_id    Region id.
	 */
	public function contains( string $country_code, string $region_id ): bool {
		$country_code = strtoupper( trim( $country_code ) );
		$region_id    = strtolower( trim( $region_id ) );

		if ( ! isset( self::MEMBERSHIP[ $region_id ] ) ) {
			return false;
		}

		return in_array( $country_code, self::MEMBERSHIP[ $region_id ], true );
	}

	/**
	 * Membership codes for a region, sorted.
	 *
	 * @param string $region_id Region id.
	 * @return list<string>
	 */
	public function members( string $region_id ): array {
		$region_id = strtolower( trim( $region_id ) );

		if ( ! isset( self::MEMBERSHIP[ $region_id ] ) ) {
			return array();
		}

		$members = self::MEMBERSHIP[ $region_id ];
		sort( $members );

		return $members;
	}

	/**
	 * Translatable label key suffix for a region id.
	 *
	 * @param string $region_id Region id.
	 */
	public static function label_context( string $region_id ): string {
		return strtolower( trim( $region_id ) );
	}
}
