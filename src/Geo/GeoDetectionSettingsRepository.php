<?php
/**
 * Geo Detection settings read facade.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

use UMC\Settings;

/**
 * Reads normalized geo settings from the merchant settings store.
 */
final class GeoDetectionSettingsRepository {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructs the repository.
	 *
	 * @param Settings $settings Merchant settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Current geo settings.
	 */
	public function get(): GeoDetectionSettings {
		$data = $this->settings->get();

		return GeoDetectionSettings::from_array( is_array( $data['geo'] ?? null ) ? $data['geo'] : array() );
	}
}
