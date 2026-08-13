<?php
/**
 * Performance guard: reporting order queries must stay bounded.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ensures OrderReportingRepository never requests unbounded order fetches.
 */
final class ReportingPerformanceGuardTest extends TestCase {

	public function test_order_reporting_repository_does_not_use_unbounded_limit(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 2 ) . '/src/Reporting/OrderReportingRepository.php'
		);

		$this->assertDoesNotMatchRegularExpression(
			"/['\"]limit['\"]\s*=>\s*-1/",
			$source,
			'OrderReportingRepository must paginate with ReportingConstants::BATCH_SIZE, never limit => -1.'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/limit\s*=>\s*-1/',
			$source,
			'OrderReportingRepository must paginate with ReportingConstants::BATCH_SIZE, never limit => -1.'
		);
	}
}
