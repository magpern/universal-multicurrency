<?php
/**
 * Serializes reporting results for transient caching.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Serializes reporting results for transient caching.
 */
final class ReportingResultSerializer {

	/**
	 * Converts a reporting result to a cache-safe array.
	 *
	 * @param ReportingResult $result Reporting result.
	 * @return array<string, mixed>
	 */
	public static function to_array( ReportingResult $result ): array {
		$performance = array();
		foreach ( $result->currency_performance()->rows() as $row ) {
			$performance[] = array(
				'currency'        => $row->currency(),
				'order_count'     => $row->order_count(),
				'order_value'     => $row->order_value(),
				'refunded_value'  => $row->refunded_value(),
				'net_order_value' => $row->net_order_value(),
			);
		}

		$pricing  = $result->pricing_source();
		$origin   = $result->origin();
		$fallback = $result->checkout_fallback();
		$stats    = $result->statistics();
		$diag     = $result->diagnostics();

		$rate_rows = array();
		foreach ( $result->rate_provenance()->rows() as $row ) {
			$rate_rows[] = array(
				'rate_source' => $row->rate_source(),
				'provider'    => $row->provider(),
				'order_count' => $row->order_count(),
			);
		}

		$query = $result->query();

		return array(
			'query'                 => array(
				'preset'         => $query->range()->preset(),
				'start'          => $query->range()->start()->format( 'Y-m-d' ),
				'end'            => $query->range()->end()->format( 'Y-m-d' ),
				'statuses'       => $query->statuses(),
				'currency'       => $query->transaction_currency(),
				'origin'         => $query->origin(),
				'fallback'       => $query->fallback(),
				'pricing_source' => $query->pricing_source(),
			),
			'performance'           => $performance,
			'pricing_source'        => array(
				'fixed'     => $pricing->fixed_total(),
				'converted' => $pricing->converted_total(),
				'unknown'   => $pricing->unknown_total(),
			),
			'origin'                => array(
				'customer'         => $origin->customer_count(),
				'visitor_location' => $origin->visitor_location_count(),
				'unknown'          => $origin->unknown_count(),
			),
			'checkout_fallback'     => array(
				'fallback'         => $fallback->fallback_count(),
				'shopper_mismatch' => $fallback->shopper_mismatch_count(),
				'selected_mode'    => $fallback->selected_mode_count(),
				'store_mode'       => $fallback->store_mode_count(),
				'unknown_checkout' => $fallback->unknown_checkout_data_count(),
			),
			'rate_provenance'       => $rate_rows,
			'statistics'            => array(
				'qualifying_orders' => $stats->qualifying_orders(),
				'net_order_value'   => $stats->net_order_value(),
				'active_currencies' => $stats->active_currencies(),
				'fixed_price_share' => $stats->fixed_price_share(),
			),
			'diagnostics'           => array(
				'legacy_orders'         => $diag->legacy_orders(),
				'partial_snapshots'     => $diag->partial_snapshots(),
				'unresolvable_currency' => $diag->unresolvable_currency_orders(),
				'unknown_origin'        => $diag->unknown_origin_orders(),
			),
			'repository_load_count' => $result->repository_load_count(),
		);
	}

	/**
	 * Rehydrates a reporting result from cached array data.
	 *
	 * @param array<string, mixed> $data Cached reporting payload.
	 */
	public static function from_array( array $data ): ReportingResult {
		$query_input = is_array( $data['query'] ?? null ) ? $data['query'] : array();
		$query       = ReportingQuery::from_input(
			array(
				'preset'         => $query_input['preset'] ?? ReportingDateRange::PRESET_30_DAYS,
				'start'          => $query_input['start'] ?? '',
				'end'            => $query_input['end'] ?? '',
				'statuses'       => $query_input['statuses'] ?? ReportingConstants::default_statuses(),
				'currency'       => $query_input['currency'] ?? '',
				'origin'         => $query_input['origin'] ?? '',
				'fallback'       => $query_input['fallback'] ?? '',
				'pricing_source' => $query_input['pricing_source'] ?? '',
			)
		);

		$performance_rows = array();
		foreach ( (array) ( $data['performance'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$performance_rows[] = new CurrencyPerformanceRow(
				(string) ( $row['currency'] ?? '' ),
				(int) ( $row['order_count'] ?? 0 ),
				(float) ( $row['order_value'] ?? 0.0 ),
				(float) ( $row['refunded_value'] ?? 0.0 ),
				(float) ( $row['net_order_value'] ?? 0.0 )
			);
		}

		$pricing_data = is_array( $data['pricing_source'] ?? null ) ? $data['pricing_source'] : array();
		$pricing      = new PricingSourceReport(
			(float) ( $pricing_data['fixed'] ?? 0.0 ),
			(float) ( $pricing_data['converted'] ?? 0.0 ),
			(float) ( $pricing_data['unknown'] ?? 0.0 )
		);

		$origin_data = is_array( $data['origin'] ?? null ) ? $data['origin'] : array();
		$origin      = new OriginReport(
			(int) ( $origin_data['customer'] ?? 0 ),
			(int) ( $origin_data['visitor_location'] ?? 0 ),
			(int) ( $origin_data['unknown'] ?? 0 )
		);

		$fallback_data = is_array( $data['checkout_fallback'] ?? null ) ? $data['checkout_fallback'] : array();
		$fallback      = new CheckoutFallbackReport(
			(int) ( $fallback_data['fallback'] ?? 0 ),
			(int) ( $fallback_data['shopper_mismatch'] ?? 0 ),
			(int) ( $fallback_data['selected_mode'] ?? 0 ),
			(int) ( $fallback_data['store_mode'] ?? 0 ),
			(int) ( $fallback_data['unknown_checkout'] ?? 0 )
		);

		$rate_rows = array();
		foreach ( (array) ( $data['rate_provenance'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rate_rows[] = new RateProvenanceRow(
				(string) ( $row['rate_source'] ?? '' ),
				(string) ( $row['provider'] ?? '' ),
				(int) ( $row['order_count'] ?? 0 )
			);
		}

		$stats_data = is_array( $data['statistics'] ?? null ) ? $data['statistics'] : array();
		$share_raw  = $stats_data['fixed_price_share'] ?? null;
		$statistics = new ReportingStatisticsSummary(
			(int) ( $stats_data['qualifying_orders'] ?? 0 ),
			(float) ( $stats_data['net_order_value'] ?? 0.0 ),
			(int) ( $stats_data['active_currencies'] ?? 0 ),
			null === $share_raw ? null : (float) $share_raw
		);

		$diag_data = is_array( $data['diagnostics'] ?? null ) ? $data['diagnostics'] : array();
		$diag      = new ReportingDiagnostics(
			(int) ( $diag_data['legacy_orders'] ?? 0 ),
			(int) ( $diag_data['partial_snapshots'] ?? 0 ),
			(int) ( $diag_data['unresolvable_currency'] ?? 0 ),
			(int) ( $diag_data['unknown_origin'] ?? 0 )
		);

		return new ReportingResult(
			$query,
			new CurrencyPerformanceReport( $performance_rows ),
			$pricing,
			$origin,
			$fallback,
			new RateProvenanceReport( $rate_rows ),
			$statistics,
			$diag,
			(int) ( $data['repository_load_count'] ?? 0 )
		);
	}
}
