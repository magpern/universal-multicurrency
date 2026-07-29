<?php
/**
 * Reusable presentation markup for Display settings controls.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Renders admin control primitives for the Display configurator.
 */
final class DisplayControlRenderer {

	/**
	 * Renders one visual choice card backed by a native radio input.
	 *
	 * @param string                $name         Input name.
	 * @param string                $value        Input value.
	 * @param bool                  $checked      Whether selected.
	 * @param string                $title        Visible title.
	 * @param string                $description  Supporting description.
	 * @param string                $diagram_html Optional decorative diagram markup.
	 * @param array<string, string> $attrs        Extra input attributes.
	 * @param string                $badge        Optional badge label (for example, Recommended).
	 * @param string                $note         Optional secondary note below the description.
	 */
	public function choice_card(
		string $name,
		string $value,
		bool $checked,
		string $title,
		string $description = '',
		string $diagram_html = '',
		array $attrs = array(),
		string $badge = '',
		string $note = ''
	): string {
		$attr_html = '';

		foreach ( $attrs as $key => $attr_value ) {
			if ( null === $attr_value || '' === $attr_value ) {
				continue;
			}

			$attr_html .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $attr_value ) );
		}

		$description_html = '' !== $description
			? sprintf( '<span class="umc-display-choice-card__description">%s</span>', esc_html( $description ) )
			: '';

		$badge_html = '' !== $badge
			? sprintf( '<span class="umc-display-choice-card__badge">%s</span>', esc_html( $badge ) )
			: '';

		$note_html = '' !== $note
			? sprintf( '<span class="umc-display-choice-card__note">%s</span>', esc_html( $note ) )
			: '';

		$diagram = '' !== $diagram_html
			? sprintf( '<span class="umc-display-choice-card__diagram">%s</span>', $diagram_html ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-owned SVG fragments only.
			: '';

		return sprintf(
			'<label class="umc-display-choice-card"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /><span class="umc-display-choice-card__content"><span class="umc-display-choice-card__title">%5$s</span>%6$s%7$s%8$s%9$s</span></label>',
			esc_attr( $name ),
			esc_attr( $value ),
			checked( $checked, true, false ),
			$attr_html,
			esc_html( $title ),
			$description_html,
			$badge_html,
			$note_html,
			$diagram
		);
	}

	/**
	 * Renders a segmented radio control group.
	 *
	 * @param string                              $name     Input name.
	 * @param array<string, string>               $options  Value => label map.
	 * @param string                              $selected Selected value.
	 * @param array<string, string>               $attrs    Extra input attributes applied to each option.
	 * @param array<string, array<string,string>> $option_attrs Per-option extra attributes keyed by value.
	 */
	public function segmented_control(
		string $name,
		array $options,
		string $selected,
		array $attrs = array(),
		array $option_attrs = array()
	): string {
		$shared = '';

		foreach ( $attrs as $key => $attr_value ) {
			if ( null === $attr_value || '' === $attr_value ) {
				continue;
			}

			$shared .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $attr_value ) );
		}

		$items = '';

		foreach ( $options as $value => $label ) {
			$extra = $shared;

			foreach ( $option_attrs[ $value ] ?? array() as $key => $attr_value ) {
				if ( null === $attr_value || '' === $attr_value ) {
					continue;
				}

				$extra .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $attr_value ) );
			}

			$items .= sprintf(
				'<label class="umc-display-segment"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /><span>%5$s</span></label>',
				esc_attr( $name ),
				esc_attr( (string) $value ),
				checked( $selected, (string) $value, false ),
				$extra,
				esc_html( (string) $label )
			);
		}

		return sprintf( '<div class="umc-display-segmented">%s</div>', $items );
	}

	/**
	 * Renders one toggle row backed by a native checkbox.
	 *
	 * @param string                $name        Input name.
	 * @param bool                  $checked     Whether checked.
	 * @param string                $label       Visible label.
	 * @param string                $description Optional description.
	 * @param array<string, string> $attrs       Extra input attributes.
	 */
	public function toggle_row(
		string $name,
		bool $checked,
		string $label,
		string $description = '',
		array $attrs = array()
	): string {
		$attr_html = '';

		foreach ( $attrs as $key => $attr_value ) {
			if ( null === $attr_value || '' === $attr_value ) {
				continue;
			}

			$attr_html .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $attr_value ) );
		}

		$description_html = '' !== $description
			? sprintf( '<span class="umc-display-toggle-row__description">%s</span>', esc_html( $description ) )
			: '';

		return sprintf(
			'<label class="umc-display-toggle-row"><input type="hidden" name="%1$s" value="0" /><input type="checkbox" name="%1$s" value="1"%2$s%3$s /><span class="umc-display-toggle-row__label">%4$s</span>%5$s</label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			$attr_html,
			esc_html( $label ),
			$description_html
		);
	}

	/**
	 * Renders a scoped callout for the Display configurator.
	 *
	 * @param string $type    Callout type: info, warning.
	 * @param string $message Message text.
	 */
	public function callout( string $type, string $message ): string {
		return sprintf(
			'<div class="umc-display-callout umc-display-callout--%1$s" role="note"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Opens a field group wrapper.
	 *
	 * @param string $extra_class Optional extra class names.
	 */
	public function field_group_open( string $extra_class = '' ): string {
		$classes = trim( 'umc-display-field-group ' . $extra_class );

		return sprintf( '<div class="%s">', esc_attr( $classes ) );
	}

	/**
	 * Closes a field group wrapper.
	 */
	public function field_group_close(): string {
		return '</div>';
	}

	/**
	 * Returns a static placement diagram for manual mode.
	 */
	public function diagram_placement_manual(): string {
		return $this->svg_frame(
			'<rect x="6" y="8" width="52" height="36" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><rect x="18" y="18" width="28" height="8" rx="2" fill="currentColor" opacity="0.35"/>'
		);
	}

	/**
	 * Returns a static placement diagram for floating side mode.
	 */
	public function diagram_placement_floating_side(): string {
		return $this->svg_frame(
			'<rect x="6" y="8" width="52" height="36" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><rect x="48" y="16" width="6" height="20" rx="3" fill="currentColor"/>'
		);
	}

	/**
	 * Returns a static placement diagram for floating bottom mode.
	 */
	public function diagram_placement_floating_bottom(): string {
		return $this->svg_frame(
			'<rect x="6" y="8" width="52" height="36" rx="4" fill="none" stroke="currentColor" stroke-width="2"/><rect x="18" y="38" width="28" height="6" rx="3" fill="currentColor"/>'
		);
	}

	/**
	 * Returns a static style diagram for dropdown mode.
	 */
	public function diagram_style_dropdown(): string {
		return $this->svg_frame(
			'<rect x="14" y="16" width="36" height="10" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M44 20l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
		);
	}

	/**
	 * Returns a static style diagram for horizontal list mode.
	 */
	public function diagram_style_horizontal_list(): string {
		return $this->svg_frame(
			'<rect x="10" y="18" width="12" height="8" rx="2" fill="currentColor" opacity="0.35"/><rect x="26" y="18" width="12" height="8" rx="2" fill="currentColor" opacity="0.55"/><rect x="42" y="18" width="12" height="8" rx="2" fill="currentColor"/>'
		);
	}

	/**
	 * Wraps static SVG path markup in an accessible decorative shell.
	 *
	 * @param string $inner Inner SVG markup.
	 */
	private function svg_frame( string $inner ): string {
		return sprintf(
			'<svg class="umc-display-diagram" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 52" width="64" height="52" aria-hidden="true" focusable="false">%s</svg>',
			$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static plugin-owned markup.
		);
	}
}
