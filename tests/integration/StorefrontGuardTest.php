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

	/**
	 * Hooks that remain out of scope: fees are disabled, stock is never touched,
	 * and Blocks (Store API) belong to a later Blocks milestone.
	 * woocommerce_create_refund is released to M4 (RefundSnapshot).
	 */
	private const FORBIDDEN_HOOKS = array(
		'woocommerce_cart_calculate_fees',
		'woocommerce_shipping_packages',
		'woocommerce_product_get_stock_quantity',
		'woocommerce_product_get_stock_status',
		'woocommerce_payment_complete_reduce_order_stock',
		'woocommerce_order_status_changed',
		'woocommerce_store_api_checkout_update_order_meta',
	);

	/**
	 * Source files allowed to reference the Converter directly: the converter
	 * itself and the single conversion seam.
	 */
	private const CONVERTER_SEAM_FILES = array(
		'Converter.php',
		'PriceConversionService.php',
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

	public function test_uninstall_never_deletes_order_snapshot_meta(): void {
		$src       = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$uninstall = dirname( $src ) . '/uninstall.php';

		$this->assertFileExists( $uninstall );
		$source = (string) file_get_contents( $uninstall );

		// The permanent _umc_* order snapshot must never be deleted on uninstall:
		// no meta-deletion calls and no direct SQL — only the settings option goes.
		$this->assertStringNotContainsString( 'delete_post_meta', $source );
		$this->assertStringNotContainsString( 'delete_metadata', $source );
		$this->assertStringNotContainsString( '$wpdb', $source );
		$this->assertStringContainsString( "delete_option( 'umc_settings' )", $source );
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

	public function test_no_store_api_or_blocks_registration_in_src(): void {
		// Store API / Blocks order rendering remain deferred: src registers no
		// Store-API or Blocks hooks (the cart_checkout_blocks compat declaration
		// lives in the bootstrap file, not in src).
		$this->assert_pattern_absent_from(
			$this->umc_source_files(),
			'/woocommerce_store_api_|woocommerce_blocks_|StoreApi\\\\|register_endpoint_data/',
			'src must register no Store API / Blocks hooks (deferred milestone).'
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
	private function m4_historical_service_files(): array {
		$files = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( in_array( basename( $file ), self::M4_HISTORICAL_BASENAMES, true ) ) {
				$files[] = $file;
			}
		}

		return $files;
	}

	/**
	 * Asserts that a regex matches none of the given source files.
	 *
	 * @param array<int, string> $files   Absolute file paths.
	 * @param string             $pattern PCRE pattern.
	 * @param string             $message Assertion message.
	 */
	private function assert_pattern_absent_from( array $files, string $pattern, string $message ): void {
		$offenders = array();

		foreach ( $files as $file ) {
			if ( 1 === preg_match( $pattern, (string) file_get_contents( $file ) ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame( array(), $offenders, $message );
	}

	/**
	 * Absolute paths of every PHP file under the plugin's src/ directory.
	 *
	 * @return array<int, string>
	 */
	private function umc_source_files(): array {
		$src = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS )
		);

		$files = array();

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Descriptions of callbacks on a hook that originate from this plugin.
	 *
	 * @param string $hook Hook name.
	 * @return array<int, string>
	 */
	private function umc_callbacks_on( string $hook ): array {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return array();
		}

		$found = array();

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) ) {
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( 0 === strpos( $class, 'UMC\\' ) ) {
						$found[] = "{$class}::{$function[1]}";
					}
				} elseif ( $function instanceof \Closure ) {
					$file = ( new \ReflectionFunction( $function ) )->getFileName();
					if ( is_string( $file ) && false !== strpos( $file, '/universal-multicurrency/src/' ) ) {
						$found[] = "closure:{$file}";
					}
				}
			}
		}

		return $found;
	}
}
