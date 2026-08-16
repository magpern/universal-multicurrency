<?php
/**
 * Shared helper for reading real WooCommerce log output written by
 * FixedPriceCsvIntegration's `umc-csv-import` channel.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

use Automattic\WooCommerce\Internal\Admin\Logging\FileV2\File;
use Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController;

/**
 * Reads WooCommerce's real on-disk log files (the FileV2 logging system,
 * WooCommerce's own default handler) rather than mocking `wc_get_logger()` —
 * `wc_get_logger()` caches a process-wide singleton on first use, so swapping
 * its underlying class in one test would leak into every later test in the
 * same PHPUnit process.
 */
trait UmcCsvImportLogTestTrait {

	/**
	 * Concatenated content of every current `umc-csv-import` log file.
	 */
	private function umc_csv_import_log_snapshot(): string {
		$controller = wc_get_container()->get( FileController::class );
		$files      = $controller->get_files( array( 'source' => 'umc-csv-import' ) );
		$contents   = '';

		if ( is_iterable( $files ) ) {
			foreach ( $files as $file ) {
				if ( $file instanceof File && is_readable( $file->get_path() ) ) {
					$contents .= (string) file_get_contents( $file->get_path() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- Test-only read of WooCommerce's own real log file.
				}
			}
		}

		return $contents;
	}

	/**
	 * Runs an operation and returns only the log content appended by it,
	 * append-only-safe against unrelated log content from earlier tests in
	 * the same process/day.
	 *
	 * @param callable $operation Operation expected to log via the umc-csv-import channel.
	 */
	private function new_umc_csv_import_log_entries( callable $operation ): string {
		$before = $this->umc_csv_import_log_snapshot();

		$operation();

		$after = $this->umc_csv_import_log_snapshot();

		return substr( $after, strlen( $before ) );
	}
}
