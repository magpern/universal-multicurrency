<?php
/**
 * Transient cache for reporting results.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Transient cache for reporting results.
 */
final class ReportingCache {

	public const GENERATION_OPTION = 'umc_reporting_cache_gen';

	public const TRANSIENT_PREFIX = 'umc_report_';

	/**
	 * Binds the cache to the reporting service.
	 *
	 * @param ReportingService $service Reporting orchestrator.
	 */
	public function __construct(
		private ReportingService $service
	) {
	}

	/**
	 * Current cache generation counter.
	 */
	public function generation(): int {
		return max( 1, (int) get_option( self::GENERATION_OPTION, 1 ) );
	}

	/**
	 * Bumps the cache generation to invalidate all entries.
	 */
	public function invalidate(): void {
		update_option( self::GENERATION_OPTION, $this->generation() + 1, false );
	}

	/**
	 * Returns a cached or freshly built reporting result.
	 *
	 * @param ReportingQuery $query   Reporting query.
	 * @param bool           $refresh When true, bypasses cached values.
	 */
	public function get( ReportingQuery $query, bool $refresh = false ): ReportingResult {
		$generation = $this->generation();
		$key        = ReportingQueryHash::for_query( $query, $generation );

		if ( ! $refresh ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return ReportingResultSerializer::from_array( $cached );
			}
		}

		$result = $this->service->build( $query );
		set_transient( $key, ReportingResultSerializer::to_array( $result ), ReportingConstants::CACHE_TTL );

		return $result;
	}
}
