<?php
/**
 * Unit smoke test for the cache-state CLI command class.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;

/**
 * Full behavioural coverage lives in
 * tests/integration/CLI/CacheStateCommandTest.php, where the real WP_CLI /
 * WP_CLI\Utils test stubs are available (tests/integration/bootstrap.php).
 * This class stays WP_CLI-framework-free at the class level, so this smoke
 * test only proves it autoloads and declares no `check` subcommand.
 */
final class CacheStateCommandTest extends TestCase {

	public function test_cache_state_command_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( \UMC\CLI\CacheStateCommand::class ) );
	}

	public function test_no_check_subcommand_exists(): void {
		$this->assertFalse( method_exists( \UMC\CLI\CacheStateCommand::class, 'check' ) );
	}

	public function test_declares_only_status_and_acknowledge(): void {
		$methods = array_map(
			static fn( \ReflectionMethod $method ): string => $method->getName(),
			( new \ReflectionClass( \UMC\CLI\CacheStateCommand::class ) )->getMethods( \ReflectionMethod::IS_PUBLIC )
		);

		$commands = array_values(
			array_filter(
				$methods,
				static fn( string $name ): bool => '__construct' !== $name
			)
		);
		sort( $commands );

		$this->assertSame( array( 'acknowledge', 'status' ), $commands );
	}
}
