<?php
/**
 * Shared helper for reading real WooCommerce log output written by
 * FixedPriceCsvIntegration's `umc-csv-import` channel.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

/**
 * Reads WooCommerce's real on-disk log files (WC_Log_Handler_File,
 * WooCommerce's default handler since long before this plugin's WC 8.2.5
 * floor) rather than mocking `wc_get_logger()` -- `wc_get_logger()` caches a
 * process-wide singleton on first use, so swapping its underlying class in
 * one test would leak into every later test in the same PHPUnit process.
 *
 * Reads the log directory directly via the `WC_LOG_DIR` constant rather than
 * through `Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController`
 * (WooCommerce's newer internal admin log-browsing API): that class is not
 * registered in WooCommerce's dependency-injection container at this
 * plugin's WC 8.2.5 floor (`wc_get_container()->get(FileController::class)`
 * throws `NotFoundException` there, confirmed by the `floor` CI leg), while
 * `WC_LOG_DIR` and the plain-text `{source}-{date}-{hash}.log` file naming
 * convention are both part of `WC_Log_Handler_File`, present at the floor.
 */
trait UmcCsvImportLogTestTrait {

	/**
	 * Concatenated content of every current `umc-csv-import` log file.
	 */
	private function umc_csv_import_log_snapshot(): string {
		$dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : '';

		if ( '' === $dir || ! is_dir( $dir ) ) {
			return '';
		}

		$contents = '';

		foreach ( (array) glob( trailingslashit( $dir ) . 'umc-csv-import-*.log' ) as $path ) {
			if ( is_string( $path ) && is_readable( $path ) ) {
				$contents .= (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- Test-only read of WooCommerce's own real log file.
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
