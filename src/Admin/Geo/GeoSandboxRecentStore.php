<?php
/**
 * Per-administrator Geo Sandbox recent country presets.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

/**
 * Stores recently used sandbox countries in user meta (ISO codes only).
 */
final class GeoSandboxRecentStore {

	public const META_KEY = 'umc_geo_sandbox_recent';

	private const MAX_RECENT = 8;

	/**
	 * Pinned quick-pick ISO alpha-2 codes for sandbox presets.
	 *
	 * @return list<string>
	 */
	public static function quick_pick_codes(): array {
		return array( 'SE', 'NO', 'DK', 'FI', 'DE', 'GB', 'US' );
	}

	/**
	 * Returns recently used country codes for the current admin user.
	 *
	 * @return list<string>
	 */
	public function get_recent(): array {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return array();
		}

		$stored = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$codes = array();

		foreach ( $stored as $code ) {
			if ( ! is_string( $code ) ) {
				continue;
			}

			$normalized = strtoupper( trim( $code ) );

			if ( 1 === preg_match( '/^[A-Z]{2}$/', $normalized ) ) {
				$codes[] = $normalized;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Records a country code at the front of the recent list.
	 *
	 * @param string $country_code ISO alpha-2 country code.
	 */
	public function push( string $country_code ): void {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		$code = strtoupper( trim( $country_code ) );

		if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return;
		}

		$recent = $this->get_recent();
		$recent = array_values( array_filter( $recent, static fn ( string $item ): bool => $item !== $code ) );
		$recent = array_merge( array( $code ), $recent );
		$recent = array_slice( $recent, 0, self::MAX_RECENT );

		update_user_meta( $user_id, self::META_KEY, $recent );
	}
}
