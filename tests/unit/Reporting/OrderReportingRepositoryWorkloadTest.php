<?php
/**
 * Unit tests: bounded pagination and large-store workload for reporting.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Reporting;

use PHPUnit\Framework\TestCase;
use UMC\Order\OrderSnapshotReader;
use UMC\Reporting\LineItemProvenanceAggregator;
use UMC\Reporting\OrderReportingRepository;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingDateRange;
use UMC\Reporting\ReportingOriginClassifier;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingQueryTooLargeException;
use UMC\Reporting\RefundValueResolver;
use UMC\Reporting\TransactionCurrencyResolver;
use WC_Order;

/**
 * Proves repository pagination stays bounded for ~10k logical orders without
 * retaining WooCommerce order objects beyond one page at a time.
 */
final class OrderReportingRepositoryWorkloadTest extends TestCase {

	/**
	 * @var list<array<string, mixed>>
	 */
	private array $page_requests = array();

	/**
	 * @var list<int>
	 */
	private array $loaded_order_ids = array();

	/**
	 * Peak concurrent wc_get_order depth observed during a fetch cycle.
	 *
	 * @var int
	 */
	private int $peak_wc_get_order_depth = 0;

	/**
	 * Current wc_get_order nesting depth while instrumentation is active.
	 *
	 * @var int
	 */
	private int $wc_get_order_depth = 0;

	protected function setUp(): void {
		parent::setUp();

		$this->page_requests           = array();
		$this->loaded_order_ids        = array();
		$this->peak_wc_get_order_depth = 0;
		$this->wc_get_order_depth      = 0;

		$test = $this;

		$GLOBALS['umc_test_wc_get_orders_callback'] = static function ( array $args ) use ( $test ) {
			return $test->paginate_orders( $args );
		};

		$GLOBALS['umc_test_wc_get_order_callback'] = static function ( int $order_id ) use ( $test ): WC_Order {
			++$test->wc_get_order_depth;
			$test->peak_wc_get_order_depth = max( $test->peak_wc_get_order_depth, $test->wc_get_order_depth );

			$order = self::make_order_stub( $order_id );

			--$test->wc_get_order_depth;

			return $order;
		};
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['umc_test_wc_get_orders_callback'],
			$GLOBALS['umc_test_wc_get_order_callback'],
			$GLOBALS['umc_test_reporting_order_total']
		);

