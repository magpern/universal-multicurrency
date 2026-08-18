<?php
/**
 * Structural guard: cache-state code must never gain infrastructure control.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CacheState;

use PHPUnit\Framework\TestCase;

/**
 * Static source guard for src/CacheState/ and src/CLI/CacheStateCommand.php
 * (ADR-0032 SS11 / docs/architecture/external-cache-state-readiness.md SS10).
 * Same kind of guard as PerformanceGuardTest / SecuritySourceGuardTest — a
 * controlled fixture / injected test input, not Infection mutation testing.
 */
final class CacheStateBoundaryGuardTest extends TestCase {

	/**
	 * Patterns that must never appear in the cache-state source files:
	 * anything that would give the plugin infrastructure control over an
	 * external cache (SSH, process execution, direct nginx/varnish coupling,
	 * cache reload/purge, outbound HTTP, direct DB access, filesystem writes).
	 *
	 * @var string
	 */
	private const FORBIDDEN_PATTERN = '/\b(ssh|proc_open|shell_exec|exec\(|nginx|varnish|reload|purge|wp_remote_\w*|curl_init|fsockopen|file_put_contents)\b|\$wpdb\b/i';

	/**
	 * Option-write/read primitives whose key argument is restricted to the
	 * cache-state store.
	 *
	 * @var string
	 */
	private const OPTION_PRIMITIVES = '/\b(get_option|add_option|update_option|delete_option)\s*\(\s*(?:CacheStateStore::OPTION|[\'"]umc_cache_state[\'"])/';

	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * Strips `//`, `#`, and `/* *\/` comments so explanatory prose about the
	 * cache boundary (which legitimately names "nginx", "reload", "purge" —
	 * the very things this feature must never do) is not mistaken for
	 * executable capability. The guard protects behaviour, not vocabulary.
	 */
	private function strip_comments( string $source ): string {
		$without_blocks = (string) preg_replace( '#/\*.*?\*/#s', '', $source );

		return (string) preg_replace( '#(^|\s)//.*$#m', '', $without_blocks );
	}

	/**
	 * @return array<int, string>
	 */
	private function guarded_files(): array {
		$files   = array( $this->root() . '/src/CLI/CacheStateCommand.php' );
		$matched = glob( $this->root() . '/src/CacheState/*.php' );

		foreach ( is_array( $matched ) ? $matched : array() as $file ) {
			$files[] = $file;
		}

		return $files;
	}

	public function test_no_infrastructure_control_patterns_in_cache_state_source(): void {
		$offenders = array();

		foreach ( $this->guarded_files() as $file ) {
			$code = $this->strip_comments( (string) file_get_contents( $file ) );

			if ( 1 === preg_match( self::FORBIDDEN_PATTERN, $code ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'src/CacheState/ and CacheStateCommand.php must never gain nginx/SSH/exec/outbound-HTTP/filesystem/SQL capability.'
		);
	}

	/**
	 * Guard self-test: a deliberately injected violation must actually be
	 * caught by the same scanning method above, proving the guard is not a
	 * tautology.
	 */
	public function test_the_forbidden_pattern_scan_actually_detects_a_violation(): void {
		$fixture = array(
			'shell_exec( "nginx -s reload" );',
			'proc_open( "ssh user@host", array(), $pipes );',
			'wp_remote_post( "https://example.test" );',
			'$wpdb->query( "SELECT 1" );',
			'file_put_contents( "/tmp/x", "y" );',
		);

		foreach ( $fixture as $line ) {
			$this->assertSame(
				1,
				preg_match( self::FORBIDDEN_PATTERN, $line ),
				'Guard self-test failed to detect an injected violation: ' . $line
			);
		}

		$this->assertSame(
			0,
			preg_match( self::FORBIDDEN_PATTERN, '$hash = substr( hash( \'sha256\', $canonical ), 0, 16 );' ),
			'Guard must not false-positive on ordinary domain code.'
		);
	}

	/**
	 * Persistence ownership: only CacheStateStore.php may pass the
	 * cache-state option key to an option-write/read primitive anywhere in
	 * src/. This is narrower than "only file containing the literal string" —
	 * Site Health field ids, CLI labels, and PersistedKeys::option_keys()'s
	 * plain constant reference may legitimately mention the key without
	 * touching storage.
	 */
	public function test_only_cache_state_store_passes_the_option_key_to_an_option_primitive(): void {
		$root      = dirname( __DIR__, 3 ) . '/src';
		$iterator  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
		$offenders = array();

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			if ( 'CacheStateStore.php' === $file->getFilename() ) {
				continue;
			}

			if ( 1 === preg_match( self::OPTION_PRIMITIVES, (string) file_get_contents( $file->getPathname() ) ) ) {
				$offenders[] = $file->getFilename();
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'CacheStateStore must be the sole runtime gateway passing the cache-state option key to an option primitive.'
		);
	}

	/**
	 * Guard self-test for the persistence-ownership rule: an injected call
	 * from a hypothetical non-owning file must be caught, and a plain
	 * constant reference (PersistedKeys' documented, permitted usage) must
	 * not trip it.
	 */
	public function test_the_option_primitive_scan_actually_detects_a_violation(): void {
		$this->assertSame(
			1,
			preg_match( self::OPTION_PRIMITIVES, 'update_option( CacheStateStore::OPTION, array() );' ),
			'Guard self-test failed to detect an injected non-owning write.'
		);
		$this->assertSame(
			1,
			preg_match( self::OPTION_PRIMITIVES, "delete_option( 'umc_cache_state' );" ),
			'Guard self-test failed to detect an injected non-owning delete by literal key.'
		);
		$this->assertSame(
			0,
			preg_match( self::OPTION_PRIMITIVES, 'CacheStateStore::OPTION,' ),
			'Guard must not false-positive on a plain constant reference (PersistedKeys::option_keys()).'
		);
	}
}
