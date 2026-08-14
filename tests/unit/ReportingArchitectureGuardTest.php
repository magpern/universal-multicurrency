<?php
/**
 * Architectural guard: reporting must stay read-only over historical facts.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Ensures src/Reporting never imports live pricing or FX dependencies.
 */
final class ReportingArchitectureGuardTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * @return array<int, string>
	 */
	private function reporting_files_except_cache(): array {
		return array_values(
			array_filter(
				$this->umc_source_files( 'Reporting' ),
				static function ( string $file ): bool {
					return 'ReportingCache.php' !== basename( $file );
				}
			)
		);
	}

	public function test_reporting_namespace_does_not_reference_rate_provider(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bRateProvider\b/',
			'Reporting must not look up live rates via RateProvider.'
		);
	}

	public function test_reporting_namespace_does_not_reference_frankfurter(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bFrankfurter\b/',
			'Reporting must not reference Frankfurter provider code.'
		);
	}

	public function test_reporting_namespace_does_not_reference_price_conversion_service(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bPriceConversionService\b/',
			'Reporting must not re-run live price conversion.'
		);
	}

	public function test_reporting_namespace_does_not_call_converter_convert(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/Converter::convert\s*\(/',
			'Reporting must not convert historical amounts.'
		);
	}

	public function test_reporting_namespace_does_not_reference_fixed_price_repository(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bFixedPriceRepository\b/',
			'Reporting must not read fixed-price documents at report time.'
		);
	}

	public function test_reporting_namespace_does_not_reference_product_price_resolution_service(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bProductPriceResolutionService\b/',
			'Reporting must not re-run product price resolution.'
		);
	}

	public function test_only_reporting_cache_may_call_get_transient_in_reporting_namespace(): void {
		$this->assert_pattern_absent_from(
			$this->reporting_files_except_cache(),
			'/\bget_transient\s*\(/',
			'Only ReportingCache may read transients inside src/Reporting/.'
		);

		$this->assert_pattern_present_in(
			array( dirname( __DIR__, 2 ) . '/src/Reporting/ReportingCache.php' ),
			'/\bget_transient\s*\(/',
			'ReportingCache must continue to read cached payloads via get_transient().'
		);
	}

	public function test_csv_renderer_does_not_load_orders_or_recompute_reports(): void {
		$csv_source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Reporting/ReportingCsvRenderer.php'
		);

		foreach (
			array(
				'OrderReportingRepository',
				'ReportingService',
				'wc_get_orders',
				'wc_get_order',
			) as $forbidden
		) {
			$this->assertStringNotContainsString(
				$forbidden,
				$csv_source,
				'ReportingCsvRenderer must consume immutable ReportingResult only.'
			);
		}
	}

	public function test_export_controller_uses_reporting_cache_not_repository(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Admin/ReportingExportController.php'
		);

		$this->assertStringContainsString( 'ReportingCache', $source );
		$this->assertStringNotContainsString( 'OrderReportingRepository', $source );
	}
}
