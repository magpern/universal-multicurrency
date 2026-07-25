<?php
/**
 * Composition root for the Universal Multicurrency plugin.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC;

/**
 * Instantiates services once and registers their hooks.
 *
 * Services are wired here milestone by milestone. Milestone 0 registers
 * nothing: WooCommerce behavior must be byte-identical with the plugin
 * active or inactive.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wires services and registers hooks. Idempotent.
	 */
	public function init(): void {
		// Milestone 0: intentionally empty.
	}
}
