<?php
/**
 * Invalidates reporting cache on order/refund lifecycle events.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Invalidates reporting cache on order lifecycle events.
 */
final class ReportingCacheInvalidator {

	/**
	 * Binds the invalidator to the reporting cache.
	 *
	 * @param ReportingCache $cache Reporting cache.
	 */
	public function __construct(
		private ReportingCache $cache
	) {
	}

	/**
	 * Registers WooCommerce hooks that invalidate reporting cache.
	 */
	public function register(): void {
		add_action( 'woocommerce_new_order', array( $this, 'invalidate' ), 10, 0 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'invalidate' ), 10, 0 );
		add_action( 'woocommerce_create_refund', array( $this, 'invalidate' ), 10, 0 );
		add_action( 'woocommerce_delete_refund', array( $this, 'invalidate' ), 10, 0 );
		add_action( 'woocommerce_payment_complete', array( $this, 'invalidate' ), 10, 0 );
	}

	/**
	 * Bumps the reporting cache generation.
	 */
	public function invalidate(): void {
		$this->cache->invalidate();
	}
}
