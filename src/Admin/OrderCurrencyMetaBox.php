<?php
/**
 * Read-only order currency audit meta box.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Order\HistoricalFormattingResolver;
use UMC\Order\OrderCurrencySnapshot;
use UMC\Order\OrderSnapshotReader;

/**
 * Adds a read-only meta box to the WooCommerce order admin screen (HPOS + legacy).
 *
 * Displays the order's snapshot metadata (base currency, transaction currency,
 * exchange rate, rate timestamp, rate source, plugin version, rate identity) and
 * resolved formatting (decimals, symbol, position). For legacy orders without a
 * snapshot, shows a clear indicator.
 *
 * The box is non-editable and fires no save handlers. All text is properly escaped.
 */
final class OrderCurrencyMetaBox {

	/**
	 * Snapshot reader.
	 *
	 * @var OrderSnapshotReader
	 */
	private OrderSnapshotReader $reader;

	/**
	 * Formatting resolver.
	 *
	 * @var HistoricalFormattingResolver
	 */
	private HistoricalFormattingResolver $resolver;

	/**
	 * Binds the meta box to its dependencies.
	 *
	 * @param OrderSnapshotReader          $reader    Snapshot reader.
	 * @param HistoricalFormattingResolver $resolver  Formatting resolver.
	 */
	public function __construct(
		OrderSnapshotReader $reader,
		HistoricalFormattingResolver $resolver
	) {
		$this->reader   = $reader;
		$this->resolver = $resolver;
	}

	/**
	 * Registers the meta box.
	 */
	public function register(): void {
		// Only register in admin context.
		if ( ! is_admin() ) {
			return;
		}

		// Register for HPOS screen.
		$hpos_screen = \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_screen();
		add_meta_box(
			'umc_order_currency',
			esc_html__( 'Currency & Exchange Rate', 'universal-multicurrency' ),
			array( $this, 'render' ),
			$hpos_screen,
			'normal',
			'default'
		);

		// Also register for legacy post-type (backwards compatibility).
		add_meta_box(
			'umc_order_currency',
			esc_html__( 'Currency & Exchange Rate', 'universal-multicurrency' ),
			array( $this, 'render' ),
			'shop_order',
			'normal',
			'default'
		);
	}

	/**
	 * Renders the meta box content.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post object (legacy) or order object (HPOS).
	 */
	public function render( $post_or_order ): void {
		// Resolve the order.
		$order = null;
		if ( $post_or_order instanceof \WC_Order ) {
			$order = $post_or_order;
		} elseif ( $post_or_order instanceof \WP_Post ) {
			$order = wc_get_order( $post_or_order->ID );
		}

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Read the snapshot.
		$snapshot = $this->reader->read( $order );

		// Build the view model.
		$view = $this->view_model( $snapshot, $order );

		// Render.
		$this->render_view( $view );
	}

	/**
	 * Builds the view model from a snapshot.
	 *
	 * Pure and WordPress-free for unit testability.
	 *
	 * @param OrderCurrencySnapshot $snapshot Order snapshot.
	 * @param \WC_Order             $order    Order object.
	 * @return array<string, mixed>
	 */
	public function view_model(
		OrderCurrencySnapshot $snapshot,
		\WC_Order $order
	): array {
		$view = array(
			'is_legacy'            => $snapshot->is_legacy(),
			'schema_version'       => $snapshot->schema_version(),
			'base_currency'        => $snapshot->base_currency(),
			'transaction_currency' => $snapshot->transaction_currency() ?? $order->get_currency(),
			'exchange_rate'        => $snapshot->exchange_rate(),
			'rate_timestamp'       => $snapshot->rate_timestamp(),
			'rate_source'          => $snapshot->rate_source(),
			'plugin_version'       => $snapshot->plugin_version(),
			'rate_identity'        => $snapshot->rate_identity(),
		);

		// Resolve formatting for display purposes.
		if ( ! $snapshot->is_legacy() ) {
			$formatting                = $this->resolver->resolve(
				$snapshot,
				$view['transaction_currency']
			);
			$view['resolved_decimals'] = $formatting->decimals();
			$view['resolved_symbol']   = $formatting->symbol();
			$view['resolved_position'] = $formatting->position();
		}

		/**
		 * Filters the order currency audit view model.
		 *
		 * @since 0.4.0
		 *
		 * @param array<string, mixed>      $view     View model.
		 * @param OrderCurrencySnapshot     $snapshot Snapshot.
		 * @param \WC_Order                 $order    Order.
		 */
		return (array) apply_filters(
			'umc_order_audit_view_model',
			$view,
			$snapshot,
			$order
		);
	}

	/**
	 * Renders the view.
	 *
	 * @param array<string, mixed> $view View model.
	 */
	private function render_view( array $view ): void {
		?>
		<div class="umc-order-currency-audit">
			<?php if ( $view['is_legacy'] ) : ?>
				<p>
					<em><?php esc_html_e( 'This order was created before the Universal Multicurrency plugin was installed. No snapshot metadata is available.', 'universal-multicurrency' ); ?></em>
				</p>
			<?php else : ?>
				<table class="widefat">
					<tbody>
						<?php if ( isset( $view['schema_version'] ) ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Snapshot Version', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['schema_version'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $view['base_currency'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Base Currency', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['base_currency'] ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><strong><?php esc_html_e( 'Order Currency', 'universal-multicurrency' ); ?></strong></td>
							<td><?php echo esc_html( $view['transaction_currency'] ); ?></td>
						</tr>
						<?php if ( $view['exchange_rate'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Exchange Rate', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['exchange_rate'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $view['rate_timestamp'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Rate Set At', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $view['rate_timestamp'] ) ); ?> UTC</td>
							</tr>
						<?php endif; ?>
						<?php if ( $view['rate_source'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Rate Source', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['rate_source'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( isset( $view['resolved_decimals'] ) ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Decimals', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( (string) $view['resolved_decimals'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( isset( $view['resolved_symbol'] ) && $view['resolved_symbol'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Symbol', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['resolved_symbol'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $view['plugin_version'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Plugin Version', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['plugin_version'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $view['rate_identity'] ) : ?>
							<tr>
								<td><strong><?php esc_html_e( 'Rate Identity', 'universal-multicurrency' ); ?></strong></td>
								<td><?php echo esc_html( $view['rate_identity'] ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
