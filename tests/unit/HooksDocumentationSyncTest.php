<?php
/**
 * Binds docs/HOOKS.md to the hooks production code actually registers.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Rates\Scheduler;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Milestone 8 review closure: every documented `umc_*` extension point exists
 * in `src/`, and every one `src/` fires or filters is documented.
 *
 * @group documentation
 */
final class HooksDocumentationSyncTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * Hook names documented for integrators but deliberately not wired.
	 *
	 * `umc_convert_fee` is the published opt-in seam for fee conversion; no
	 * Milestone 3+ callback applies it, and HOOKS.md says so explicitly.
	 *
	 * @var list<string>
	 */
	private const DOCUMENTED_BUT_UNWIRED = array(
		'umc_convert_fee',
	);

	/**
	 * Hook names referenced through class constants rather than literals.
	 *
	 * @return list<string>
	 */
	private function constant_hook_names(): array {
		return array(
			DetectorRegistry::FILTER,
			Scheduler::HOOK,
		);
	}

	private function hooks_document(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/HOOKS.md' );
	}

	/**
	 * Every `umc_*` hook name passed to a hook API call site in `src/`.
	 *
	 * @return list<string>
	 */
	private function source_hook_names(): array {
		$names = $this->constant_hook_names();

		foreach ( $this->umc_source_files() as $file ) {
			$source = (string) file_get_contents( $file );

			if ( ! preg_match_all(
				'/(?:do_action|apply_filters|add_action|add_filter)\s*\(\s*\'(umc_[a-z0-9_]+)\'/',
				$source,
				$matches
			) ) {
				continue;
			}

			foreach ( $matches[1] as $name ) {
				$names[] = $name;
			}
		}

		sort( $names );

		return array_values( array_unique( $names ) );
	}

	/**
	 * Every `umc_*` hook listed in the "Filters and actions the plugin provides" table.
	 *
	 * @return list<string>
	 */
	private function documented_provided_hook_names(): array {
		$doc     = $this->hooks_document();
		$heading = '## Filters and actions the plugin provides';
		$start   = strpos( $doc, $heading );

		$this->assertIsInt( $start, 'HOOKS.md must keep the provided-hooks section.' );

		$section = substr( $doc, $start + strlen( $heading ) );
		$next    = strpos( $section, "\n## " );

		if ( is_int( $next ) ) {
			$section = substr( $section, 0, $next );
		}

		preg_match_all( '/^\|\s*`(umc_[a-z0-9_]+)`/m', $section, $matches );

		$names = $matches[1];
		sort( $names );

		return array_values( array_unique( $names ) );
	}

	public function test_every_hook_fired_in_source_is_documented(): void {
		$doc     = $this->hooks_document();
		$missing = array();

		foreach ( $this->source_hook_names() as $name ) {
			if ( ! str_contains( $doc, '`' . $name . '`' ) ) {
				$missing[] = $name;
			}
		}

		$this->assertSame( array(), $missing, "Undocumented hooks in docs/HOOKS.md:\n" . implode( "\n", $missing ) );
	}

	public function test_every_documented_hook_exists_in_source(): void {
		$in_source = $this->source_hook_names();
		$stale     = array();

		foreach ( $this->documented_provided_hook_names() as $name ) {
			if ( in_array( $name, $in_source, true ) || in_array( $name, self::DOCUMENTED_BUT_UNWIRED, true ) ) {
				continue;
			}

			$stale[] = $name;
		}

		$this->assertSame( array(), $stale, "Speculative hooks documented in docs/HOOKS.md:\n" . implode( "\n", $stale ) );
	}

	public function test_milestone_eight_extension_points_are_documented(): void {
		$doc = $this->hooks_document();

		foreach (
			array(
				'umc_run_rate_update',
				'umc_rate_fetch_completed',
				'umc_exchange_rate_sources',
				'umc_settings_saved',
				'admin_post_umc_update_rates',
				'umc_rate_health',
			) as $name
		) {
			$this->assertStringContainsString( '`' . $name . '`', $doc, 'HOOKS.md must document ' . $name );
		}
	}

	public function test_scheduled_hook_constant_matches_documentation(): void {
		$this->assertSame( 'umc_run_rate_update', Scheduler::HOOK );
		$this->assertStringContainsString( '`umc_run_rate_update`', $this->hooks_document() );
	}
}
