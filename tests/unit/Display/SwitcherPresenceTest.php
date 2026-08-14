<?php
/**
 * Unit tests for bounded switcher presence detection.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Display\SwitcherPresence;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherShortcode;

/**
 * Covers proactive asset presence rules without unbounded template scans.
 */
final class SwitcherPresenceTest extends TestCase {

	public function test_automatic_placement_requests_assets(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$this->assertTrue(
			( new SwitcherPresence() )->should_load_switcher_assets( $settings, 2 )
		);
	}

	public function test_disabled_switcher_never_requests_assets(): void {
		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => false,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$this->assertFalse(
			( new SwitcherPresence() )->should_load_switcher_assets( $settings, 3 )
		);
	}

	public function test_single_currency_never_requests_assets(): void {
		$settings = SwitcherSettings::from_array( array( 'enabled' => true ) );

		$this->assertFalse(
			( new SwitcherPresence() )->should_load_switcher_assets( $settings, 1 )
		);
	}

	public function test_shortcode_in_post_content_requests_assets(): void {
		if ( ! function_exists( 'has_shortcode' ) ) {
			$this->markTestSkipped( 'has_shortcode unavailable.' );
		}
		$post               = $this->createMock( \WP_Post::class );
		$post->post_content = '[' . SwitcherShortcode::TAG_PRIMARY . ']';
		$GLOBALS['post']    = $post;

		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_MANUAL,
			)
		);

		$this->assertTrue(
			( new SwitcherPresence() )->should_load_switcher_assets( $settings, 2 )
		);

		unset( $GLOBALS['post'] );
	}

	public function test_block_in_post_content_requests_assets(): void {
		if ( ! function_exists( 'has_block' ) ) {
			$this->markTestSkipped( 'has_block unavailable.' );
		}

		$post               = $this->createMock( \WP_Post::class );
		$post->post_content = '<!-- wp:universal-multicurrency/currency-switcher /-->';
		$GLOBALS['post']    = $post;

		$settings = SwitcherSettings::from_array(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_MANUAL,
			)
		);

		$this->assertTrue(
			( new SwitcherPresence() )->should_load_switcher_assets( $settings, 2 )
		);

		unset( $GLOBALS['post'] );
	}

	public function test_presence_class_does_not_call_get_block_templates(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Display/SwitcherPresence.php'
		);

		$this->assertStringNotContainsString( 'get_block_templates', $source );
		$this->assertStringNotContainsString( 'wp_template_part', $source );
	}
}
