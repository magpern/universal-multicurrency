<?php
/**
 * Checkout policy application phases.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Identifies whether checkout policy runs for presentation or settlement.
 */
final class CheckoutPolicyPhase {

	public const PRESENTATION = 'presentation';

	public const SETTLEMENT = 'settlement';
}