		parent::tearDown();
	}

	public function test_ten_thousand_logical_orders_use_fifty_bounded_batches(): void {
		$repository = $this->repository();
		$records    = $this->fetch_for_total( $repository, 10_000 );

		$this->assertCount( 10_000, $records );
		$this->assertSame( 10_000, $repository->load_count() );
		$this->assertCount( 50, $this->page_requests );

		foreach ( $this->page_requests as $index => $args ) {
			$this->assertSame( ReportingConstants::BATCH_SIZE, (int) $args['limit'], 'Page ' . ( $index + 1 ) );
			$this->assertSame( $index + 1, (int) $args['page'] );
		}

		$this->assertSame( range( 1, 10_000 ), $this->loaded_order_ids );
		$this->assertSame( 1, $this->peak_wc_get_order_depth );
	}

	/**
	 * @return array<string, array{0: int, 1: int, 2: int}>
	 */
	public function boundary_order_total_provider(): array {
		return array(
			'zero orders'     => array( 0, 1, 0 ),
			'single order'    => array( 1, 1, 1 ),
			'one below batch' => array( 199, 1, 199 ),
			'exact batch'     => array( 200, 1, 200 ),
			'one over batch'  => array( 201, 2, 201 ),
			'one below ten k' => array( 9_999, 50, 9_999 ),
			'exact ten k'     => array( 10_000, 50, 10_000 ),
			'one over ten k'  => array( 10_001, 51, 10_001 ),
		);
	}

	/**
	 * @param int $total          Total logical orders.
	 * @param int $expected_pages Expected wc_get_orders page count.
	 * @param int $expected_loads Expected loaded order count.
	 *
	 * @dataProvider boundary_order_total_provider
	 */
	public function test_pagination_boundaries_process_each_order_once(
		int $total,
		int $expected_pages,
		int $expected_loads
	): void {
		$repository = $this->repository();
		$records    = $this->fetch_for_total( $repository, $total );

		$this->assertCount( $expected_loads, $records );
		$this->assertSame( $expected_loads, $repository->load_count() );
		$this->assertCount( $expected_pages, $this->page_requests );
		$this->assertSame( $expected_loads, count( array_unique( $this->loaded_order_ids ) ) );

		if ( $expected_loads > 0 ) {
			$this->assertSame( range( 1, $expected_loads ), $this->loaded_order_ids );
		}
	}

	public function test_fifty_thousand_orders_pass_the_safety_cap(): void {
		$this->page_requests = array();

		$GLOBALS['umc_test_wc_get_orders_callback'] = function ( array $args ): object {
			$this->page_requests[] = $args;

			return (object) array(
				'orders'        => array( 1 ),
				'max_num_pages' => 1,
				'total'         => ReportingConstants::MAX_UNBOUNDED_ORDERS,
			);
		};

		$repository = $this->repository();
		$records    = $repository->fetch_records( $this->query() );

		$this->assertCount( 1, $records );
		$this->assertSame( 1, $repository->load_count() );
	}

	public function test_fifty_thousand_one_orders_are_rejected_honestly(): void {
		$this->expectException( ReportingQueryTooLargeException::class );
		$this->expectExceptionMessage( '50001 orders' );

		$GLOBALS['umc_test_wc_get_orders_callback'] = function ( array $args ): object {
			$this->page_requests[] = $args;

			return (object) array(
				'orders'        => array(),
				'max_num_pages' => 0,
				'total'         => ReportingConstants::MAX_UNBOUNDED_ORDERS + 1,
			);
		};

		$this->repository()->fetch_records( $this->query() );
	}

	public function test_forty_nine_thousand_nine_hundred_ninety_nine_orders_pass_cap(): void {
		$GLOBALS['umc_test_wc_get_orders_callback'] = function ( array $args ): object {
			$this->page_requests[] = $args;

			return (object) array(
				'orders'        => array( 1 ),
				'max_num_pages' => 1,
				'total'         => ReportingConstants::MAX_UNBOUNDED_ORDERS - 1,
			);
		};

		$records = $this->repository()->fetch_records( $this->query() );

		$this->assertCount( 1, $records );
	}

	/**
	 * @param array<string, mixed> $args Query args.
	 * @return object
	 */
	public function paginate_orders( array $args ): object {
		$this->page_requests[] = $args;

		$total = (int) ( $GLOBALS['umc_test_reporting_order_total'] ?? 0 );
		$limit = (int) ( $args['limit'] ?? ReportingConstants::BATCH_SIZE );
		$page  = (int) ( $args['page'] ?? 1 );

		$max_pages = $total > 0 ? (int) ceil( $total / $limit ) : 1;
		$offset    = ( $page - 1 ) * $limit;
		$remaining = max( 0, $total - $offset );
		$count     = min( $limit, $remaining );
		$ids       = $count > 0 ? range( $offset + 1, $offset + $count ) : array();

		return (object) array(
			'orders'        => $ids,
			'max_num_pages' => $max_pages,
			'total'         => $total,
		);
	}

	/**
	 * @param OrderReportingRepository $repository Repository under test.
	 * @param int                      $total      Logical order total for the fake store.
	 * @return list<\UMC\Reporting\OrderReportRecord>
	 */
	private function fetch_for_total( OrderReportingRepository $repository, int $total ): array {
		$GLOBALS['umc_test_reporting_order_total'] = $total;
		$this->page_requests                       = array();
		$this->loaded_order_ids                    = array();
		$this->peak_wc_get_order_depth             = 0;
		$this->wc_get_order_depth                  = 0;

		$records = $repository->fetch_records( $this->query() );

		foreach ( $records as $record ) {
			$this->loaded_order_ids[] = $record->order_id();
		}

		return $records;
	}

	private function query(): ReportingQuery {
		return new ReportingQuery(
			new ReportingDateRange(
				new \DateTimeImmutable( '2026-01-01 00:00:00' ),
				new \DateTimeImmutable( '2026-01-31 23:59:59' ),
				ReportingDateRange::PRESET_30_DAYS
			),
			ReportingConstants::default_statuses(),
			'',
			'',
			'',
			''
		);
	}

	private function repository(): OrderReportingRepository {
		return new OrderReportingRepository(
			new OrderSnapshotReader(),
			new TransactionCurrencyResolver(),
			new RefundValueResolver(),
			new LineItemProvenanceAggregator(),
			new ReportingOriginClassifier()
		);
	}

	private static function make_order_stub( int $order_id ): WC_Order {
		return new class( $order_id ) extends WC_Order {
			/**
			 * @var int
			 */
			private int $order_id;

			/**
			 * @param int $order_id Order ID.
			 */
			public function __construct( int $order_id ) {
				$this->order_id = $order_id;
			}

			public function get_id() {
				return $this->order_id;
			}

			public function get_total() {
				return 100.0;
			}

			public function get_currency() {
				return 'EUR';
			}

			public function get_meta( $key = '' ) {
				unset( $key );
				return '';
			}

			public function get_items( $type = 'line_item' ) {
				unset( $type );
				return array();
			}
		};
	}
}
