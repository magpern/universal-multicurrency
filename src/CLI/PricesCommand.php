<?php
/**
 * WP-CLI commands for fixed-price catalog operations.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CLI;

use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceOperationResult;

/**
 * Thin CLI wrapper over the shared fixed-price catalog services (ADR-0029).
 *
 * Registered via {@see \WP_CLI::add_command()} when WP-CLI is present. Does
 * not extend {@see \WP_CLI_Command} so the class remains autoloadable in
 * unit tests, matching {@see RatesCommand}.
 *
 * Performs **no** per-user capability authorization check anywhere in this
 * class — WP-CLI execution is trusted administrative/system access,
 * identical to the established `wp umc rates` precedent
 * ({@see \UMC\Tests\Unit\CLI\CliAuthorizationPrecedentTest}).
 */
final class PricesCommand {

	/**
	 * Batch size for catalog iteration (within the 100-250 range used
	 * elsewhere in the plugin).
	 */
	private const BATCH_SIZE = 200;

	/**
	 * Valid `--status` values for `list`, matching
	 * {@see FixedPriceCoverageReport}'s STATUS_* constants.
	 *
	 * @var array<int, string>
	 */
	private const VALID_STATUSES = array(
		FixedPriceCoverageReport::STATUS_FIXED,
		FixedPriceCoverageReport::STATUS_PARTIAL,
		FixedPriceCoverageReport::STATUS_FX_FALLBACK,
		FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS,
	);

	/**
	 * Binds the CLI wrapper to the shared catalog-operations services.
	 *
	 * @param FixedPriceCatalogOperationsService $service  Shared seed/clear orchestration.
	 * @param FixedPriceCatalogQuery             $query    Shared classified catalog listing.
	 * @param CurrencyRegistry                   $registry Currency configuration.
	 */
	public function __construct(
		private FixedPriceCatalogOperationsService $service,
		private FixedPriceCatalogQuery $query,
		private CurrencyRegistry $registry
	) {
	}

	/**
	 * Lists fixed-price coverage across the catalog.
	 *
	 * ## OPTIONS
	 *
	 * [--currency=<code>]
	 * : Limit the listing to one non-base currency code. Defaults to every
	 * configured non-base currency.
	 *
	 * [--status=<status>]
	 * : Limit the listing to one coverage status: fixed, partial, fx, or
	 * no_priceable_variations.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc prices list
	 *     wp umc prices list --currency=SEK --status=fx
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function list( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		$status = isset( $assoc_args['status'] ) ? (string) $assoc_args['status'] : '';

		if ( '' !== $status && ! in_array( $status, self::VALID_STATUSES, true ) ) {
			\WP_CLI::error( 'Invalid --status. Use one of: ' . implode( ', ', self::VALID_STATUSES ) . '.' );
			return;
		}

		$currencies = $this->resolve_listing_currencies( $assoc_args );

		if ( null === $currencies ) {
			return;
		}

		$rows = array();

		foreach ( $currencies as $code ) {
			foreach ( $this->query->each_classified( $code, $status, self::BATCH_SIZE ) as $row ) {
				$rows[] = array(
					'currency'   => $code,
					'product_id' => (string) $row['product']->get_id(),
					'name'       => $row['product']->get_name(),
					'status'     => $row['status'],
				);
			}
		}

		\WP_CLI\Utils::format_items( 'table', $rows, array( 'currency', 'product_id', 'name', 'status' ) );
	}

	/**
	 * Seeds fixed prices from the current FX conversion of each product's or
	 * variation's own authored native price. One rate is resolved and used
	 * for the entire invocation, including every batch of `--all`.
	 *
	 * ## OPTIONS
	 *
	 * --currency=<code>
	 * : Target non-base currency code.
	 *
	 * [--product=<id>]
	 * : Seed one product/variable-product ID. Mutually exclusive with --all.
	 *
	 * [--all]
	 * : Seed every product in the catalog. Mutually exclusive with --product.
	 *
	 * [--dry-run]
	 * : Compute and report the result without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc prices seed --currency=SEK --all
	 *     wp umc prices seed --currency=SEK --product=123 --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function seed( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		$this->run_operation( 'seed', $assoc_args );
	}

	/**
	 * Clears the fixed price for one currency across the requested scope.
	 * Other currencies and other products are untouched.
	 *
	 * ## OPTIONS
	 *
	 * --currency=<code>
	 * : Target non-base currency code.
	 *
	 * [--product=<id>]
	 * : Clear one product/variable-product ID. Mutually exclusive with --all.
	 *
	 * [--all]
	 * : Clear every product in the catalog. Mutually exclusive with --product.
	 *
	 * [--dry-run]
	 * : Compute and report the result without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp umc prices clear --currency=SEK --all
	 *     wp umc prices clear --currency=SEK --product=123
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Associative args.
	 */
	public function clear( array $args = array(), array $assoc_args = array() ): void {
		unset( $args );

		$this->run_operation( 'clear', $assoc_args );
	}

