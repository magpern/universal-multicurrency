<?php
/**
 * Unit-level performance invariants for pure services.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Settings;
use UMC\SettingsUpgrader;

/**
 * Deterministic service-invocation counts without WordPress bootstrap.
 *
 * @group performance
 */
final class PerformanceBaselineTest extends TestCase {

	protected function tearDown(): void {
		Settings::reset_upgrader();
		parent::tearDown();
	}

	public function test_in_memory_settings_repeated_get_is_idempotent(): void {
		$data = Settings::sanitize(
			array(
				'currencies' => array(
					'SEK' => array( 'rate' => '11.50' ),
				),
			)
		);

		$settings = new Settings( $data );
		$first    = $settings->get();
		$second   = $settings->get();

		$this->assertSame( $first, $second );
		$this->assertSame( '11.50', $settings->get_rate( 'SEK' ) );
	}

	public function test_upgrader_second_pass_avoids_persist_flag(): void {
		$upgrader = new SettingsUpgrader();
		$raw_v0   = array(
			'currencies' => array(
				'SEK' => array( 'rate' => '11.50' ),
			),
		);

		$first  = $upgrader->upgrade( $raw_v0 );
		$second = $upgrader->upgrade( $first->settings() );

		$this->assertTrue( $first->should_persist() );
		$this->assertFalse( $second->should_persist(), 'Canonical settings must not request a rewrite on re-entry.' );
	}
}
