<?php
/**
 * Shared shortcode surface scan for switcher placement warnings.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Best-effort scan for switcher shortcodes on key storefront surfaces.
 */
final class SwitcherShortcodeScanner {

	/**
	 * Whether a switcher shortcode appears on a key storefront surface.
	 */
	public function has_shortcode_on_key_surface(): bool {
		$post_ids = array();

		$front_page = (int) get_option( 'page_on_front' );
		if ( $front_page > 0 ) {
			$post_ids[] = $front_page;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_page = (int) wc_get_page_id( 'shop' );
			if ( $shop_page > 0 ) {
				$post_ids[] = $shop_page;
			}
		}

		$menus = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( isset( $item->object_id ) && 'post_type' === $item->type ) {
					$post_ids[] = (int) $item->object_id;
				}
			}
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if (
				has_shortcode( $post->post_content, SwitcherShortcode::TAG_PRIMARY )
				|| has_shortcode( $post->post_content, SwitcherShortcode::TAG_LEGACY )
			) {
				return true;
			}
		}

		return false;
	}
}
