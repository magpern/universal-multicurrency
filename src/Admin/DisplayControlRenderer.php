<?php
/**
 * Reusable presentation markup for Display settings controls.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Backward-compatible facade over the plugin-wide admin component renderer.
 */
final class DisplayControlRenderer {

	/**
	 * Shared admin component renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $components;

	/**
	 * Creates the Display control renderer facade.
	 *
	 * @param AdminComponentRenderer|null $components Optional component renderer.
	 */
	public function __construct( ?AdminComponentRenderer $components = null ) {
		$this->components = $components ?? new AdminComponentRenderer();
	}

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
		return $this->components->choice_card(
			$name,
			$value,
			$checked,
			$title,
			$description,
			$diagram_html,
			$attrs,
			$badge,
			$note
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
		return $this->components->segmented_control( $name, $options, $selected, $attrs, $option_attrs );
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
		return $this->components->toggle_row( $name, $checked, $label, $description, $attrs );
	}

	/**
	 * Renders a scoped callout for the Display configurator.
	 *
	 * @param string $type    Callout type: info, warning.
	 * @param string $message Message text.
	 */
	public function callout( string $type, string $message ): string {
		return $this->components->callout( $type, $message );
	}

	/**
	 * Opens a field group wrapper.
	 *
	 * @param string $extra_class Optional extra class names.
	 */
	public function field_group_open( string $extra_class = '' ): string {
		return $this->components->field_group_open( $extra_class );
	}

	/**
	 * Closes a field group wrapper.
	 */
	public function field_group_close(): string {
		return $this->components->field_group_close();
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
