<?php
/**
 * Extension compatibility adapter contract.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Bridges one third-party extension into existing UMC monetary seams.
 */
interface ExtensionCompatibilityAdapterInterface {

	/**
	 * Extension id this adapter serves (matches registry definition id).
	 */
	public function extension_id(): string;

	/**
	 * Registers WooCommerce hooks when the extension is active.
	 */
	public function register(): void;

	/**
	 * Whether the adapter considers itself active for the current request.
	 */
	public function is_active(): bool;
}
