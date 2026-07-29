<?php
/**
 * Defense-in-depth redaction for support reports.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Report;

/**
 * Redacts sensitive values from plain-text reports.
 */
final class ReportRedactor {

	/**
	 * Redacts sensitive patterns from report text.
	 *
	 * @param string $report Plain-text report.
	 */
	public function redact( string $report ): string {
		$patterns = array(
			'/sk_live_[A-Za-z0-9]+/'  => '[redacted-stripe-key]',
			'/pk_live_[A-Za-z0-9]+/'  => '[redacted-stripe-key]',
			'/AKIA[0-9A-Z]{16}/'      => '[redacted-access-key]',
			'/-----BEGIN [A-Z ]+-----[\s\S]+?-----END [A-Z ]+-----/' => '[redacted-secret-block]',
			'/DB_PASSWORD\s*=\s*.+/i' => 'DB_PASSWORD=[redacted]',
			'/AUTH_KEY\s*=\s*.+/i'    => 'AUTH_KEY=[redacted]',
			'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => '[redacted-email]',
			'#/opt/[^\s]+#'           => '[redacted-path]',
			'#/var/www/[^\s]+#'       => '[redacted-path]',
			'#/home/[^\s]+#'          => '[redacted-path]',
		);

		foreach ( $patterns as $pattern => $replacement ) {
			$report = (string) preg_replace( $pattern, $replacement, $report );
		}

		return $report;
	}
}
