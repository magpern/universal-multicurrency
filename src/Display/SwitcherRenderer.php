<?php
/**
 * Shared HTML renderer for the currency switcher.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Produces escaped switcher markup for storefront and preview use.
 */
final class SwitcherRenderer {

	/**
	 * Renders a switcher view model as HTML.
	 *
	 * @param SwitcherViewModel $view_model Render payload.
	 */
	public function render( SwitcherViewModel $view_model ): string {
		if ( $view_model->is_horizontal_list() ) {
			return $this->render_horizontal_list( $view_model );
		}

		return $this->render_dropdown( $view_model );
	}

	/**
	 * Renders the dropdown switcher presentation.
	 *
	 * @param SwitcherViewModel $view_model Render payload.
	 */
	private function render_dropdown( SwitcherViewModel $view_model ): string {
		$active = $view_model->active();

		if ( null === $active ) {
			return '';
		}

		$classes = $this->class_attr( $view_model->root_classes() );
		$style   = $this->style_attr( $view_model->css_variables() );
		$items   = '';

		foreach ( $view_model->options() as $option ) {
			$items .= $this->render_option_link( $option, $view_model->is_preview() );
		}

		return sprintf(
			'<div class="%1$s" style="%2$s"><button type="button" class="umc-switcher__trigger" id="%3$s" aria-expanded="false" aria-controls="%4$s">%5$s</button><ul class="umc-switcher__menu" id="%4$s" hidden>%6$s</ul></div>',
			esc_attr( $classes ),
			esc_attr( $style ),
			esc_attr( $view_model->trigger_id() ),
			esc_attr( $view_model->menu_id() ),
			esc_html( $active->compact_label() ),
			$items
		);
	}

	/**
	 * Renders the horizontal list switcher presentation.
	 *
	 * @param SwitcherViewModel $view_model Render payload.
	 */
	private function render_horizontal_list( SwitcherViewModel $view_model ): string {
		$classes = $this->class_attr( array_merge( $view_model->root_classes(), array( 'umc-switcher--expanded' ) ) );
		$style   = $this->style_attr( $view_model->css_variables() );
		$items   = '';

		foreach ( $view_model->options() as $option ) {
			$items .= sprintf(
				'<li class="umc-switcher__item%1$s">%2$s</li>',
				$option->is_active() ? ' is-active' : '',
				$this->render_option_link( $option, $view_model->is_preview(), true )
			);
		}

		return sprintf(
			'<div class="%1$s" style="%2$s"><ul class="umc-switcher__list">%3$s</ul></div>',
			esc_attr( $classes ),
			esc_attr( $style ),
			$items
		);
	}

	/**
	 * Renders one currency option as a switch link.
	 *
	 * @param SwitcherOptionViewModel $option  Currency option.
	 * @param bool                    $preview Whether preview mode is active.
	 * @param bool                    $inline  Whether the link is inline in a list item.
	 */
	private function render_option_link( SwitcherOptionViewModel $option, bool $preview, bool $inline = false ): string {
		$current = $option->is_active() ? ' aria-current="true"' : '';
		$rel     = $preview ? '' : ' rel="nofollow"';
		$href    = esc_url( $option->url() );
		$label   = $inline ? $option->label() : $option->label();

		if ( $inline ) {
			return sprintf(
				'<a class="umc-switcher__link" href="%1$s"%2$s%3$s>%4$s</a>',
				$href,
				$rel,
				$current,
				esc_html( $label )
			);
		}

		return sprintf(
			'<li><a class="umc-switcher__link" href="%1$s"%2$s%3$s>%4$s</a></li>',
			$href,
			$rel,
			$current,
			esc_html( $label )
		);
	}

	/**
	 * Joins CSS class names for a root element attribute.
	 *
	 * @param array<int, string> $classes CSS classes.
	 */
	private function class_attr( array $classes ): string {
		return implode( ' ', $classes );
	}

	/**
	 * Serializes CSS custom properties for an inline style attribute.
	 *
	 * @param array<string, string> $variables CSS custom properties.
	 */
	private function style_attr( array $variables ): string {
		$parts = array();

		foreach ( $variables as $name => $value ) {
			$parts[] = $name . ':' . $value;
		}

		return implode( ';', $parts );
	}
}
