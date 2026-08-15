<?php
/**
 * M24 WP1 characterization: the established WP-CLI authorization precedent.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;

/**
 * Locks the source-level fact that `wp umc rates` performs no
 * `current_user_can()` authorization checks — WP-CLI execution is trusted
 * administrative/system access. ADR-0029 requires `wp umc prices` to follow
 * this exact precedent rather than introduce a new authorization model.
 */
final class CliAuthorizationPrecedentTest extends TestCase {

	public function test_rates_command_performs_no_capability_checks(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/CLI/RatesCommand.php'
		);

		$this->assertStringNotContainsString(
			'current_user_can',
			$source,
			'RatesCommand.php is the established wp-cli authorization precedent: it must ' .
			'perform zero current_user_can() checks, trusting WP-CLI execution entirely.'
		);
	}

	/**
	 * M24 falsification T: the CLI path must never introduce a
	 * current_user_can() authorization check, matching the RatesCommand
	 * precedent above.
	 */
	public function test_prices_command_performs_no_capability_checks(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/CLI/PricesCommand.php'
		);

		$this->assertStringNotContainsString(
			'current_user_can',
			$source,
			'PricesCommand.php must match the wp umc rates authorization precedent: zero ' .
			'current_user_can() checks, trusting WP-CLI execution entirely (ADR-0029).'
		);
	}
}
