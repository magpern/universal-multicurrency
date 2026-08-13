<?php
/**
 * Admin-post CSV export for reporting.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Reporting\ReportingCache;
use UMC\Reporting\ReportingCsvRenderer;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingQueryTooLargeException;

/**
 * Admin-post CSV export for reporting.
 */
final class ReportingExportController {

	public const ACTION = 'umc_export_reporting_csv';

	/**
	 * Binds the controller to reporting services.
	 *
	 * @param ReportingCache       $cache        Reporting cache.
	 * @param ReportingCsvRenderer $csv_renderer CSV renderer.
	 */
	public function __construct(
		private ReportingCache $cache,
		private ReportingCsvRenderer $csv_renderer
	) {
	}

	/**
	 * Registers the admin-post export handler.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handles an authenticated CSV export request.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export reporting data.', 'universal-multicurrency' ) );
		}

		check_admin_referer( self::ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		$query = ReportingQuery::from_input( wp_unslash( $_GET ) );

		try {
			$result = $this->cache->get( $query, true );
		} catch ( ReportingQueryTooLargeException $exception ) {
			wp_die( esc_html( $exception->getMessage() ) );
		}

		$this->csv_renderer->stream( $result );
		exit;
	}
}
