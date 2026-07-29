<?php
/**
 * Unit tests for report redaction.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;
use UMC\Compatibility\Report\ReportRedactor;

/**
 * Verifies sensitive patterns are redacted from reports.
 */
final class ReportRedactorTest extends TestCase {

	public function test_redacts_secret_patterns_and_paths(): void {
		$redactor = new ReportRedactor();
		$input    = implode(
			"\n",
			array(
				'Stripe key sk_live_abc123def456',
				'Email admin@example.com',
				'Path /opt/biopentra/dev/universal-multicurrency/universal-multicurrency.php',
				'DB_PASSWORD=supersecret',
			)
		);

		$output = $redactor->redact( $input );

		$this->assertStringNotContainsString( 'sk_live_abc123def456', $output );
		$this->assertStringNotContainsString( 'admin@example.com', $output );
		$this->assertStringNotContainsString( '/opt/biopentra/', $output );
		$this->assertStringNotContainsString( 'supersecret', $output );
		$this->assertStringContainsString( '[redacted-stripe-key]', $output );
	}
}
