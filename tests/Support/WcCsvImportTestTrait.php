<?php
/**
 * Shared helper for building real CSV files and driving WooCommerce's native
 * `WC_Product_CSV_Importer` against them.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

/**
 * Used by M25 WP1's empirical characterization of WooCommerce's own product
 * CSV import pipeline. Deliberately talks to `WC_Product_CSV_Importer`
 * directly (bypassing the admin controller's upload/nonce handling, which is
 * WooCommerce's own already-authorized surface and out of scope here) so
 * tests can assert on real importer behavior against hand-built CSV content.
 */
trait WcCsvImportTestTrait {

	/**
	 * Temp CSV files created during the test, removed in tear_down().
	 *
	 * @var array<int, string>
	 */
	private array $umc_csv_temp_files = array();

	/**
	 * Writes a CSV file from a header row and data rows.
	 *
	 * @param array<int, string>             $header Header row, in raw-column order.
	 * @param array<int, array<int, string>> $rows   Data rows, same column order as $header.
	 * @return string Path to the written file.
	 */
	private function write_csv_file( array $header, array $rows ): string {
		$path = tempnam( sys_get_temp_dir(), 'umc_csv_' );
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-only temp file, WP_Filesystem is unavailable in the PHPUnit process.
		$path .= '.csv';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Test-only temp file; WC_Product_CSV_Importer itself reads via fopen()/fgetcsv().
		$handle = fopen( $path, 'w' );
		fputcsv( $handle, $header );

		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Test-only temp file.

		$this->umc_csv_temp_files[] = $path;

		return $path;
	}

	/**
	 * Builds and runs a real `WC_Product_CSV_Importer` against a hand-built
	 * CSV file. Headers are used as-is as mapped keys (no controller
	 * auto-mapping step) unless `mapping` is supplied in $args.
	 *
	 * @param array<int, string>             $header Header row.
	 * @param array<int, array<int, string>> $rows   Data rows.
	 * @param array<string, mixed>           $args   Extra/overriding importer params.
	 * @return array Result of {@see \WC_Product_CSV_Importer::import()}.
	 */
	private function run_csv_import( array $header, array $rows, array $args = array() ): array {
		$path = $this->write_csv_file( $header, $rows );

		$params = array_merge(
			array(
				'parse'           => true,
				'update_existing' => true,
			),
			$args
		);

		$importer = new \WC_Product_CSV_Importer( $path, $params );

		return $importer->import();
	}

	/**
	 * Removes temp CSV files written during the test.
	 */
	private function clean_up_csv_temp_files(): void {
		foreach ( $this->umc_csv_temp_files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test-only temp file cleanup.
			}
		}

		$this->umc_csv_temp_files = array();
	}
}
