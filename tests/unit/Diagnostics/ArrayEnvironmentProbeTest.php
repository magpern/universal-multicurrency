<?php
/**
 * Unit tests for the EnvironmentProbe test double.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;

/**
 * Validates the test double reports supplied evidence and defaults absent
 * signatures to false.
 */
final class ArrayEnvironmentProbeTest extends TestCase {

	public function test_evaluate_reports_evidence_supplied_at_construction(): void {
		$signature = new Signature( SignatureKind::CLASS_NAME, 'Example', 40 );
		$probe     = new ArrayEnvironmentProbe( array( $signature->key() => true ) );

		$this->assertSame( array( 'class:Example' => true ), $probe->evaluate( array( $signature ) ) );
	}

	public function test_evaluate_defaults_a_missing_key_to_false(): void {
		$signature = new Signature( SignatureKind::HOOK, 'example_hook', 10 );
		$probe     = new ArrayEnvironmentProbe( array() );

		$this->assertSame( array( 'hook:example_hook' => false ), $probe->evaluate( array( $signature ) ) );
	}

	public function test_evaluate_handles_multiple_signatures_independently(): void {
		$present = new Signature( SignatureKind::PLUGIN_PATH, 'example/index.php', 60 );
		$absent  = new Signature( SignatureKind::HOOK, 'example_hook', 10 );

		$probe = new ArrayEnvironmentProbe( array( $present->key() => true ) );

		$this->assertSame(
			array(
				'plugin_path:example/index.php' => true,
				'hook:example_hook'             => false,
			),
			$probe->evaluate( array( $present, $absent ) )
		);
	}
}
