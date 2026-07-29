<?php
/**
 * Switcher render payload.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * View model consumed by {@see SwitcherRenderer}.
 */
final class SwitcherViewModel {

	/**
	 * Assembles a switcher render payload from normalized inputs.
	 *
	 * @param string                              $instance_id Unique DOM instance id suffix.
	 * @param SwitcherSettings                    $settings    Normalized settings.
	 * @param array<int, SwitcherOptionViewModel> $options     Selectable options.
	 * @param SwitcherOptionViewModel|null        $active      Active option.
	 * @param bool                                $preview     Whether preview mode is active.
	 */
	public function __construct(
		private string $instance_id,
		private SwitcherSettings $settings,
		private array $options,
		private ?SwitcherOptionViewModel $active,
		private bool $preview = false
	) {
	}

	/**
	 * Unique instance id suffix.
	 */
	public function instance_id(): string {
		return $this->instance_id;
	}

	/**
	 * Normalized settings.
	 */
	public function settings(): SwitcherSettings {
		return $this->settings;
	}

	/**
	 * Selectable currency options.
	 *
	 * @return array<int, SwitcherOptionViewModel>
	 */
	public function options(): array {
		return $this->options;
	}

	/**
	 * Active currency option, if any.
	 */
	public function active(): ?SwitcherOptionViewModel {
		return $this->active;
	}

	/**
	 * Whether the view model is for administration preview.
	 */
	public function is_preview(): bool {
		return $this->preview;
	}

	/**
	 * Root CSS classes for the switcher element.
	 *
	 * @return array<int, string>
	 */
	public function root_classes(): array {
		return $this->settings->modifier_classes( $this->preview );
	}

	/**
	 * Inline CSS custom properties.
	 *
	 * @return array<string, string>
	 */
	public function css_variables(): array {
		return $this->settings->css_variables();
	}

	/**
	 * Whether the horizontal list presentation is active.
	 */
	public function is_horizontal_list(): bool {
		return SwitcherSettings::STYLE_HORIZONTAL_LIST === $this->settings->style();
	}

	/**
	 * Menu element id for aria-controls.
	 */
	public function menu_id(): string {
		return 'umc-switcher-menu-' . $this->instance_id;
	}

	/**
	 * Trigger button id.
	 */
	public function trigger_id(): string {
		return 'umc-switcher-trigger-' . $this->instance_id;
	}
}
