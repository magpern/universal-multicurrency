<?php
/**
 * Integration tests for WordPressEnvironmentProbe.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use WP_UnitTestCase;

/**
 * Proves each admissible signature kind against real WordPress registries.
 */
final class WordPressEnvironmentProbeTest extends WP_UnitTestCase {

	/**
	 * Probe under test.
	 *
	 * @var WordPressEnvironmentProbe
	 */
	private WordPressEnvironmentProbe $probe;

	protected function setUp(): void {
		parent::setUp();
		$this->probe = new WordPressEnvironmentProbe();
	}

	protected function tearDown(): void {
		remove_all_shortcodes();
		remove_all_filters( 'umc_probe_hook' );
		remove_all_filters( 'umc_probe_hook_negative' );
		parent::tearDown();
	}

	public function test_plugin_path_signature_reflects_active_plugins_option(): void {
		update_option(
			'active_plugins',
			array(
				'umc-fixture-switcher-a/umc-fixture-switcher-a.php',
			)
		);

		$signature = new Signature(
			SignatureKind::PLUGIN_PATH,
			'umc-fixture-switcher-a/umc-fixture-switcher-a.php',
			60
		);

		$this->assertTrue( $this->probe->evaluate( array( $signature ) )[ $signature->key() ] );
	}

	public function test_malformed_active_plugins_option_yields_false_without_warnings(): void {
		update_option( 'active_plugins', 'not-an-array' );

		$signature = new Signature(
			SignatureKind::PLUGIN_PATH,
			'umc-fixture-switcher-a/umc-fixture-switcher-a.php',
			60
		);

		$this->assertFalse( $this->probe->evaluate( array( $signature ) )[ $signature->key() ] );
	}

	public function test_class_signature_uses_non_autoload_lookup(): void {
		$signature = new Signature( SignatureKind::CLASS_NAME, 'UMC_Probe_Class_Isolated', 40 );

		$this->assertFalse( $this->probe->evaluate( array( $signature ) )[ $signature->key() ] );

		require_once __DIR__ . '/fixtures/probe-class-fixture.php';

		$this->assertTrue( $this->probe->evaluate( array( $signature ) )[ $signature->key() ] );
	}

	public function test_function_and_constant_signatures(): void {
		require_once WP_PLUGIN_DIR . '/umc-fixture-switcher-a/umc-fixture-switcher-a.php';

		$function = new Signature( SignatureKind::FUNCTION, 'umc_fixture_switcher_a_symbol', 30 );
		$constant = new Signature( SignatureKind::CONSTANT, 'UMC_FIXTURE_SWITCHER_A_VERSION', 25 );
		$result   = $this->probe->evaluate( array( $function, $constant ) );

		$this->assertTrue( $result[ $function->key() ] );
		$this->assertTrue( $result[ $constant->key() ] );
	}

	public function test_shortcode_and_hook_signatures(): void {
		add_shortcode(
			'umc_probe_shortcode',
			static function (): string {
				return '';
			}
		);
		add_filter(
			'umc_probe_hook',
			static function ( $value ) {
				return $value;
			}
		);

		$shortcode = new Signature( SignatureKind::SHORTCODE, 'umc_probe_shortcode', 15 );
		$hook      = new Signature( SignatureKind::HOOK, 'umc_probe_hook', 10 );
		$negative  = new Signature( SignatureKind::HOOK, 'umc_probe_hook_negative', 10 );
		$result    = $this->probe->evaluate( array( $shortcode, $hook, $negative ) );

		$this->assertTrue( $result[ $shortcode->key() ] );
		$this->assertTrue( $result[ $hook->key() ] );
		$this->assertFalse( $result[ $negative->key() ] );
	}

	public function test_absent_symbols_default_to_false(): void {
		$signatures = array(
			new Signature( SignatureKind::PLUGIN_PATH, 'missing/plugin.php', 60 ),
			new Signature( SignatureKind::CLASS_NAME, 'UMC_Missing_Class', 40 ),
			new Signature( SignatureKind::FUNCTION, 'umc_missing_function', 30 ),
			new Signature( SignatureKind::CONSTANT, 'UMC_MISSING_VERSION', 25 ),
			new Signature( SignatureKind::SHORTCODE, 'umc_missing_shortcode', 15 ),
			new Signature( SignatureKind::HOOK, 'umc_missing_hook', 10 ),
		);

		foreach ( $this->probe->evaluate( $signatures ) as $key => $present ) {
			$this->assertFalse( $present, "Expected '{$key}' to be absent." );
		}
	}
}
