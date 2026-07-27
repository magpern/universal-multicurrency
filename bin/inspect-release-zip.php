#!/usr/bin/env php
<?php
/**
 * CLI wrapper for release ZIP inspection.
 *
 * Usage: bin/inspect-release-zip.php [path/to/universal-multicurrency-x.y.z.zip]
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI audit tool output.

$root = dirname( __DIR__ );

require $root . '/vendor/autoload.php';

use UMC\Tests\Support\ReleaseZipInspector;

$zip = $argv[1] ?? getenv( 'UMC_RELEASE_ZIP' );
if ( ! is_string( $zip ) ) {
	$zip = '';
}

if ( '' === $zip ) {
	fwrite( STDERR, "Usage: bin/inspect-release-zip.php <zip-path>\n" );
	exit( 1 );
}

if ( ! str_starts_with( $zip, '/' ) ) {
	$zip = $root . '/' . ltrim( $zip, '/' );
}

try {
	$result = ReleaseZipInspector::assert_clean( $zip );
} catch ( Throwable $exception ) {
	fwrite( STDERR, $exception->getMessage() . PHP_EOL );
	exit( 1 );
}

echo 'Release ZIP OK: ' . $result['zip'] . PHP_EOL;
echo 'Entries: ' . count( $result['entries'] ) . PHP_EOL;
exit( 0 );
