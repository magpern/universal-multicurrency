<?php
/**
 * HPOS-safe batched order loader for reporting.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\Order\OrderSnapshotReader;
use WC_Order;

/**
 * HPOS-safe batched order loader for reporting.
 */
final class OrderReportingRepository {

	/**
	 * Number of orders loaded in the current fetch cycle.
	 *
	 * @var int
	 */
	private int $load_count = 0;

	/**
	 * Binds the repository to reporting collaborators.
	 *
	 * @param OrderSnapshotReader          $snapshot_reader   Snapshot reader.
	 * @param TransactionCurrencyResolver  $currency_resolver Transaction currency resolver.
	 * @param RefundValueResolver          $refund_resolver   Refund value resolver.
	 * @param LineItemProvenanceAggregator $line_aggregator   Line-item provenance aggregator.
	 * @param ReportingOriginClassifier    $origin_classifier Origin classifier.
	 */
	public function __construct(
		private OrderSnapshotReader $snapshot_reader,
		private TransactionCurrencyResolver $currency_resolver,
		private RefundValueResolver $refund_resolver,
		private LineItemProvenanceAggregator $line_aggregator,
		private ReportingOriginClassifier $origin_classifier
	) {
	}

	/**
	 * Number of orders loaded in the current fetch cycle.
	 */
	public function load_count(): int {
		return $this->load_count;
	}

	/**
	 * Resets the load counter before a new fetch cycle.
	 */
	public function reset_load_count(): void {
		$this->load_count = 0;
	}

	/**
	 * Loads and filters order report records for a query.
	 *
	 * @param ReportingQuery $query Reporting query.
	 * @return list<OrderReportRecord>
	 * @throws ReportingQueryTooLargeException When the query exceeds safe bounds.
	 */
	public function fetch_records( ReportingQuery $query ): array {
		$this->reset_load_count();

		$args = array(
			'status'       => $query->statuses(),
			'limit'        => ReportingConstants::BATCH_SIZE,
			'paginate'     => true,
			'return'       => 'ids',
			'orderby'      => 'date',
			'order'        => 'ASC',
			'date_created' => $query->range()->wc_date_query(),
		);

		$page    = 1;
		$records = array();
		$total   = 0;

		do {
			$args['page'] = $page;
			$result       = wc_get_orders( $args );

			if ( ! is_object( $result ) || ! isset( $result->orders, $result->max_num_pages, $result->total ) ) {
				break;
			}

			if ( 1 === $page ) {
				$total = (int) $result->total;
				if ( $total > ReportingConstants::MAX_UNBOUNDED_ORDERS ) {
					// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message built from integer order count.
					throw new ReportingQueryTooLargeException(
						sprintf(
							'Reporting query matches %d orders; narrow the date range.',
							$total
						)
					);
					// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
			}

			foreach ( $result->orders as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof WC_Order ) {
					continue;
				}

				++$this->load_count;
				$record = $this->build_record( $order );
				if ( ! $this->matches_query( $record, $query ) ) {
					continue;
				}

				$records[] = $record;
			}

			++$page;
		} while ( $page <= (int) $result->max_num_pages );

		return $records;
	}

	/**
	 * Builds one report record from a WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function build_record( WC_Order $order ): OrderReportRecord {
		$snapshot = $this->snapshot_reader->read( $order );
		$resolved = $this->currency_resolver->resolve( $order, $snapshot );

		return new OrderReportRecord(
			$order->get_id(),
			$snapshot,
			$resolved['currency'],
			$resolved['unresolvable'],
			(float) $order->get_total(),
			$this->refund_resolver->refunded_value( $order ),
			$this->origin_classifier->classify( $snapshot ),
			$snapshot->fallback_occurred(),
			$snapshot->shopper_currency(),
			$snapshot->checkout_mode(),
			$this->line_aggregator->product_line_sources( $order )
		);
	}

	/**
	 * Applies post-load query filters to one record.
	 *
	 * @param OrderReportRecord $record Loaded order record.
	 * @param ReportingQuery    $query  Reporting query.
	 */
	private function matches_query( OrderReportRecord $record, ReportingQuery $query ): bool {
		$currency = $query->transaction_currency();
		if ( '' !== $currency ) {
			if ( $record->unresolvable_currency() || $currency !== $record->transaction_currency() ) {
				return false;
			}
		}

		$origin = $query->origin();
		if ( '' !== $origin && $origin !== $record->reporting_origin() ) {
			return false;
		}

		$fallback = $query->fallback();
		if ( '' !== $fallback ) {
			$occurred = $record->fallback_occurred();
			if ( null === $occurred ) {
				return false;
			}
			$expected = 'yes' === $fallback;
			if ( $occurred !== $expected ) {
				return false;
			}
		}

		return true;
	}
}
