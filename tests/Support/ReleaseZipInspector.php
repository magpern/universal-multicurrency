<?php
/**
 * Validates a built release ZIP against RC packaging rules.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

/**
 * Inspects the actual archive produced by bin/build-zip.sh.
 */
final class ReleaseZipInspector {

	/**
	 * Paths that must never appear inside the release ZIP.
	 *
	 * @var list<string>
	 */
	public const FORBIDDEN_PATH_FRAGMENTS = array(
		'tests/',
		'docs/',
		'docs/plans/',
		'.git/',
		'.github/',
		'phpunit',
		'phpcs',
		'.env',
		'.phpunit.result.cache',
	);

	/**
	 * Required entries relative to the plugin root inside the archive.
	 *
	 * @var list<string>
	 */
	public const REQUIRED_ENTRIES = array(
		'universal-multicurrency/universal-multicurrency.php',
		'universal-multicurrency/uninstall.php',
		'universal-multicurrency/languages/universal-multicurrency.pot',
		'universal-multicurrency/vendor/autoload.php',
	);

	/**
	 * @return array{zip: string, entries: list<string>, violations: list<string>}
	 *
	 * @throws \RuntimeException When the archive cannot be read or opened.
	 */
	public static function inspect( string $zip_path ): array {
		if ( ! is_readable( $zip_path ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal audit diagnostic.
			throw new \RuntimeException( 'Release ZIP is not readable: ' . $zip_path );
		}

		$zip    = new \ZipArchive();
		$opened = $zip->open( $zip_path );

		if ( true !== $opened ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal audit diagnostic.
			throw new \RuntimeException( 'Could not open release ZIP: ' . $zip_path );
		}

		$entries    = array();
		$violations = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive API.
			$name = (string) $zip->getNameIndex( $i );

			if ( str_ends_with( $name, '/' ) ) {
				continue;
			}

			$entries[] = $name;

			foreach ( self::FORBIDDEN_PATH_FRAGMENTS as $fragment ) {
				if ( false !== strpos( $name, $fragment ) ) {
					$violations[] = 'Forbidden path fragment "' . $fragment . '" in ' . $name;
				}
			}

			if ( 1 === preg_match( '/vendor\/(phpunit|squizlabs|wp-coding-standards|dealerdirect|infection)/', $name ) ) {
				$violations[] = 'Development dependency shipped in ' . $name;
			}
		}

		$zip->close();

		sort( $entries, SORT_STRING );

		foreach ( self::REQUIRED_ENTRIES as $required ) {
			if ( ! in_array( $required, $entries, true ) ) {
				$violations[] = 'Missing required entry: ' . $required;
			}
		}

		$roots = array_values(
			array_unique(
				array_map(
					static fn( string $entry ): string => explode( '/', $entry )[0] ?? $entry,
					$entries
				)
			)
		);

		if ( array( 'universal-multicurrency' ) !== $roots ) {
			$violations[] = 'ZIP must contain exactly one top-level directory "universal-multicurrency"; found: ' . implode( ', ', $roots );
		}

		if ( 1 !== preg_match( '/universal-multicurrency-[\d.]+\.zip$/', basename( $zip_path ) ) ) {
			$violations[] = 'Unexpected ZIP filename (pre-bump semver expected): ' . basename( $zip_path );
		}

		return array(
			'zip'        => $zip_path,
			'entries'    => $entries,
			'violations' => $violations,
		);
	}

	/**
	 * @throws \RuntimeException When inspection fails.
	 */
	public static function assert_clean( string $zip_path ): array {
		$result = self::inspect( $zip_path );

		if ( array() !== $result['violations'] ) {
			$message = "Release ZIP audit failed for {$zip_path}:\n- " . implode( "\n- ", $result['violations'] );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal audit diagnostic.
			throw new \RuntimeException( $message );
		}

		return $result;
	}
}
