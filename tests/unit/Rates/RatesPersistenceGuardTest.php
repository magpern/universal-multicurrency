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

	public function test_only_the_state_store_writes_the_rate_state_option(): void {
		$allowed = array( 'RateUpdateState.php' );

		$this->assert_pattern_absent_from(
			array_values(
				array_filter(
					$this->umc_source_files(),
					static fn( string $file ): bool => str_contains( $file, '/src/Rates/' )
						&& ! in_array( basename( $file ), $allowed, true )
				)
			),
			'/\bupdate_option\s*\(/',
			'Only RateUpdateState may call update_option() inside src/Rates/.'
		);
	}

	public function test_only_the_transport_performs_outbound_http(): void {
		$this->assert_pattern_absent_from(
			array_values(
				array_filter(
					$this->umc_source_files(),
					static fn( string $file ): bool => 'WordPressHttpTransport.php' !== basename( $file )
				)
			),
			'/\b(?:wp_remote_get|wp_remote_post|wp_remote_request|wp_safe_remote_get|wp_safe_remote_post|wp_safe_remote_request|curl_init|fsockopen)\s*\(/',
			'Outbound HTTP must stay inside Rates/Http/WordPressHttpTransport.php.'
		);
	}

	public function test_the_transport_uses_only_the_safe_remote_api(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Rates/Http/WordPressHttpTransport.php' );

		$this->assertStringContainsString( 'wp_safe_remote_get(', $source );
		$this->assertDoesNotMatchRegularExpression( '/\bwp_remote_(?:get|post|request)\s*\(/', $source );
	}

	public function test_scheduler_never_references_exchange_rate_source(): void {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Rates/Scheduler.php' );

		$this->assertStringNotContainsString( 'ExchangeRateSource', $source );
		$this->assertStringNotContainsString( 'FrankfurterRateSource', $source );
	}
}
