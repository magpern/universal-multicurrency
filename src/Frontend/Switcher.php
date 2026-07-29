<?php
/**
 * Legacy currency switcher shim.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Frontend;

/**
 * Deprecated compatibility class retained for backward references.
 *
 * Shortcode registration moved to {@see \UMC\Display\SwitcherShortcode}.
 */
final class Switcher {

	/**
	 * Deprecated: shortcode registration is handled by Display services.
	 */
	public function register(): void {
		// Intentionally empty. See SwitcherShortcode.
	}
}
