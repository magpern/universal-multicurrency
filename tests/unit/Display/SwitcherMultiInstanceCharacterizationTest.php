<?php
/**
 * Characterization: multiple switcher instances keep unique DOM ids.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherOptionFactory;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherViewModel;

/**
 * Documents multi-instance id uniqueness for automatic + shortcode coexistence.
 */
final class SwitcherMultiInstanceCharacterizationTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			define( 'UMC_PLUGIN_FILE', dirname( __DIR__, 3 ) . '/universal-multicurrency.php' );
		}

		if ( ! defined( 'UMC_VERSION' ) ) {
			define( 'UMC_VERSION', '0.0.0-test' );
		}
	}

	public function test_two_rendered_instances_expose_distinct_trigger_panel_and_menu_ids(): void {
		$renderer = new SwitcherRenderer();
		$first    = $renderer->render( $this->view_model( '11', SwitcherSettings::PRESENTATION_EDGE_PILL ) );
		$second   = $renderer->render( $this->view_model( '12', SwitcherSettings::PRESENTATION_CLASSIC_DROPDOWN ) );

		$this->assertStringContainsString( 'id="umc-switcher-trigger-11"', $first );
		$this->assertStringContainsString( 'id="umc-switcher-panel-11"', $first );
		$this->assertStringContainsString( 'id="umc-switcher-menu-11"', $first );
		$this->assertStringContainsString( 'id="umc-switcher-title-11"', $first );

		$this->assertStringContainsString( 'id="umc-switcher-trigger-12"', $second );
		$this->assertStringContainsString( 'id="umc-switcher-panel-12"', $second );

		$this->assertStringNotContainsString( 'umc-switcher-trigger-12', $first );
		$this->assertStringNotContainsString( 'umc-switcher-panel-11', $second );
		$this->assertStringContainsString( 'data-umc-presentation="edge-pill"', $first );
		$this->assertStringContainsString( 'data-umc-presentation="classic-dropdown"', $second );
		$this->assertStringNotContainsString( 'role="dialog"', $first );
		$this->assertStringNotContainsString( 'role="dialog"', $second );
	}

	/**
	 * @param string $instance_id Instance suffix.
	 * @param string $presentation Selector presentation.
	 */
	private function view_model( string $instance_id, string $presentation ): SwitcherViewModel {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
				'design'    => array(
					'presentation' => $presentation,
				),
			)
		);

		$factory = new SwitcherOptionFactory( $settings );
		$active  = $factory->create( 'SEK', 'kr', 'Swedish krona', '?currency=SEK', true );
		$other   = $factory->create( 'EUR', '€', 'Euro', '?currency=EUR', false );

		return new SwitcherViewModel( $instance_id, $settings, array( $active, $other ), $active, false );
	}
}
