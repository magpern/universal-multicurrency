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

		$items = '';

		foreach ( $view_model->options() as $option ) {
			$items .= $this->render_option_link( $option, $view_model->is_preview() );
		}

		return sprintf(
			'<div %1$s><button type="button" class="umc-switcher__trigger" id="%2$s" aria-expanded="false" aria-controls="%3$s"><span class="umc-switcher__trigger-content">%4$s</span>%5$s</button><ul class="umc-switcher__menu" id="%3$s" hidden>%6$s</ul></div>',
			$this->root_attributes( $view_model ),
			esc_attr( $view_model->trigger_id() ),
			esc_attr( $view_model->menu_id() ),
			$this->trigger_content( $active ),
			$view_model->show_chevron() ? '<span class="umc-switcher__chevron" aria-hidden="true"></span>' : '',
			$items
		);
	}

	/**
	 * Renders the horizontal list switcher presentation.
	 *
	 * @param SwitcherViewModel $view_model Render payload.
	 */
	private function render_horizontal_list( SwitcherViewModel $view_model ): string {
		$items = '';

		foreach ( $view_model->options() as $option ) {
			$items .= sprintf(
				'<li class="umc-switcher__item%1$s">%2$s</li>',
				$option->is_active() ? ' is-active' : '',
				$this->render_option_link( $option, $view_model->is_preview(), true )
			);
		}

		return sprintf(
			'<div %1$s><ul class="umc-switcher__list">%2$s</ul></div>',
			$this->root_attributes( $view_model, array( 'umc-switcher--expanded' ) ),
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

		$link = sprintf(
			'<a class="umc-switcher__link" href="%1$s"%2$s%3$s>%4$s</a>',
			esc_url( $option->url() ),
			$rel,
			$current,
			$this->menu_content( $option )
		);

		if ( $inline ) {
			return $link;
		}

		return '<li>' . $link . '</li>';
	}

	/**
	 * Escaped element markup for the trigger, falling back to the plain label.
	 *
	 * @param SwitcherOptionViewModel $option Active currency option.
	 */
	private function trigger_content( SwitcherOptionViewModel $option ): string {
		$html = $option->trigger_content_html();

		if ( '' !== $html ) {
			return $html;
		}

		return esc_html( $option->compact_label() );
	}

	/**
	 * Escaped element markup for a menu link, falling back to the plain label.
	 *
	 * @param SwitcherOptionViewModel $option Currency option.
	 */
	private function menu_content( SwitcherOptionViewModel $option ): string {
		$html = $option->menu_html();

		if ( '' !== $html ) {
			return $html;
		}

		return esc_html( $option->label() );
	}

	/**
	 * Builds the escaped attribute list for the switcher root element.
	 *
	 * @param SwitcherViewModel  $view_model    Render payload.
	 * @param array<int, string> $extra_classes Additional root classes.
	 */
	private function root_attributes( SwitcherViewModel $view_model, array $extra_classes = array() ): string {
		return sprintf(
			'class="%1$s" style="%2$s" data-umc-placement="%3$s" data-umc-style="%4$s"',
			esc_attr( implode( ' ', array_merge( $view_model->root_classes(), $extra_classes ) ) ),
			esc_attr( $this->style_attr( $view_model->css_variables() ) ),
			esc_attr( $view_model->placement_attribute() ),
			esc_attr( $view_model->style_attribute() )
		);
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
