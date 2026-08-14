<?php
/**
 * Unit tests for embedded switcher settings overrides.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherSettings;

/**
 * Covers immutable placement overrides for embedded surfaces.
 */
final class SwitcherSettingsEmbeddedTest extends TestCase {

	public function test_with_placement_returns_new_instance(): void {
		$original = SwitcherSettings::from_array(
			array(
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);
		$embedded = $original->with_placement( SwitcherSettings::PLACEMENT_MANUAL );

		$this->assertNotSame( $original, $embedded );
		$this->assertSame( SwitcherSettings::PLACEMENT_FLOATING_SIDE, $original->placement() );
		$this->assertSame( SwitcherSettings::PLACEMENT_MANUAL, $embedded->placement() );
	}

	public function test_for_embedded_surface_forces_manual_placement(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'placement' => SwitcherSettings::PLACEMENT_STICKY_FOOTER,
			)
		)->for_embedded_surface();

		$this->assertSame( SwitcherSettings::PLACEMENT_MANUAL, $settings->placement() );
	}
}
