<?php
/**
 * Reporting constants and defaults.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Reporting constants and defaults.
 */
final class ReportingConstants {

	public const REPORT_SCHEMA_VERSION = 1;

	public const CACHE_TTL = 900;

	public const BATCH_SIZE = 200;

	public const MAX_UNBOUNDED_ORDERS = 50000;

	public const ORIGIN_UNKNOWN = 'unknown';

	public const SOURCE_FIXED = 'fixed';

	public const SOURCE_CONVERTED = 'converted';

	public const SOURCE_UNKNOWN = 'unknown';

	/**
	 * Default qualifying order statuses.
	 *
	 * @return list<string>
	 */
	public static function default_statuses(): array {
		return array( 'processing', 'completed' );
	}

	/**
	 * All filterable WC order statuses for reporting UI.
	 *
	 * @return list<string>
	 */
	public static function selectable_statuses(): array {
		return array(
			'pending',
			'processing',
			'on-hold',
			'completed',
			'cancelled',
			'refunded',
			'failed',
		);
	}
}
