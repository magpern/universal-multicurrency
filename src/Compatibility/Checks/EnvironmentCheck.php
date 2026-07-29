<?php
/**
 * Environment facts compatibility check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Display\SwitcherSettingsRepository;

/**
 * Reports environment facts without treating every fact as a failure.
 */
final class EnvironmentCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$facts   = $inventory->facts();
		$display = ( new SwitcherSettingsRepository( $inventory->settings() ) )->get();
		$results = array();

		$labels = array(
			'umc_version'    => __( 'Universal Multicurrency', 'universal-multicurrency' ),
			'schema_version' => __( 'Settings schema', 'universal-multicurrency' ),
			'wordpress'      => __( 'WordPress', 'universal-multicurrency' ),
			'woocommerce'    => __( 'WooCommerce', 'universal-multicurrency' ),
			'php'            => __( 'PHP', 'universal-multicurrency' ),
			'database'       => __( 'Database', 'universal-multicurrency' ),
			'multisite'      => __( 'Multisite', 'universal-multicurrency' ),
			'hpos'           => __( 'HPOS', 'universal-multicurrency' ),
			'object_cache'   => __( 'Object cache', 'universal-multicurrency' ),
			'cron_disabled'  => __( 'WP-Cron disabled', 'universal-multicurrency' ),
			'permalink'      => __( 'Permalink structure', 'universal-multicurrency' ),
			'memory_limit'   => __( 'PHP memory limit', 'universal-multicurrency' ),
			'max_execution'  => __( 'Max execution time', 'universal-multicurrency' ),
			'locale'         => __( 'Locale', 'universal-multicurrency' ),
			'base_currency'  => __( 'Base currency', 'universal-multicurrency' ),
			'enabled_codes'  => __( 'Enabled currencies', 'universal-multicurrency' ),
		);

		foreach ( $labels as $key => $label ) {
			if ( ! isset( $facts[ $key ] ) || '' === $facts[ $key ] ) {
				continue;
			}

			$results[] = new CompatibilityResult(
				'environment.' . $key,
				CompatibilityCategory::ENVIRONMENT,
				CompatibilitySeverity::INFO,
				$label,
				$label . ': ' . $facts[ $key ],
				CompatibilityDeterminism::FACT,
				array(
					'key'   => $key,
					'value' => $facts[ $key ],
				)
			);
		}

		$results[] = new CompatibilityResult(
			'environment.display',
			CompatibilityCategory::ENVIRONMENT,
			CompatibilitySeverity::INFO,
			__( 'Display placement and style', 'universal-multicurrency' ),
			sprintf(
				/* translators: 1: placement, 2: style */
				__( 'Display placement: %1$s. Style: %2$s.', 'universal-multicurrency' ),
				$display->placement(),
				$display->style()
			),
			CompatibilityDeterminism::FACT,
			array(
				'placement' => $display->placement(),
				'style'     => $display->style(),
				'enabled'   => $display->is_enabled() ? 'yes' : 'no',
			)
		);

		if ( 'plain' === ( $facts['permalink'] ?? '' ) ) {
			$results[] = new CompatibilityResult(
				'environment.permalink_plain',
				CompatibilityCategory::ENVIRONMENT,
				CompatibilitySeverity::WARNING,
				__( 'Plain permalinks detected', 'universal-multicurrency' ),
				__( 'Plain permalinks can make currency switching and redirects less reliable.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		if ( 'yes' === ( $facts['cron_disabled'] ?? '' ) ) {
			$results[] = new CompatibilityResult(
				'environment.cron_disabled',
				CompatibilityCategory::ENVIRONMENT,
				CompatibilitySeverity::INFO,
				__( 'WordPress pseudo-cron is disabled', 'universal-multicurrency' ),
				__( 'DISABLE_WP_CRON is set. Ensure a system cron task triggers wp-cron.php if automatic rate updates are used.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		return $results;
	}
}