	/**
	 * Shared seed/clear argument parsing, scope resolution, and reporting.
	 *
	 * @param string                $action     'seed' or 'clear'.
	 * @param array<string, string> $assoc_args Associative args.
	 */
	private function run_operation( string $action, array $assoc_args ): void {
		$currency = isset( $assoc_args['currency'] ) ? strtoupper( (string) $assoc_args['currency'] ) : '';

		if ( '' === $currency ) {
			\WP_CLI::error( '--currency is required.' );
			return;
		}

		$has_product = isset( $assoc_args['product'] );
		$has_all     = isset( $assoc_args['all'] );

		if ( $has_product === $has_all ) {
			\WP_CLI::error( 'Specify exactly one of --product=<id> or --all.' );
			return;
		}

		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $has_product ) {
			$product_id = absint( $assoc_args['product'] );
			$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

			if ( ! $product instanceof \WC_Product ) {
				\WP_CLI::error( 'Unknown --product ID.' );
				return;
			}

			$products = array( $product );
		} else {
			$products = $this->query->each_product( self::BATCH_SIZE );
		}

		$result = 'seed' === $action
			? $this->service->seed( $products, $currency, ! $dry_run )
			: $this->service->clear( $products, $currency, ! $dry_run );

		if ( $result->is_aborted() ) {
			\WP_CLI::error( $this->message_for_abort( $result->abort_reason() ) );
			return;
		}

		$this->report( $action, $result, $dry_run );
	}

	/**
	 * Prints the completion summary and sets the process exit code.
	 *
	 * @param string                    $action  'seed' or 'clear'.
	 * @param FixedPriceOperationResult $result  Completed operation outcome.
	 * @param bool                      $dry_run Whether this was a dry run.
	 */
	private function report( string $action, FixedPriceOperationResult $result, bool $dry_run ): void {
		$prefix    = $dry_run ? '[dry-run] ' : '';
		$succeeded = count( $result->succeeded() );
		$skipped   = count( $result->skipped() );

		if ( 'seed' === $action ) {
			\WP_CLI::success(
				sprintf(
					'%sSeeded %d product(s)/variation(s) at rate %s (%d skipped).',
					$prefix,
					$succeeded,
					(string) $result->rate_used(),
					$skipped
				)
			);
			return;
		}

		\WP_CLI::success(
			sprintf( '%sCleared %d product(s)/variation(s) (%d skipped).', $prefix, $succeeded, $skipped )
		);
	}

	/**
	 * Human-readable message for an aborted operation.
	 *
	 * @param string|null $reason One of {@see FixedPriceOperationResult}'s ABORT_* constants.
	 */
	private function message_for_abort( ?string $reason ): string {
		return match ( $reason ) {
			FixedPriceOperationResult::ABORT_BASE_CURRENCY    => 'The base currency cannot have fixed prices.',
			FixedPriceOperationResult::ABORT_UNKNOWN_CURRENCY => 'That currency is not configured.',
			FixedPriceOperationResult::ABORT_NO_RATE          => 'No exchange rate is available for that currency.',
			default                                             => 'The fixed pricing operation could not be completed.',
		};
	}

	/**
	 * Resolves the currency codes `list` should classify, validating an
	 * explicit `--currency` against the registry.
	 *
	 * @param array<string, string> $assoc_args Associative args.
	 * @return array<int, string>|null Currency codes, or null when invalid (error already reported).
	 */
	private function resolve_listing_currencies( array $assoc_args ): ?array {
		if ( ! isset( $assoc_args['currency'] ) ) {
			return array_values(
				array_map(
					static fn( $currency ) => $currency->code(),
					array_filter(
						$this->registry->get_currencies(),
						fn( $currency ): bool => ! $this->registry->is_base( $currency->code() )
					)
				)
			);
		}

		$code = strtoupper( (string) $assoc_args['currency'] );

		if ( $this->registry->is_base( $code ) || null === $this->registry->get_currency( $code ) ) {
			\WP_CLI::error( 'Unknown or base --currency.' );
			return null;
		}

		return array( $code );
	}
}
