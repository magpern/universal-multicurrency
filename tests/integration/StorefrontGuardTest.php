<?php
/**
 * Structural guards: Milestone 3 stays within scope and honours its seams.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Converter;
use UMC\Plugin;
use WP_UnitTestCase;

/**
 * Asserts the plugin registers no out-of-scope (fee/stock/refund/Blocks/M4)
 * callbacks, boots idempotently, and keeps its architectural boundaries: only
 * the conversion seam touches the Converter, order writes never use SQL, and no
 * broad exception is swallowed.
 */
final class StorefrontGuardTest extends WP_UnitTestCase {

	use \UMC\Tests\Support\SourceGuardTrait;

	/**
	 * Hooks that remain out of scope: fees are disabled and stock is never
	 * touched. woocommerce_create_refund is released to M4 (RefundSnapshot);
	 * the Store API checkout hooks are released to M5 (CheckoutSnapshotAdapter).
	 */
	private const FORBIDDEN_HOOKS = array(
		'woocommerce_cart_calculate_fees',
		'woocommerce_shipping_packages',
		'woocommerce_product_get_stock_quantity',
		'woocommerce_product_get_stock_status',
		'woocommerce_payment_complete_reduce_order_stock',
		'woocommerce_order_status_changed',
	);

	/**
	 * Source files allowed to reference the Converter directly: the converter
	 * itself and the single conversion seam.
	 */
	private const CONVERTER_SEAM_FILES = array(
		'Converter.php',
		'PriceConversionService.php',
	);

	/**
	 * The only source files permitted to stage order metadata.
	 */
	private const SNAPSHOT_WRITER_FILES = array(
		'OrderSnapshot.php',
		'RefundSnapshot.php',
	);

	public function test_no_out_of_scope_hooks_have_plugin_callbacks(): void {
		foreach ( self::FORBIDDEN_HOOKS as $hook ) {
			$this->assertSame(
				array(),
				$this->umc_callbacks_on( $hook ),
				"Milestone 3 must not hook '{$hook}'."
			);
		}
	}

	public function test_plugin_boot_is_idempotent(): void {
		$plugin = Plugin::instance();
		$plugin->init();
		$plugin->init();

		$this->assertSame( $plugin, Plugin::instance() );
	}

