<?php
/**
 * Settings link on the Plugins screen.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Adds a WooCommerce Multicurrency settings shortcut on the plugin row.
 */
final class PluginActionLinks {

	/**
	 * Registers the plugin row action filter.
	 */
	public function register(): void {
		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		$basename = plugin_basename( UMC_PLUGIN_FILE );
		add_filter( "plugin_action_links_{$basename}", array( $this, 'add_settings_link' ) );
	}

	/**
	 * Prepends the Multicurrency settings tab link.
	 *
	 * @param array<int, string> $links Existing plugin row links.
	 * @return array<int, string>
	 */
	public function add_settings_link( array $links ): array {
		$url = admin_url( 'admin.php?page=wc-settings&tab=umc' );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'universal-multicurrency' )
			)
		);

		return $links;
	}
}
