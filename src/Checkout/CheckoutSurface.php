<?php
/**
 * Checkout policy surface identifiers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Identifies the checkout surface invoking checkout policy.
 */
final class CheckoutSurface {

	public const CLASSIC_CHECKOUT = 'classic_checkout';

	public const STORE_API_CHECKOUT = 'store_api_checkout';
}
