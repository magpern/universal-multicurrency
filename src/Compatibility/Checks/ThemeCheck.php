<?php
/**
 * Theme compatibility check.
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
use UMC\Compatibility\Registry\ThemeCompatibilityRegistry;
use UMC\Display\SwitcherSettingsRepository;

/**
 * Reports active theme facts and conservative compatibility status.
 */
final class ThemeCheck implements CompatibilityCheckInterface {

	/**
	 * Runs the check against shared inventory.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$theme  = $inventory->theme();
		$parent = $inventory->parent_theme();
		$entry  = ThemeCompatibilityRegistry::resolve( (string) ( $theme['stylesheet'] ?? '' ) );

		$results = array(
			new CompatibilityResult(
				'theme.active',
				CompatibilityCategory::THEME,
				CompatibilitySeverity::INFO,
				(string) ( $theme['name'] ?? __( 'Active theme', 'universal-multicurrency' ) ),
				sprintf(
					/* translators: 1: theme name, 2: theme version */
					__( 'Active theme: %1$s (%2$s). Compatibility status: %3$s.', 'universal-multicurrency' ),
					(string) ( $theme['name'] ?? '' ),
					(string) ( $theme['version'] ?? '' ),
					$entry['status']
				),
				CompatibilityDeterminism::FACT,
				array(
					'stylesheet' => (string) ( $theme['stylesheet'] ?? '' ),
					'version'    => (string) ( $theme['version'] ?? '' ),
					'status'     => $entry['status'],
				)
			),
		);

		if ( array() !== $parent && ( $parent['stylesheet'] ?? '' ) !== ( $theme['stylesheet'] ?? '' ) ) {
			$results[] = new CompatibilityResult(
				'theme.parent',
				CompatibilityCategory::THEME,
				CompatibilitySeverity::INFO,
				(string) ( $parent['name'] ?? __( 'Parent theme', 'universal-multicurrency' ) ),
				sprintf(
					/* translators: 1: parent theme name, 2: parent theme version */
					__( 'Parent theme: %1$s (%2$s).', 'universal-multicurrency' ),
					(string) ( $parent['name'] ?? '' ),
					(string) ( $parent['version'] ?? '' )
				),
				CompatibilityDeterminism::FACT,
				array(
					'stylesheet' => (string) ( $parent['stylesheet'] ?? '' ),
					'version'    => (string) ( $parent['version'] ?? '' ),
				)
			);
		}

		$display   = ( new SwitcherSettingsRepository( $inventory->settings() ) )->get();
		$results[] = new CompatibilityResult(
			'theme.placement_note',
			CompatibilityCategory::THEME,
			CompatibilitySeverity::INFO,
			__( 'Automatic switcher placement uses generic hooks', 'universal-multicurrency' ),
			__( 'Automatic switcher placement currently relies on generic WooCommerce and WordPress hooks rather than a theme-specific integration.', 'universal-multicurrency' ),
			CompatibilityDeterminism::FACT,
			array(
				'placement' => $display->placement(),
				'style'     => $display->style(),
			)
		);

		return $results;
	}
}
