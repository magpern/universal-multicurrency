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

	public function test_m4_historical_services_no_conversion(): void {
		$offenders = array();
		$src       = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$order_dir = $src . '/Order';

		if ( is_dir( $order_dir ) ) {
			foreach ( glob( "$order_dir/*.php" ) as $file ) {
				$source = (string) file_get_contents( $file );

				// Historical services must never reference the conversion seam.
				if ( preg_match( '/Converter::|PriceConversionService\b|->convert/', $source ) ) {
					$offenders[] = basename( $file );
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'M4 historical services (Order/*) must not reference conversion: they format stored values only.'
		);
	}

	public function test_m4_historical_services_no_session_access(): void {
		$offenders = array();
		$src       = dirname( ( new \ReflectionClass( Converter::class ) )->getFileName() );
		$order_dir = $src . '/Order';

		if ( is_dir( $order_dir ) ) {
			foreach ( glob( "$order_dir/*.php" ) as $file ) {
				$source = (string) file_get_contents( $file );

				// Historical services must not read session/active currency.
				if ( preg_match( '/get_active_code|get_active_currency|->session|COOKIE_NAME/', $source ) ) {
					$offenders[] = basename( $file );
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'M4 historical services must not access session/active currency; order currency is explicit.'
		);
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
