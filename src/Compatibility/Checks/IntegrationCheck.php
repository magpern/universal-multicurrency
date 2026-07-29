<?php
/**
 * Integration detection compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityAction;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\Registry\IntegrationRegistry;

/**
 * Reports detected integrations using the central registry.
 */
final class IntegrationCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$results  = array();
		$detected = 0;

		foreach ( IntegrationRegistry::definitions() as $definition ) {
			if ( ! empty( $definition['conflict'] ) ) {
				continue;
			}

			$match = IntegrationRegistry::detect(
				$definition,
				$inventory->plugins(),
				$inventory->active_plugins()
			);

			if ( ! $match['installed'] ) {
				continue;
			}

			++$detected;
			$severity = $match['active'] ? CompatibilitySeverity::INFO : CompatibilitySeverity::INFO;
			$summary  = $match['active']
				? sprintf(
					/* translators: %s: integration label */
					__( '%s is active.', 'universal-multicurrency' ),
					(string) $definition['label']
				)
				: sprintf(
					/* translators: %s: integration label */
					__( '%s is installed but inactive.', 'universal-multicurrency' ),
					(string) $definition['label']
				);

			if ( IntegrationRegistry::GROUP_CURRENCY_SWITCHER === ( $definition['group'] ?? '' ) ) {
				$summary = sprintf(
					/* translators: %s: integration label */
					__( '%s was detected. Status: Detected / Untested.', 'universal-multicurrency' ),
					(string) $definition['label']
				);
			}

			$results[] = new CompatibilityResult(
				'integration.' . (string) $definition['id'],
				CompatibilityCategory::INTEGRATIONS,
				$severity,
				(string) $definition['label'],
				$summary,
				CompatibilityDeterminism::HEURISTIC,
				array(
					'status_label' => (string) ( $definition['status_label'] ?? 'Detected' ),
					'active'       => $match['active'] ? 'yes' : 'no',
					'version'      => (string) $match['version'],
					'plugin'       => $this->normalize_plugin_slug( (string) $match['plugin_file'] ),
				),
				array(
					new CompatibilityAction(
						__( 'Review installed plugins', 'universal-multicurrency' ),
						admin_url( 'plugins.php' )
					),
				)
			);
		}

		if ( 0 === $detected ) {
			$results[] = new CompatibilityResult(
				'integration.none',
				CompatibilityCategory::INTEGRATIONS,
				CompatibilitySeverity::PASS,
				__( 'No tracked integrations detected', 'universal-multicurrency' ),
				__( 'No integrations from the compatibility registry were detected on this site.', 'universal-multicurrency' ),
				CompatibilityDeterminism::HEURISTIC
			);
		}

		return $results;
	}

	/**
	 * Normalizes a plugin bootstrap path.
	 *
	 * @param string $plugin_file Plugin file path.
	 */
	private function normalize_plugin_slug( string $plugin_file ): string {
		return str_replace( '\\', '/', $plugin_file );
	}
}
