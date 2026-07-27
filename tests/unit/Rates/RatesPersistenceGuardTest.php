<?php
/**
 * Static guard: rate persistence is confined to approved classes.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Ensures only approved classes touch option persistence for rates.
 */
final class RatesPersistenceGuardTest extends TestCase {

	use SourceGuardTrait;

	public function test_only_approved_rates_classes_call_get_option(): void {
		$allowed = array( 'RateUpdateState.php', 'ExchangeRateStore.php' );
		$files   = array_filter(
			$this->umc_source_files(),
			static fn( string $file ): bool => str_contains( $file, '/src/Rates/' )
		);

		$this->assert_pattern_absent_from(
			array_values(
				array_filter(
					$files,
					static fn( string $file ): bool => ! in_array( basename( $file ), $allowed, true )
				)
			),
			'/\bget_option\s*\(/',
			'Only RateUpdateState may call get_option() inside src/Rates/.'
		);
	}

	public function test_scheduler_never_references_exchange_rate_source(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Rates/Scheduler.php' );

		$this->assertStringNotContainsString( 'ExchangeRateSource', $source );
		$this->assertStringNotContainsString( 'FrankfurterRateSource', $source );
	}
}
