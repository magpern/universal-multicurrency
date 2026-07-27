<?php
/**
 * Unit test: the plugin header version and UMC_VERSION never drift apart.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Two independent places declare the plugin's version — the header WordPress
 * reads and the UMC_VERSION constant the plugin's own code reads (e.g. the
 * order snapshot's stamped version). Nothing enforces they move together, so
 * this asserts it against the source directly rather than trusting a release
 * to touch both.
 */
final class PluginVersionTest extends TestCase {

	private function source(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/universal-multicurrency.php' );
	}

	public function test_header_version_matches_umc_version_constant(): void {
		$source = $this->source();

		$this->assertMatchesRegularExpression( '/\* Version:\s*(\S+)/', $source, 'Plugin header is missing a Version line.' );
		$this->assertMatchesRegularExpression( '/define\(\s*\'UMC_VERSION\',\s*\'([^\']+)\'\s*\)/', $source, 'UMC_VERSION definition not found.' );

		preg_match( '/\* Version:\s*(\S+)/', $source, $header_match );
		preg_match( '/define\(\s*\'UMC_VERSION\',\s*\'([^\']+)\'\s*\)/', $source, $constant_match );

		$this->assertSame(
			$header_match[1],
			$constant_match[1],
			'The plugin header Version and the UMC_VERSION constant must move together.'
		);
	}
}
