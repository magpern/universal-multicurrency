<?php
/**
 * Static guards for performance baseline policy.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Ensures runtime code avoids persistent caches and documents ceilings.
 *
 * @group performance
 */
final class PerformanceGuardTest extends TestCase {

	use SourceGuardTrait;

	public function test_src_contains_no_transient_or_object_cache_calls(): void {
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/\b(set_transient|get_transient|delete_transient|wp_cache_(set|get|delete|add|replace|flush))\s*\(/',
			'M7 forbids persistent caches and object-cache integration in runtime code.'
		);
	}

	public function test_performance_baselines_document_exists(): void {
		$path = dirname( __DIR__, 2 ) . '/docs/PERFORMANCE_BASELINES.md';

		$this->assertFileExists( $path );

		$doc = (string) file_get_contents( $path );

		foreach (
			array(
				'Environment assumptions',
				'Metric definitions',
				'Scenarios and enforced ceilings',
				'Changing a ceiling',
				'wall-clock',
			) as $section
		) {
			$this->assertStringContainsString( $section, $doc );
		}
	}

	public function test_integration_baseline_class_declares_documented_ceilings(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__ ) . '/integration/PerformanceBaselineTest.php'
		);

		foreach (
			array(
				'CEILING_SETTINGS_WRITE_CANONICAL_LOAD',
				'CEILING_SETTINGS_WRITE_ABSENT_LOAD',
				'CEILING_SETTINGS_WRITE_V0_UPGRADE',
				'CEILING_CURRENCY_RESOLUTION_WRITES',
				'CEILING_DIAGNOSTICS_QUERY_DELTA',
				'CEILING_STORE_API_CART_QUERY_DELTA',
				'CEILING_RATE_UPDATE_NOT_MODIFIED_WRITES',
			) as $name
		) {
			$this->assertStringContainsString( $name, $source, 'Missing enforced ceiling constant ' . $name );
		}
	}
}
