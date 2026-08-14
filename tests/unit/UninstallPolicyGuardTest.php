<?php
/**
 * Structural guard: uninstall.php must honour the ADR-0009 retention policy.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UMC\PersistedKeys;
use UMC\Rates\RateUpdateState;
use UMC\Reporting\ReportingCache;
use UMC\Settings;

/**
 * Pins behavioural invariants on uninstall.php rather than individual literals.
 */
final class UninstallPolicyGuardTest extends TestCase {

	/**
	 * APIs that must never appear in uninstall.php because they could delete
	 * commerce or user metadata.
	 *
	 * @var list<string>
	 */
	private const FORBIDDEN_PATTERNS = array(
		'/\bdelete_post_meta\s*\(/',
		'/\bdelete_metadata\s*\(/',
		'/\bdelete_user_meta\s*\(/',
		'/\bwp_delete_user\s*\(/',
		'/\$wpdb\b/',
		'/\bDROP\s+TABLE\b/i',
		'/\bTRUNCATE\b/i',
	);

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function uninstall_path(): string {
		return $this->root() . '/uninstall.php';
	}

	private function uninstall_source(): string {
		return (string) file_get_contents( $this->uninstall_path() );
	}

	/**
	 * @return list<string>
	 */
	private function delete_option_keys_from_uninstall(): array {
		$source = $this->uninstall_source();

		if ( ! preg_match_all( "/delete_option\s*\(\s*['\"]([^'\"]+)['\"]/", $source, $matches ) ) {
			return array();
		}

		return array_values( $matches[1] );
	}

	public function test_uninstall_file_exists(): void {
		$this->assertFileExists( $this->uninstall_path() );
	}

	public function test_uninstall_deletes_only_contracted_option_keys(): void {
		$this->assertSame(
			PersistedKeys::uninstall_deleted_option_keys(),
			$this->delete_option_keys_from_uninstall(),
			'uninstall.php must delete exactly the option keys in PersistedKeys::uninstall_deleted_option_keys().'
		);
	}

	public function test_uninstall_deleted_option_keys_match_persisted_inventory(): void {
		$this->assertSame(
			array( Settings::OPTION, RateUpdateState::OPTION, ReportingCache::GENERATION_OPTION ),
			PersistedKeys::uninstall_deleted_option_keys()
		);
	}

	public function test_uninstall_never_uses_forbidden_deletion_apis(): void {
		$source = $this->uninstall_source();

		foreach ( self::FORBIDDEN_PATTERNS as $pattern ) {
			$this->assertDoesNotMatchRegularExpression(
				$pattern,
				$source,
				'uninstall.php must not use metadata or SQL deletion APIs (ADR-0009).'
			);
		}
	}

	public function test_uninstall_policy_preserves_all_order_and_refund_meta_keys(): void {
		$policy = PersistedKeys::uninstall_policy();

		$this->assertSame( PersistedKeys::order_meta_keys(), $policy['preserve_order_meta'] );
		$this->assertSame( PersistedKeys::refund_meta_keys(), $policy['preserve_refund_meta'] );
	}

	public function test_uninstall_policy_preserves_dismissal_user_meta(): void {
		$this->assertSame(
			array(
				'umc_dismissed_notices',
				\UMC\Admin\GeoSandboxController::RESULT_META,
				\UMC\Admin\Geo\GeoSandboxRecentStore::META_KEY,
			),
			PersistedKeys::uninstall_preserved_user_meta_keys()
		);
	}

	public function test_persisted_data_doc_documents_adr_0009(): void {
		$source = (string) file_get_contents( $this->root() . '/docs/PERSISTED_DATA.md' );

		$this->assertStringContainsString( 'docs/adr/0009-uninstall-retention-policy.md', $source );
	}
}
