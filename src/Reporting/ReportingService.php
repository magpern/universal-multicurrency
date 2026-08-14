<?php
/**
 * Reporting orchestrator.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\CurrencySwitcher;

/**
 * Aggregates order records into immutable reporting results.
 */
final class ReportingService {

	/**
	 * Binds the service to the order reporting repository.
	 *
	 * @param OrderReportingRepository $repository HPOS-safe order loader.
	 */
	public function __construct(
		private OrderReportingRepository $repository
	) {
	}

	/**
	 * Builds an immutable reporting result for a query.
	 *
	 * @param ReportingQuery $query Reporting query.
	 */
	public function build( ReportingQuery $query ): ReportingResult {
		$records = $this->repository->fetch_records( $query );

		$currency_buckets = array();
		$fixed_total      = 0.0;
		$converted_total  = 0.0;
		$unknown_total    = 0.0;

		$customer_count       = 0;
		$visitor_count        = 0;
		$unknown_origin_count = 0;

		$fallback_count         = 0;
		$shopper_mismatch_count = 0;
		$selected_mode_count    = 0;
		$store_mode_count       = 0;
		$unknown_checkout_count = 0;

		$rate_buckets = array();

		$legacy_orders              = 0;
		$partial_snapshots          = 0;
		$unresolvable_currency      = 0;
		$unknown_origin_diagnostics = 0;

		$qualifying_orders = 0;
		$net_total         = 0.0;
		$active_currencies = array();

		$source_filter = $query->pricing_source();

		foreach ( $records as $record ) {
			$snapshot = $record->snapshot();

			if ( $snapshot->is_legacy() ) {
				++$legacy_orders;
			}
			if ( $snapshot->is_partial() ) {
				++$partial_snapshots;
			}
			if ( $record->unresolvable_currency() ) {
				++$unresolvable_currency;
				continue;
			}

			$currency = (string) $record->transaction_currency();
			if ( '' === $currency ) {
				++$unresolvable_currency;
				continue;
			}

			++$qualifying_orders;
			$active_currencies[ $currency ] = true;

			if ( ! isset( $currency_buckets[ $currency ] ) ) {
				$currency_buckets[ $currency ] = array(
					'count'    => 0,
					'order'    => 0.0,
					'refunded' => 0.0,
				);
			}

			$currency_buckets[ $currency ]['count']    += 1;
			$currency_buckets[ $currency ]['order']    += $record->order_value();
			$currency_buckets[ $currency ]['refunded'] += $record->refunded_value();
			$net_total                                 += $record->net_order_value();

			switch ( $record->reporting_origin() ) {
				case CurrencySwitcher::ORIGIN_CUSTOMER:
					++$customer_count;
					break;
				case CurrencySwitcher::ORIGIN_VISITOR_LOCATION:
					++$visitor_count;
					break;
				default:
					++$unknown_origin_count;
					++$unknown_origin_diagnostics;
					break;
			}

			$fallback = $record->fallback_occurred();
			if ( null === $fallback ) {
				++$unknown_checkout_count;
			} else {
				if ( $fallback ) {
					++$fallback_count;
				}

				$shopper = $record->shopper_currency();
				$txn     = $record->transaction_currency();
				if ( null !== $shopper && null !== $txn && $shopper !== $txn ) {
					++$shopper_mismatch_count;
				}

				$mode = $record->checkout_mode();
				if ( 'selected' === $mode ) {
					++$selected_mode_count;
				} elseif ( 'store' === $mode ) {
					++$store_mode_count;
				}
			}

			$rate_source = (string) ( $snapshot->rate_source() ?? 'unknown' );
			$provider    = (string) ( $snapshot->rate_provider() ?? '' );
			$rate_key    = $rate_source . '|' . $provider;
			if ( ! isset( $rate_buckets[ $rate_key ] ) ) {
				$rate_buckets[ $rate_key ] = array(
					'source'   => $rate_source,
					'provider' => $provider,
					'count'    => 0,
				);
			}
			++$rate_buckets[ $rate_key ]['count'];

			foreach ( $record->line_sources() as $line ) {
				$line_source = $line['source'];
				$line_total  = (float) $line['total'];

				if ( '' !== $source_filter && $source_filter !== $line_source ) {
					continue;
				}

				if ( ReportingConstants::SOURCE_FIXED === $line_source ) {
					$fixed_total += $line_total;
				} elseif ( ReportingConstants::SOURCE_CONVERTED === $line_source ) {
					$converted_total += $line_total;
				} else {
					$unknown_total += $line_total;
				}
			}
		}

		$performance_rows = array();
		foreach ( $currency_buckets as $code => $bucket ) {
			$order_value        = $bucket['order'];
			$refunded           = $bucket['refunded'];
			$performance_rows[] = new CurrencyPerformanceRow(
				$code,
				$bucket['count'],
				$order_value,
				$refunded,
				max( 0.0, $order_value - $refunded )
			);
		}

		usort(
			$performance_rows,
			static function ( CurrencyPerformanceRow $a, CurrencyPerformanceRow $b ): int {
				return strcmp( $a->currency(), $b->currency() );
			}
		);

		$rate_rows = array();
		foreach ( $rate_buckets as $bucket ) {
			$rate_rows[] = new RateProvenanceRow(
				$bucket['source'],
				$bucket['provider'],
				$bucket['count']
			);
		}

		usort(
			$rate_rows,
			static function ( RateProvenanceRow $a, RateProvenanceRow $b ): int {
				$cmp = strcmp( $a->rate_source(), $b->rate_source() );
				return 0 !== $cmp ? $cmp : strcmp( $a->provider(), $b->provider() );
			}
		);

		$pricing_report = new PricingSourceReport( $fixed_total, $converted_total, $unknown_total );

		return new ReportingResult(
			$query,
			new CurrencyPerformanceReport( $performance_rows ),
			$pricing_report,
			new OriginReport( $customer_count, $visitor_count, $unknown_origin_count ),
			new CheckoutFallbackReport(
				$fallback_count,
				$shopper_mismatch_count,
				$selected_mode_count,
				$store_mode_count,
				$unknown_checkout_count
			),
			new RateProvenanceReport( $rate_rows ),
			new ReportingStatisticsSummary(
				$qualifying_orders,
				$net_total,
				count( $active_currencies ),
				$pricing_report->fixed_share()
			),
			new ReportingDiagnostics(
				$legacy_orders,
				$partial_snapshots,
				$unresolvable_currency,
				$unknown_origin_diagnostics
			),
			$this->repository->load_count()
		);
	}
}
