<?php
/**
 * WP-CLI commands for external cache-state readiness.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CLI;

use UMC\CacheState\CacheStateService;

/**
 * Thin CLI wrapper over {@see CacheStateService}. Modeled directly on
 * {@see RatesCommand}. Does not extend {@see \WP_CLI_Command} so the class
 * remains autoloadable in unit tests.
 */
final class CacheStateCommand {

	/**
	 * Binds CLI handlers to the shared cache-state service.
	 *
	 * @param CacheStateService $service Cache-state read/acknowledge orchestrator.
	 */
	public function __construct( private CacheStateService $service ) {
	}

	/**
	 * Prints the current external cache-state readiness report.
	 *
	 * `reconciliation_required: true` is a normal, successful result, not a
	 * command failure — this command always exits 0 when it prints the
	 * report. A non-zero exit means the state is unknown/unavailable, never
	 * "the plugin is inactive."
	 *
	 * ## OPTIONS
	 *
	 * [--format=<table|json>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc cache-state status
	 *     wp umc cache-state status --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function status( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		$report = $this->service->report();
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $report->to_array() ) );
			return;
		}

		$rows = array();

		foreach ( $report->to_array() as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			} elseif ( is_array( $value ) ) {
				$value = implode( ',', $value );
			} elseif ( null === $value ) {
				$value = '';
			}

			$rows[] = array(
				'field' => (string) $key,
				'value' => (string) $value,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}

	/**
	 * Records an external operator or tool's claim that the current
	 * cache-critical state has been reconciled.
	 *
	 * This is a claim, not a verification: it does not confirm nginx, a CDN,
	 * or any proxy runtime was actually updated, reloaded, or is serving
	 * correctly. Follow the reconcile -> re-read -> acknowledge transaction
	 * documented in docs/CLI.md; acknowledging a hash captured before
	 * reconciliation completed can leave a stale claim on record.
	 *
	 * ## OPTIONS
	 *
	 * <hash>
	 * : The 16-character hex state hash to acknowledge. Must match the
	 * current value from `wp umc cache-state status` exactly; only the
	 * current hash is accepted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc cache-state acknowledge a1b2c3d4e5f60718
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args: the hash to acknowledge.
	 * @param array<string, string> $assoc_args Associative args (unused).
	 */
	public function acknowledge( array $args = array(), array $assoc_args = array() ): void {
		unset( $assoc_args );

		$submitted = isset( $args[0] ) ? (string) $args[0] : '';
		$current   = $this->service->report()->state_hash();

		if ( ! $this->service->acknowledge( $submitted ) ) {
			\WP_CLI::error(
				sprintf(
					'Rejected: "%1$s" does not match the current state hash. Current state hash is %2$s. Reconcile the external cache first, then re-read the current hash and acknowledge that exact value.',
					$submitted,
					$current
				),
				true
			);
			return;
		}

		\WP_CLI::success(
			sprintf( 'Recorded external cache reconciliation claim for state hash %s.', $submitted )
		);
	}
}
