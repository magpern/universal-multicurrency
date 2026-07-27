<?php
/**
 * Shared helpers for structural source and hook guards.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

/**
 * Extracted from StorefrontGuardTest so diagnostics and storefront guards share
 * one implementation of the file-scan and hook-introspection helpers.
 */
trait SourceGuardTrait {

	/**
	 * Asserts that a regex matches none of the given source files.
	 *
	 * @param array<int, string> $files   Absolute file paths.
	 * @param string             $pattern PCRE pattern.
	 * @param string             $message Assertion message.
	 */
	private function assert_pattern_absent_from( array $files, string $pattern, string $message ): void {
		$offenders = array();

		foreach ( $files as $file ) {
			if ( 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame( array(), $offenders, $message );
	}

	/**
	 * Asserts that a regex matches at least one of the given source files.
	 *
	 * @param array<int, string> $files   Absolute file paths.
	 * @param string             $pattern PCRE pattern.
	 * @param string             $message Assertion message.
	 */
	private function assert_pattern_present_in( array $files, string $pattern, string $message ): void {
		foreach ( $files as $file ) {
			if ( 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) {
				$this->addToAssertionCount( 1 );
				return;
			}
		}

		$this->fail( $message );
	}

	/**
	 * Absolute paths of every PHP file under the plugin's src/ directory.
	 *
	 * @param string|null $relative_subdir Optional path relative to src/, e.g. Diagnostics.
	 *
	 * @return array<int, string>
	 */
	private function umc_source_files( ?string $relative_subdir = null ): array {
		$root = dirname( __DIR__, 2 ) . '/src';

		if ( null !== $relative_subdir ) {
			$root .= '/' . ltrim( $relative_subdir, '/' );
		}

		if ( ! is_dir( $root ) ) {
			return array();
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);

		$files = array();

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Returns src/Diagnostics PHP files, optionally excluding one basename.
	 *
	 * @return array<int, string>
	 */
	private function diagnostics_files( ?string $except_basename = null ): array {
		$files = $this->umc_source_files( 'Diagnostics' );

		if ( null === $except_basename ) {
			return $files;
		}

		return array_values(
			array_filter(
				$files,
				static function ( string $file ) use ( $except_basename ): bool {
					return basename( $file ) !== $except_basename;
				}
			)
		);
	}

	/**
	 * Returns every src/ PHP file outside src/Diagnostics/.
	 *
	 * @return array<int, string>
	 */
	private function non_diagnostics_files( ?string $except_basename = null ): array {
		$files = array_values(
			array_filter(
				$this->umc_source_files(),
				static function ( string $file ): bool {
					return false === strpos( $file, '/Diagnostics/' );
				}
			)
		);

		if ( null === $except_basename ) {
			return $files;
		}

		return array_values(
			array_filter(
				$files,
				static function ( string $file ) use ( $except_basename ): bool {
					return basename( $file ) !== $except_basename;
				}
			)
		);
	}

	/**
	 * Descriptions of callbacks on a hook that originate from this plugin.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return array<int, string>
	 */
	private function umc_callbacks_on( string $hook ): array {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$found = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) ) {
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( 0 === strpos( $class, 'UMC\\' ) ) {
						$found[] = "{$class}::{$function[1]}";
					}
				} elseif ( $function instanceof \Closure ) {
					$file = ( new \ReflectionFunction( $function ) )->getFileName();
					if ( is_string( $file ) && false !== strpos( $file, '/universal-multicurrency/src/' ) ) {
						$found[] = "closure:{$file}";
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Plugin callbacks on a hook whose class lives under src/Diagnostics/.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return array<int, string>
	 */
	private function diagnostics_callbacks_on( string $hook ): array {
		return array_values(
			array_filter(
				$this->umc_callbacks_on( $hook ),
				static function ( string $callback ): bool {
					return 0 === strpos( $callback, 'UMC\\Diagnostics\\' );
				}
			)
		);
	}
}
