<?php
/**
 * Configuration compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\Validation\SettingsConfigurationValidator;

/**
 * Validates persisted Universal Multicurrency settings.
 */
final class ConfigurationCheck implements CompatibilityCheckInterface {

	/**
	 * Settings validator.
	 *
	 * @var SettingsConfigurationValidator
	 */
	private SettingsConfigurationValidator $validator;

	/**
	 * Creates the check.
	 *
	 * @param SettingsConfigurationValidator|null $validator Optional validator.
	 */
	public function __construct( ?SettingsConfigurationValidator $validator = null ) {
		$this->validator = $validator ?? new SettingsConfigurationValidator();
	}

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		return $this->validator->validate( $inventory );
	}
}
