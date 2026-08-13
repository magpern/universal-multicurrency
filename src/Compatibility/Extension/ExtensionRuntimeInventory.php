<?php
/**
 * Runtime plugin inventory for extension compatibility bootstrapping.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Loads installed/active plugin facts once per adapter registration pass.
 */
final class ExtensionRuntimeInventory {

	/**
	 * Returns installed plugins from the WordPress plugin registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	/**
	 * Returns active plugin bootstrap paths.
	 *
	 * @return array<int, string>
	 */
	public static function active_plugins(): array {
		$active = get_option( 'active_plugins', array() );

		return array_values( is_array( $active ) ? $active : array() );
	}
}