	public function test_converter_is_only_used_through_the_seam(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( in_array( basename( $file ), self::CONVERTER_SEAM_FILES, true ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			if ( 1 === preg_match( '/Converter::|new\s+Converter\b|use\s+UMC\\\\Converter\b/', $source ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Only PriceConversionService may use the Converter; all other code goes through the seam.'
		);
	}

	public function test_no_direct_sql_for_order_or_any_persistence(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			$source = (string) file_get_contents( $file );

			if ( 1 === preg_match( '/\$wpdb\b/', $source ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Persistence must go through WordPress/WooCommerce CRUD, never $wpdb / direct SQL.'
		);
	}

	public function test_no_broad_exception_is_swallowed(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( 'SettingsUpgrader.php' === basename( $file ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			if ( 1 === preg_match( '/catch\s*\(\s*\\\\?(Throwable|Exception)\b/', $source ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'No broad catch(\Throwable|\Exception): programming errors must surface.'
		);
	}

	public function test_uninstall_policy_invariants(): void {
		$src       = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$uninstall = dirname( $src ) . '/uninstall.php';

		$this->assertFileExists( $uninstall );

		$source = (string) file_get_contents( $uninstall );

		foreach (
			array(
				'delete_post_meta',
				'delete_metadata',
				'delete_user_meta',
				'$wpdb',
			) as $forbidden
		) {
			$this->assertStringNotContainsString(
				$forbidden,
				$source,
				'uninstall.php must not delete commerce or user metadata (ADR-0009).'
			);
		}

		$this->assertSame(
			\UMC\PersistedKeys::uninstall_deleted_option_keys(),
			$this->extract_delete_option_keys( $source ),
			'uninstall.php must delete exactly the contracted option keys.'
		);
	}

	/**
	 * @return list<string>
	 */
	private function extract_delete_option_keys( string $source ): array {
		if ( ! preg_match_all( "/delete_option\s*\(\s*['\"]([^'\"]+)['\"]/", $source, $matches ) ) {
			return array();
		}

		return array_values( $matches[1] );
	}

	/**
	 * The M4 historical-service source files (OrderSnapshot is M3, not M4).
	 *
	 * These are the services that must operate purely on the stored order
	 * currency: no conversion, no session, no live rate, no CurrencyContext.
	 */
	private const M4_HISTORICAL_BASENAMES = array(
		'OrderSnapshotReader.php',
		'OrderCurrencySnapshot.php',
		'HistoricalFormattingResolver.php',
		'ResolvedOrderCurrencyFormatting.php',
		'OrderCurrencyContext.php',
		'OrderCurrencyFormatting.php',
		'HistoricalOrderDisplay.php',
		'OrderPayCurrencyLock.php',
		'RefundSnapshot.php',
		'OrderCurrencyMetaBox.php',
	);

	public function test_m4_historical_services_no_conversion(): void {
		// M4 historical services must never reference the conversion seam.
		$this->assert_pattern_absent_from(
			$this->m4_historical_service_files(),
			'/Converter::|PriceConversionService\b|->convert/',
			'M4 historical services must not reference conversion: they format stored values only.'
		);
	}

	public function test_m4_historical_services_no_session_access(): void {
		// M4 historical services must not read session/active currency.
		$this->assert_pattern_absent_from(
			$this->m4_historical_service_files(),
			'/get_active_code|get_active_currency|->session|COOKIE_NAME/',
			'M4 historical services must not access session/active currency; order currency is explicit.'
		);
	}

	public function test_m4_historical_services_no_live_rate_or_currency_context(): void {
		// No live rate lookup and no CurrencyContext access: the order currency is
		// authoritative and explicit. Matches real usage (calls/instantiation/type
		// hints), not the word "RateProvider" appearing in prose docblocks.
		$this->assert_pattern_absent_from(
			$this->m4_historical_service_files(),
			'/->get_rate\(|->has_rate\(|new\s+(ManualRateProvider|CurrencyContext)\b|\bCurrencyContext\s+\$/',
			'M4 historical services must not look up live rates or read the CurrencyContext.'
		);
	}

	public function test_m4_historical_services_use_no_post_meta_api(): void {
		// Order data flows through WC_Order CRUD only, never the post-meta API.
		$this->assert_pattern_absent_from(
			$this->m4_historical_service_files(),
			'/\b(get|update|add|delete)_post_meta\s*\(/',
			'M4 historical services must use WC_Order CRUD, never the post-meta API.'
		);
	}

	public function test_gateway_compatibility_does_not_inspect_order_context(): void {
		// The generic gateway rule takes an explicit currency; it must never
		// inspect the order context (the dead class_exists() no-op is gone). This
		// keeps order-pay deterministic via explicit-currency filtering.
		$src  = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$file = $src . '/Integration/GatewayCompatibility.php';

		$this->assertFileExists( $file );
		$source = (string) file_get_contents( $file );

		$this->assertDoesNotMatchRegularExpression(
			'/OrderCurrencyContext|class_exists\(\s*[\'"]\\\\?UMC\\\\Order/',
			$source,
			'GatewayCompatibility must filter by explicit currency, never inspect the order context.'
		);
	}

	public function test_no_service_deletes_umc_metadata(): void {
		// The permanent _umc_* order/refund snapshot must never be deleted at runtime.
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/delete_(meta_data|post_meta|metadata)\s*\(\s*[^)]*_umc_/',
			'No service may delete permanent _umc_* metadata.'
		);
	}

	public function test_store_api_registration_stays_in_the_store_api_namespace(): void {
		// Store API integration is confined to src/StoreApi: every other service
		// stays transport-agnostic, so the domain layer cannot grow a dependency
		// on how a particular client happens to reach it.
		$outside_store_api = array_values(
			array_filter(
				$this->umc_source_files(),
				static function ( string $file ): bool {
					return false === strpos( $file, '/StoreApi/' );
				}
			)
		);

		$this->assertNotSame( array(), $outside_store_api, 'Expected source files outside the Store API namespace.' );

		$this->assert_pattern_absent_from(
			$outside_store_api,
			'/woocommerce_store_api_|woocommerce_blocks_|register_endpoint_data/',
			'Only src/StoreApi may register Store API or Blocks hooks.'
		);
	}

	public function test_only_the_snapshot_writers_stage_order_metadata(): void {
		// Both checkout flows must converge on one writer. An adapter that wrote
		// its own metadata would be the start of two snapshot formats.
		$this->assert_pattern_absent_from(
			$this->umc_source_files_except( self::SNAPSHOT_WRITER_FILES ),
			'/->update_meta_data\s*\(/',
			'Only the snapshot writers may stage order metadata; everything else delegates.'
		);
	}

	public function test_no_service_stamps_the_order_currency(): void {
		// WooCommerce sets the order currency, from a filtered value, in both
		// checkout flows. A plugin that also set it could disagree with itself.
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/->set_currency\s*\(/',
			'WooCommerce stamps the order currency; the plugin only records it.'
		);
	}

	public function test_store_api_code_raises_no_session_notices(): void {
		// Notices live in the session and are rendered by whichever page reads
		// them next, so one raised during an API request leaks onto an unrelated
		// page view. WooCommerce also turns session error notices into Store API
		// errors, which would block an otherwise valid checkout.
		$this->assert_pattern_absent_from(
			$this->store_api_source_files(),
			'/wc_add_notice\s*\(/',
			'Store API code must not raise session notices.'
		);
	}

	public function test_only_the_store_api_adapter_saves_an_order(): void {
		// Staging metadata and letting WooCommerce save is the rule. The single
		// exception is the cart re-sync hook, which fires after WooCommerce has
		// already saved, so a change made there would otherwise be lost.
		$this->assert_pattern_absent_from(
			$this->umc_source_files_except( array( 'CheckoutSnapshotAdapter.php' ) ),
			'/->save\s*\(\s*\)/',
			'Only CheckoutSnapshotAdapter may save an order; see its docblock for why.'
		);
	}

	public function test_no_frontend_assets_are_registered(): void {
		// This milestone is server-side only: currency switching reloads the page.
		// Shipping JavaScript would also mean shipping a second place where money
		// could be formatted or, worse, calculated.
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/wp_(register|enqueue)_script|IntegrationInterface|woocommerce_blocks_(cart|checkout|mini-cart)_block_registration/',
			'No frontend assets: the blocks are served entirely by server-side conversion.'
		);
	}

	public function test_historical_display_brackets_are_paired(): void {
		// Every enter (before_/resend) bracket has a matching exit (after_) bracket
		// so a render can never leave the order context on the stack.
		$src    = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$source = (string) file_get_contents( $src . '/Order/HistoricalOrderDisplay.php' );

		$enters = preg_match_all( '/add_action\(\s*\'woocommerce_(order_details_before_order_table|email_before_order_table|before_resend_order_emails)\'/', $source );
		$exits  = preg_match_all( '/add_action\(\s*\'woocommerce_(order_details_after_order_table|email_after_order_table|after_resend_order_email)\'/', $source );

		$this->assertSame(
			$enters,
			$exits,
			'Each historical-display enter bracket must have a matching exit bracket.'
		);
		$this->assertSame( 3, $enters, 'Expected three paired display brackets.' );
	}

	public function test_m4_no_order_total_setters(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			$source = (string) file_get_contents( $file );

			if ( preg_match(
				'/->set_(total|subtotal|discount_total|shipping_total|cart_tax|shipping_tax|total_tax|amount|fee_total)\s*\(/',
				$source
			) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'The plugin must never set order or refund totals; stored values are authoritative.'
		);
	}

	/**
	 * Absolute paths of the M4 historical-service source files.
	 *
	 * @return array<int, string>
	 */
	/**
	 * Every source file except the given basenames.
	 *
	 * @param array<int, string> $basenames Files to exclude.
	 * @return array<int, string>
	 */
	private function umc_source_files_except( array $basenames ): array {
		$files = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( ! in_array( basename( $file ), $basenames, true ) ) {
				$files[] = $file;
			}
		}

		return $files;
	}

	/**
	 * Source files inside the Store API namespace.
	 *
	 * @return array<int, string>
	 */
	private function store_api_source_files(): array {
		$files = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( false !== strpos( $file, '/StoreApi/' ) ) {
				$files[] = $file;
			}
		}

		$this->assertNotSame( array(), $files, 'Expected Store API source files.' );

		return $files;
	}

	private function m4_historical_service_files(): array {
		$files = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( in_array( basename( $file ), self::M4_HISTORICAL_BASENAMES, true ) ) {
				$files[] = $file;
			}
		}

		return $files;
	}
}
