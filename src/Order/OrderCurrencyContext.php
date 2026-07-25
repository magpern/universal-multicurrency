<?php
/**
 * Request-scoped order currency context stack.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Order;

/**
 * Manages a stack of order-scoped currency contexts.
 *
 * Allows code to temporarily override currency formatting for a single order
 * while rendering or processing it. The context is **stacked** to support
 * nested renders (e.g., admin list rendering multiple orders).
 *
 * The stack is request-scoped and must be manually entered/exited. Entry fires
 * an action; exit fires another. If using `run()`, a try/finally ensures
 * restoration even on error.
 *
 * The context resolves formatting (decimals, symbol, position) once on enter
 * and caches it for efficient repeated reads during a single render.
 */
final class OrderCurrencyContext {

	/**
	 * The reader for snapshot data.
	 *
	 * @var OrderSnapshotReader
	 */
	private OrderSnapshotReader $reader;

	/**
	 * The resolver for formatting fallback.
	 *
	 * @var HistoricalFormattingResolver
	 */
	private HistoricalFormattingResolver $resolver;

	/**
	 * Stack of resolved formatting (LIFO).
	 *
	 * @var array<int, ResolvedOrderCurrencyFormatting>
	 */
	private array $stack = array();

	/**
	 * Binds the context to its dependencies.
	 *
	 * @param OrderSnapshotReader           $reader   Snapshot reader.
	 * @param HistoricalFormattingResolver  $resolver Formatting resolver.
	 */
	public function __construct(
		OrderSnapshotReader $reader,
		HistoricalFormattingResolver $resolver
	) {
		$this->reader   = $reader;
		$this->resolver = $resolver;
	}

	/**
	 * Whether the stack is currently active (non-empty).
	 */
	public function is_active(): bool {
		return ! empty( $this->stack );
	}

	/**
	 * The current stack depth (0 = no context).
	 */
	public function depth(): int {
		return count( $this->stack );
	}

	/**
	 * The currently active order currency code, or null if no context.
	 */
	public function current_code(): ?string {
		if ( empty( $this->stack ) ) {
			return null;
		}

		return end( $this->stack )->code();
	}

	/**
	 * The currently resolved formatting, or null if no context.
	 */
	public function current_formatting(): ?ResolvedOrderCurrencyFormatting {
		if ( empty( $this->stack ) ) {
			return null;
		}

		return end( $this->stack );
	}

	/**
	 * Enters the context for an order (pushes onto the stack).
	 *
	 * Reads the order's snapshot, resolves its formatting, and caches it for
	 * the duration of the context. Fires `umc_order_currency_context_entered`.
	 *
	 * @param \WC_Order $order Order to enter context for.
	 */
	public function enter( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$snapshot   = $this->reader->read( $order );
		$code       = $order->get_currency();
		$formatting = $this->resolver->resolve( $snapshot, $code );

		$this->stack[] = $formatting;

		/**
		 * Fires when an order currency context is entered.
		 *
		 * @since 0.4.0
		 *
		 * @param \WC_Order $order Order being rendered in its own currency context.
		 */
		do_action( 'umc_order_currency_context_entered', $order );
	}

	/**
	 * Exits the current context (pops from the stack).
	 *
	 * A guard ensures this never underflows. Fires `umc_order_currency_context_exited`.
	 */
	public function exit(): void {
		if ( empty( $this->stack ) ) {
			return;
		}

		array_pop( $this->stack );

		/**
		 * Fires when an order currency context is exited.
		 *
		 * @since 0.4.0
		 */
		do_action( 'umc_order_currency_context_exited' );
	}

	/**
	 * Runs a callable within the order currency context.
	 *
	 * Enters the context, calls the function, and **guarantees** exit via
	 * try/finally (restoration even if the callable throws).
	 *
	 * @param \WC_Order $order    Order to enter context for.
	 * @param callable  $callback Function to run inside the context.
	 * @return mixed Return value of the callback.
	 */
	public function run( $order, callable $callback ) {
		$this->enter( $order );

		try {
			return $callback();
		} finally {
			$this->exit();
		}
	}
}
