<?php
/**
 * Editor-only assets for the currency switcher block.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Registers the block editor script in admin contexts only.
 */
final class SwitcherBlockEditorAssets {

	public const SCRIPT_HANDLE = 'umc-switcher-block-editor';

	/**
	 * Hooks editor script registration ahead of block type registration.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_editor_script' ), 15 );
	}

	/**
	 * Registers the vanilla block editor script handle.
	 */
	public function register_editor_script(): void {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		$script_path = plugin_dir_path( UMC_PLUGIN_FILE ) . 'blocks/currency-switcher/editor.js';
		$script_url  = plugin_dir_url( UMC_PLUGIN_FILE ) . 'blocks/currency-switcher/editor.js';
		$version     = defined( 'UMC_VERSION' ) ? UMC_VERSION : '0';

		wp_register_script(
			self::SCRIPT_HANDLE,
			$script_url,
			array(
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-server-side-render',
			),
			is_readable( $script_path ) ? (string) filemtime( $script_path ) : $version,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'umcSwitcherBlock',
			array(
				'displaySettingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=umc&section=display' ),
			)
		);
	}
}
