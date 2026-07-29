<?php
/**
 * Reads normalized Display switcher settings from plugin storage.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

use UMC\Settings;

/**
 * Repository for Display switcher settings.
 */
final class SwitcherSettingsRepository {

	/**
	 * Plugin settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Binds the repository to the plugin settings store.
	 *
	 * @param Settings $settings Plugin settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns normalized Display switcher settings.
	 */
	public function get(): SwitcherSettings {
		$data = $this->settings->get();

		return SwitcherSettings::from_array(
			is_array( $data['display'] ?? null ) ? $data['display'] : array()
		);
	}

	/**
	 * Whether the customer selection should be remembered between visits.
	 */
	public function remember_selection(): bool {
		return $this->get()->remember_selection();
	}
}
