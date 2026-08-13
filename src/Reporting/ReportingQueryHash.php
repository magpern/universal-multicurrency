<?php
/**
 * Deterministic cache key for reporting queries.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Deterministic cache key for reporting queries.
 */
final class ReportingQueryHash {

	/**
	 * Builds a stable transient key for a query and cache generation.
	 *
	 * @param ReportingQuery $query            Reporting query.
	 * @param int            $cache_generation Active cache generation.
	 */
	public static function for_query( ReportingQuery $query, int $cache_generation ): string {
		$payload = array(
			'schema'     => ReportingConstants::REPORT_SCHEMA_VERSION,
			'generation' => $cache_generation,
			'preset'     => $query->range()->preset(),
			'start'      => $query->range()->start()->format( 'Y-m-d H:i:s' ),
			'end'        => $query->range()->end()->format( 'Y-m-d H:i:s' ),
			'statuses'   => $query->statuses(),
			'currency'   => $query->transaction_currency(),
			'origin'     => $query->origin(),
			'fallback'   => $query->fallback(),
			'source'     => $query->pricing_source(),
		);

		return 'umc_report_' . md5( wp_json_encode( $payload ) );
	}
}
