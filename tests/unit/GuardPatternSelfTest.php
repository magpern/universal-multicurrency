<?php
/**
 * Verifies guard helper patterns fail when a violation is present.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Mutation-style self-check for the shared absent/present guard assertions.
 */
final class GuardPatternSelfTest extends TestCase {

	use \UMC\Tests\Support\SourceGuardTrait;

	public function test_assert_pattern_absent_from_fails_when_pattern_matches(): void {
		$file = tempnam( sys_get_temp_dir(), 'umc-guard-' );
		$this->assertNotFalse( $file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ephemeral guard-pattern fixture outside the repo tree.
		file_put_contents( $file, "<?php\nclass Converter {}\n" );

		try {
			$this->expectException( \PHPUnit\Framework\ExpectationFailedException::class );
			$this->assert_pattern_absent_from(
				array( $file ),
				'/Converter/',
				'Guard self-test: pattern should have matched.'
			);
		} finally {
			if ( is_string( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes the ephemeral guard-pattern fixture.
				unlink( $file );
			}
		}
	}

	public function test_assert_pattern_present_in_fails_when_pattern_is_absent(): void {
		$file = tempnam( sys_get_temp_dir(), 'umc-guard-' );
		$this->assertNotFalse( $file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ephemeral guard-pattern fixture outside the repo tree.
		file_put_contents( $file, "<?php\n// clean\n" );

		try {
			$this->expectException( \PHPUnit\Framework\AssertionFailedError::class );
			$this->assert_pattern_present_in(
				array( $file ),
				'/Converter/',
				'Guard self-test: pattern should have been absent.'
			);
		} finally {
			if ( is_string( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes the ephemeral guard-pattern fixture.
				unlink( $file );
			}
		}
	}
}
