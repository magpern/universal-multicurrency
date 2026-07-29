<?php
/**
 * Enqueues scoped admin assets for the Multicurrency settings tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Loads admin CSS/JS only on the Multicurrency settings screens.
 */
final class AdminAssets {

	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Enqueues assets for the Multicurrency settings tab.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query args.
		if ( ! isset( $_GET['tab'] ) || 'umc' !== sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) ) {
			return;
		}

		if ( ! defined( 'UMC_VERSION' ) || ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		$base = plugin_dir_url( UMC_PLUGIN_FILE ) . 'assets/admin/';
		$path = plugin_dir_path( UMC_PLUGIN_FILE ) . 'assets/admin/';

		wp_enqueue_style(
			'umc-admin-settings',
			$base . 'umc-settings.css',
			array( 'dashicons' ),
			(string) filemtime( $path . 'umc-settings.css' )
		);

		wp_enqueue_script(
			'umc-admin-settings',
			$base . 'umc-settings.js',
			array( 'jquery' ),
			(string) filemtime( $path . 'umc-settings.js' ),
			true
		);
	}

	/**
	 * Adds a body class on the Multicurrency settings screens.
	 *
	 * @param string $classes Space-separated admin body classes.
	 */
	public function body_class( string $classes ): string {
		if ( ! is_admin() ) {
			return $classes;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query args.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) || 'umc' !== sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) ) {
			return $classes;
		}

		return trim( $classes . ' umc-settings-page' );
	}
}
