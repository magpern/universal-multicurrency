<?php
/**
 * Extension detector unit tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility\Extension;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Extension\ExtensionDetector;

/**
 * Unit tests for ExtensionDetector passive probes.
 */
final class ExtensionDetectorTest extends TestCase {

	public function test_detects_absent_extension(): void {
		$definition = array(
			'signatures' => array(
				array(
					'type'   => 'plugin_path',
					'needle' => 'nonexistent-plugin/nonexistent.php',
				),
				array(
					'type'   => 'class',
					'needle' => 'Nonexistent_Class_For_UMC_Test',
				),
			),
		);

		$result = ExtensionDetector::detect( $definition, array(), array() );

		$this->assertFalse( $result['installed'] );
		$this->assertFalse( $result['active'] );
	}

	public function test_detects_installed_inactive_plugin(): void {
		$definition = array(
			'signatures' => array(
				array(
					'type'   => 'plugin_path',
					'needle' => 'my-plugin/my-plugin.php',
				),
			),
		);

		$plugins = array(
			'my-plugin/my-plugin.php' => array(
				'Version' => '1.2.3',
			),
		);

		$result = ExtensionDetector::detect( $definition, $plugins, array() );

		$this->assertTrue( $result['installed'] );
		$this->assertFalse( $result['active'] );
		$this->assertSame( '1.2.3', $result['version'] );
	}

	public function test_is_untested_version(): void {
		$this->assertTrue( ExtensionDetector::is_untested_version( '9.2.0', '9.0.0' ) );
		$this->assertFalse( ExtensionDetector::is_untested_version( '8.0.0', '9.0.0' ) );
	}
}
