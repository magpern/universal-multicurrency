<?php
/**
 * Maps Diagnostics conflict findings to Compatibility results.
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
use UMC\Diagnostics\DetectorManifest;
use UMC\Diagnostics\Finding;
use UMC\Diagnostics\SignatureKind;

/**
 * Reuses Milestone 6 conflict detection without duplicating signatures.
 */
final class ConflictCheck implements CompatibilityCheckInterface {

	/**
	 * Runs conflict mapping.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$results  = array();
		$findings = $inventory->conflict_detector()->findings();
		$seen     = array();

		foreach ( $findings as $finding ) {
			$seen[ $finding->id() ] = true;
			$results[]              = $this->map_active_finding( $finding, $inventory );
		}

		foreach ( DetectorManifest::manifest() as $id => $definition ) {
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			$plugin_file = $this->installed_manifest_plugin( $definition['signatures'] ?? array(), $inventory );
			if ( null === $plugin_file ) {
				continue;
			}

			$results[] = new CompatibilityResult(
				'conflict.inactive.' . $id,
				CompatibilityCategory::CONFLICTS,
				CompatibilitySeverity::INFO,
				(string) $definition['label'],
				sprintf(
					/* translators: %s: third-party plugin name */
					__( '%s is installed but inactive.', 'universal-multicurrency' ),
					(string) $definition['label']
				),
				CompatibilityDeterminism::DETERMINISTIC,
				array(
					'plugin'  => $this->normalize_plugin_slug( $plugin_file ),
					'status'  => 'inactive',
					'version' => $inventory->plugin_version( $plugin_file ),
				)
			);
		}

		if ( array() === $findings ) {
			$results[] = new CompatibilityResult(
				'conflict.none',
				CompatibilityCategory::CONFLICTS,
				CompatibilitySeverity::PASS,
				__( 'No currency-switcher conflicts detected', 'universal-multicurrency' ),
				__( 'No active conflicting currency switcher was detected.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		return $results;
	}

	/**
	 * Maps one active finding to a conflict result.
	 *
	 * @param Finding                $finding   Diagnostics finding.
	 * @param CompatibilityInventory $inventory Shared inventory.
	 */
	private function map_active_finding( Finding $finding, CompatibilityInventory $inventory ): CompatibilityResult {
		$evidence = array(
			'plugin'     => $finding->label(),
			'confidence' => $finding->confidence(),
			'score'      => (string) $finding->score(),
		);

		foreach ( $finding->matched() as $signature ) {
			$key = $signature->kind();
			if ( SignatureKind::PLUGIN_PATH === $key ) {
				$evidence['plugin_path'] = $this->normalize_plugin_slug( $signature->needle() );
				$evidence['status']      = 'active';
				if ( $inventory->is_plugin_active( $signature->needle() ) ) {
					$evidence['version'] = $inventory->plugin_version( $signature->needle() );
				}
			} else {
				$evidence[ 'signature_' . $key ] = $signature->needle();
			}
		}

		return new CompatibilityResult(
			'conflict.active.' . $finding->id(),
			CompatibilityCategory::CONFLICTS,
			CompatibilitySeverity::CONFLICT,
			$finding->label(),
			sprintf(
				/* translators: %s: third-party plugin name */
				__( '%s appears to be active and conflicts with Universal Multicurrency.', 'universal-multicurrency' ),
				$finding->label()
			),
			CompatibilityDeterminism::DETERMINISTIC,
			$evidence,
			array(
				new CompatibilityAction(
					__( 'Review installed plugins', 'universal-multicurrency' ),
					admin_url( 'plugins.php' )
				),
			),
			array(
				__( 'Deactivate the conflicting currency switcher or disable the Universal Multicurrency storefront switcher until only one switcher controls prices.', 'universal-multicurrency' ),
			)
		);
	}

	/**
	 * Finds an installed manifest plugin file from plugin_path signatures.
	 *
	 * @param array<int, array<string, mixed>> $signatures Manifest signatures.
	 * @param CompatibilityInventory           $inventory  Shared inventory.
	 */
	private function installed_manifest_plugin( array $signatures, CompatibilityInventory $inventory ): ?string {
		foreach ( $signatures as $signature ) {
			if ( SignatureKind::PLUGIN_PATH !== ( $signature['kind'] ?? '' ) ) {
				continue;
			}

			$needle = (string) ( $signature['needle'] ?? '' );
			if ( '' === $needle ) {
				continue;
			}

			if ( isset( $inventory->plugins()[ $needle ] ) ) {
				return $needle;
			}

			$match = $inventory->find_plugin_by_path_suffix( $needle );
			if ( null !== $match ) {
				return $match;
			}
		}

		return null;
	}

	/**
	 * Normalizes a plugin path to a slug without full filesystem paths.
	 *
	 * @param string $plugin_file Plugin bootstrap path.
	 */
	private function normalize_plugin_slug( string $plugin_file ): string {
		return str_replace( '\\', '/', $plugin_file );
	}
}
