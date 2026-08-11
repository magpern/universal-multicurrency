<?php
/**
 * WP-CLI commands for exchange-rate operations.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CLI;

use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateFetchResult;
use UMC\Rates\RateHealthService;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateService;
use UMC\Rates\UpdateInProgressException;
use UMC\Settings;

/**
 * Thin CLI wrapper over rate health and update services.
 *
 * Registered via {@see \WP_CLI::add_command()} when WP-CLI is present. Does not
 * extend {@see \WP_CLI_Command} so the class remains autoloadable in unit tests.
 */
final class RatesCommand {

	/**
	 * Binds CLI handlers to shared rate services.
	 *
	 * @param RateHealthService $health   Read-only health aggregator.
	 * @param RateUpdateService $updater  Rate update orchestration.
	 * @param Settings          $settings Merchant settings store.
	 * @param ExchangeRateStore $store    Rate persistence boundary.
	 */
	public function __construct(
		private RateHealthService $health,
		private RateUpdateService $updater,
		private Settings $settings,
		private ExchangeRateStore $store
	) {
	}

	/**
	 * Prints the current exchange-rate health report.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc rates status
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args (unused).
	 */
	public function status( array $args = array(), array $assoc_args = array() ): void {
		unset( $args, $assoc_args );

		$report = $this->health->report();
		$rows   = array();

		foreach ( $report->to_array() as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
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
	 * Refreshes automatic exchange rates.
	 *
	 * ## OPTIONS
	 *
	 * [--currency=<code>]
	 * : Limit the refresh to one automatic currency code.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc rates refresh
	 *     wp umc rates refresh --currency=SEK
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function refresh( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		$currency = isset( $assoc_args['currency'] ) ? strtoupper( (string) $assoc_args['currency'] ) : '';

		try {
			$result = '' === $currency
				? $this->updater->update( null )
				: $this->updater->update( array( $currency ) );
		} catch ( UpdateInProgressException $exception ) {
			\WP_CLI::error( $exception->getMessage(), true );
		}

		$this->print_refresh_result( $result );

		if ( $result->is_total_failure() ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Lists configured currencies and effective rate status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc rates list
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args (unused).
	 */
	public function list( array $args = array(), array $assoc_args = array() ): void {
		unset( $args, $assoc_args );

		$evaluator = new RateStatusEvaluator( $this->settings, $this->store );
		$rows      = array();

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			$code    = strtoupper( (string) $code );
			$enabled = ! isset( $config['enabled'] ) ? true : (bool) $config['enabled'];
			$mode    = $this->settings->get_effective_rate_mode( $code );

			$rows[] = array(
				'code'          => $code,
				'enabled'       => $enabled ? 'yes' : 'no',
				'mode'          => $mode,
				'provider_rate' => (string) ( $config['provider_rate'] ?? '' ),
				'manual_rate'   => (string) ( $config['manual_rate'] ?? '' ),
				'updated_at'    => (string) (int) ( $config['rate_updated_at'] ?? 0 ),
				'status'        => $evaluator->label_for_currency( $code ),
			);
		}

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			array( 'code', 'enabled', 'mode', 'provider_rate', 'manual_rate', 'updated_at', 'status' )
		);
	}

	/**
	 * Writes a human-readable refresh outcome and returns for exit-code handling.
	 *
	 * @param RateFetchResult $result Fetch outcome.
	 */
	private function print_refresh_result( RateFetchResult $result ): void {
		if ( $result->is_no_automatic_targets() ) {
			\WP_CLI::warning( 'No automatic currencies are configured for refresh.' );
			return;
		}

		if ( $result->is_not_modified() ) {
			\WP_CLI::success( 'Rates are already up to date (not modified).' );
			return;
		}

		if ( $result->is_total_failure() ) {
			\WP_CLI::warning( 'Rate update failed for all targeted currencies. Last known rates were preserved.' );
			return;
		}

		if ( $result->is_partial_failure() ) {
			\WP_CLI::warning(
				sprintf(
					'Partial update: %d succeeded, %d failed. Last known rates preserved for failures.',
					count( $result->quotes() ),
					count( $result->failures() )
				)
			);
			return;
		}

		\WP_CLI::success(
			sprintf( 'Updated %d currency rate(s).', count( $result->quotes() ) )
		);
	}
}
