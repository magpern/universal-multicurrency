<?php
/**
 * Bounded switcher presence detection for asset loading.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Detects whether the current request likely renders a switcher surface.
 *
 * Proactive detection is an optimization for early enqueue. Render callbacks
 * remain the correctness backstop via SwitcherAssets::ensure_enqueued().
 */
final class SwitcherPresence {

	public const BLOCK_NAME = 'universal-multicurrency/currency-switcher';

	/**
	 * Whether proactive asset loading should run for this request.
	 *
	 * @param SwitcherSettings $settings Display settings.
	 * @param int              $selectable_count Number of selectable currencies.
	 */
	public function should_load_switcher_assets( SwitcherSettings $settings, int $selectable_count ): bool {
		if ( ! $settings->is_enabled() ) {
			return false;
		}

		if ( $selectable_count < 2 ) {
			return false;
		}

		if ( $settings->should_render_automatic() ) {
			return true;
		}

		if ( $this->current_post_contains_shortcode() ) {
			return true;
		}

		if ( $this->current_post_contains_block() ) {
			return true;
		}

		if ( $this->current_template_contains_block() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the current post contains a supported switcher shortcode.
	 */
	public function current_post_contains_shortcode(): bool {
		$content = $this->current_post_content();

		if ( '' === $content ) {
			return false;
		}

		return has_shortcode( $content, SwitcherShortcode::TAG_PRIMARY )
			|| has_shortcode( $content, SwitcherShortcode::TAG_LEGACY );
	}

	/**
	 * Whether the current post contains the currency switcher block.
	 */
	public function current_post_contains_block(): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		$content = $this->current_post_content();

		if ( '' === $content ) {
			return false;
		}

		return has_block( self::BLOCK_NAME, $content );
	}

	/**
	 * Whether the current resolved block template contains the switcher block.
	 *
	 * Fetches at most one template record when WordPress exposes a current template id.
	 */
	public function current_template_contains_block(): bool {
		if ( ! function_exists( 'has_block' ) || ! function_exists( 'get_block_template' ) ) {
			return false;
		}

		if ( ! function_exists( 'wp_get_current_template_id' ) ) {
			return false;
		}

		$template_id = wp_get_current_template_id();

		if ( ! is_string( $template_id ) || '' === $template_id ) {
			return false;
		}

		$template = get_block_template( $template_id, 'wp_template' );

		if ( ! is_object( $template ) || ! isset( $template->content ) || ! is_string( $template->content ) ) {
			return false;
		}

		return has_block( self::BLOCK_NAME, $template->content );
	}

	/**
	 * Raw post content for the current singular query object.
	 */
	private function current_post_content(): string {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		return (string) $post->post_content;
	}
}
