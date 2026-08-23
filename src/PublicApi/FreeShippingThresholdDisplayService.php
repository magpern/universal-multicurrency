<?php
/**
 * Public presentation of a resolved free-shipping threshold.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\PublicApi;

use UMC\CurrencyContext;
use UMC\Integration\FreeShippingThreshold;
use UMC\Integration\FreeShippingThresholdResolver;

/**
 * Turns a shared resolved threshold into the stable public three-key array.
 *
 * Owns display-only policy (convertible request, Option A over-precision,
 * missing-rate fail-closed). Formatting uses `wc_price()` so UMC/WC filters
 * apply. No monetary arithmetic lives here.
 */
final class FreeShippingThresholdDisplayService {

	/**
	 * Shared threshold resolver (same instance as checkout eligibility).
	 *
	 * @var FreeShippingThresholdResolver
	 */
	private FreeShippingThresholdResolver $resolver;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the shared resolver and currency context.
	 *
	 * @param FreeShippingThresholdResolver $resolver Shared threshold resolver.
	 * @param CurrencyContext               $context  Request-scoped currency facade.
	 */
	public function __construct( FreeShippingThresholdResolver $resolver, CurrencyContext $context ) {
		$this->resolver = $resolver;
		$this->context  = $context;
	}

	/**
	 * Resolves and formats a base-authored free-shipping threshold for display.
	 *
	 * @param string $base_threshold Base-currency decimal string.
	 * @return array{formatted_html: string, amount: string, currency_code: string}|null
	 */
	public function get_display( string $base_threshold ): ?array {
		if ( ! $this->context->is_convertible_request() ) {
			return null;
		}

		if ( $this->resolver->exceeds_base_precision( $base_threshold ) ) {
			return null;
		}

		if ( ! $this->context->is_base_active() && ! $this->context->has_active_exchange_rate() ) {
			return null;
		}

		$threshold = $this->resolver->resolve( $base_threshold );

		if ( null === $threshold ) {
			return null;
		}

		return $this->present( $threshold );
	}

	/**
	 * Formats an already-resolved threshold.
	 *
	 * @param FreeShippingThreshold $threshold Shared resolver result.
	 * @return array{formatted_html: string, amount: string, currency_code: string}
	 */
	public function present( FreeShippingThreshold $threshold ): array {
		$amount = $threshold->amount();
		$code   = $threshold->currency_code();

		return array(
			'formatted_html' => wc_price(
				$amount,
				array(
					'currency' => $code,
				)
			),
			'amount'         => $amount,
			'currency_code'  => $code,
		);
	}
}
