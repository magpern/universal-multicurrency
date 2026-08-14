<?php
/**
 * CSV export renderer for immutable reporting results.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * CSV export renderer for immutable reporting results.
 */
final class ReportingCsvRenderer {

	/**
	 * Builds CSV rows for a reporting result.
	 *
	 * @param ReportingResult $result Reporting result.
	 * @return list<list<string>>
	 */
	public function rows( ReportingResult $result ): array {
		$rows   = array();
		$rows[] = array( __( 'Section', 'universal-multicurrency' ), __( 'Key', 'universal-multicurrency' ), __( 'Value', 'universal-multicurrency' ) );

		foreach ( $result->currency_performance()->rows() as $row ) {
			$rows[] = array(
				'currency_performance',
				$row->currency() . ' order_count',
				(string) $row->order_count(),
			);
			$rows[] = array(
				'currency_performance',
				$row->currency() . ' order_value',
				$this->format_amount( $row->order_value() ),
			);
			$rows[] = array(
				'currency_performance',
				$row->currency() . ' refunded_value',
				$this->format_amount( $row->refunded_value() ),
			);
			$rows[] = array(
				'currency_performance',
				$row->currency() . ' net_order_value',
				$this->format_amount( $row->net_order_value() ),
			);
			$rows[] = array(
				'currency_performance',
				$row->currency() . ' average_order_value',
				$this->format_amount( $row->average_order_value() ),
			);
		}

		$pricing = $result->pricing_source();
		$rows[]  = array( 'pricing_source', 'fixed', $this->format_amount( $pricing->fixed_total() ) );
		$rows[]  = array( 'pricing_source', 'converted', $this->format_amount( $pricing->converted_total() ) );
		$rows[]  = array( 'pricing_source', 'unknown', $this->format_amount( $pricing->unknown_total() ) );

		$origin = $result->origin();
		$rows[] = array( 'origin', 'customer', (string) $origin->customer_count() );
		$rows[] = array( 'origin', 'visitor_location', (string) $origin->visitor_location_count() );
		$rows[] = array( 'origin', 'unknown', (string) $origin->unknown_count() );

		return $rows;
	}

	/**
	 * Streams a reporting result as a CSV download.
	 *
	 * @param ReportingResult $result Reporting result.
	 */
	public function stream( ReportingResult $result ): void {
		$filename = 'umc-reporting-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			return;
		}

		foreach ( $this->rows( $result ) as $row ) {
			fputcsv(
				$output,
				array_map(
					array( $this, 'escape_csv_cell' ),
					$row
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream for CSV download.
	}

	/**
	 * Formats a monetary amount for CSV output.
	 *
	 * @param float $amount Monetary amount.
	 */
	private function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Prefixes CSV cells that could be interpreted as formulas.
	 *
	 * @param string $value Raw cell value.
	 */
	private function escape_csv_cell( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
