<?php
/**
 * Enqueues scoped admin assets for the Multicurrency settings tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Display\SwitcherSettings;

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

		if ( $this->is_display_section() ) {
			$switcher_base = plugin_dir_url( UMC_PLUGIN_FILE ) . 'assets/css/switcher.css';
			$switcher_path = plugin_dir_path( UMC_PLUGIN_FILE ) . 'assets/css/switcher.css';

			wp_enqueue_style(
				'umc-switcher',
				$switcher_base,
				array(),
				(string) filemtime( $switcher_path )
			);

			wp_localize_script(
				'umc-admin-settings',
				'umcDisplayPreview',
				array(
					'placements'  => array(
						SwitcherSettings::PLACEMENT_MANUAL,
						SwitcherSettings::PLACEMENT_FLOATING_SIDE,
						SwitcherSettings::PLACEMENT_STICKY_FOOTER,
					),
					'styles'      => array(
						SwitcherSettings::STYLE_DROPDOWN,
						SwitcherSettings::STYLE_HORIZONTAL_LIST,
					),
					'samples'     => array(
						array(
							'code'   => 'EUR',
							'symbol' => '€',
							'name'   => 'Euro',
						),
						array(
							'code'   => 'SEK',
							'symbol' => 'kr',
							'name'   => 'Swedish krona',
						),
						array(
							'code'   => 'USD',
							'symbol' => '$',
							'name'   => 'US Dollar',
						),
					),
					'statusOn'    => __( 'On', 'universal-multicurrency' ),
					'statusOff'   => __( 'Off', 'universal-multicurrency' ),
					'copySuccess' => __( 'Shortcode copied.', 'universal-multicurrency' ),
					'copyFailed'  => __( 'Could not copy shortcode.', 'universal-multicurrency' ),
					'copyPrompt'  => __( 'Copy shortcode:', 'universal-multicurrency' ),
				)
			);
		}

		if ( $this->is_compatibility_section() ) {
			wp_enqueue_script(
				'umc-admin-compatibility',
				$base . 'umc-compatibility.js',
				array(),
				(string) filemtime( $path . 'umc-compatibility.js' ),
				true
			);

			wp_localize_script(
				'umc-admin-compatibility',
				'umcCompatibility',
				array(
					'copySuccess'  => __( 'Report copied.', 'universal-multicurrency' ),
					'copyFailed'   => __( 'Could not copy report.', 'universal-multicurrency' ),
					'showEvidence' => __( 'Show technical evidence', 'universal-multicurrency' ),
					'hideEvidence' => __( 'Hide technical evidence', 'universal-multicurrency' ),
				)
			);
		}
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

	/**
	 * Whether the active Multicurrency tab section is Display.
	 */
	private function is_display_section(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query args.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : '';

		return SettingsPage::SECTION_DISPLAY === $section;
	}

	/**
	 * Whether the active Multicurrency tab section is Compatibility.
	 */
	private function is_compatibility_section(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query args.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( (string) $_GET['section'] ) ) : '';

		return SettingsPage::SECTION_COMPATIBILITY === $section;
	}
}
