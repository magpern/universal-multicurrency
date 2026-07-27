<?php
/**
 * Structural guard: the WooCommerce-floor route-unavailable exclusion stays
 * exactly as wide as it should be (WordPress-free).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the mechanism that excludes Store API order-route tests at the
 * WooCommerce floor: `wc-order-route-unavailable` must cover exactly the
 * tests known to depend on the route, confined to one file, and `wc-shape`
 * must stay unused until a genuine response-shape incompatibility is
 * recorded. Each assertion here was verified to fail when the condition it
 * guards is violated, not merely to pass today.
 */
final class OrderRouteGroupGuardTest extends TestCase {

	private const EXPECTED_COUNT = 8;

	private const CONFINED_TO = 'tests/integration/StoreApi/OrderRouteCurrencyTest.php';

	/**
	 * @return array<int, string>
	 */
	private function test_source_files(): array {
		$root  = dirname( __DIR__ );
		$files = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			$path = (string) $file->getPathname();

			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			if ( false !== strpos( $path, '/tests/tmp/' ) ) {
				continue;
			}

			$files[] = $path;
		}

		return $files;
	}

	public function test_wc_order_route_unavailable_group_has_the_expected_count(): void {
		$files = $this->test_source_files();
		$this->assertNotSame( array(), $files, 'Expected test source files to scan.' );

		$total = 0;

		foreach ( $files as $file ) {
			$total += (int) preg_match_all( '/@group\s+wc-order-route-unavailable\b/', (string) file_get_contents( $file ) );
		}

		$this->assertSame(
			self::EXPECTED_COUNT,
			$total,
			'wc-order-route-unavailable must cover exactly the tests known to depend on the absent route.'
		);
	}

	public function test_wc_order_route_unavailable_group_is_confined_to_its_file(): void {
		$files     = $this->test_source_files();
		$offenders = array();

		foreach ( $files as $file ) {
			$count = (int) preg_match_all( '/@group\s+wc-order-route-unavailable\b/', (string) file_get_contents( $file ) );

			if ( $count > 0 && false === strpos( $file, self::CONFINED_TO ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'wc-order-route-unavailable must be confined to OrderRouteCurrencyTest.'
		);
	}

	public function test_wc_shape_group_is_unused(): void {
		$files = $this->test_source_files();
		$total = 0;

		foreach ( $files as $file ) {
			$total += (int) preg_match_all( '/@group\s+wc-shape\b/', (string) file_get_contents( $file ) );
		}

		$this->assertSame(
			0,
			$total,
			'wc-shape is reserved for a genuine response-shape incompatibility; none has been recorded.'
		);
	}
}
