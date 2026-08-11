<?php
/**
 * One currency option in the switcher view model.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Presentation data for one selectable currency.
 */
final class SwitcherOptionViewModel {

	/**
	 * Builds one selectable currency option for rendering.
	 *
	 * The HTML fragments carry pre-escaped structured elements. When they are
	 * absent the renderer falls back to escaping the plain-text labels.
	 *
	 * @param string      $code                 Currency code.
	 * @param string      $label                Menu label as plain text.
	 * @param string      $compact_label        Trigger label as plain text.
	 * @param string      $url                  Switch URL.
	 * @param bool        $is_active            Whether this option is active.
	 * @param string|null $menu_html            Escaped menu element markup.
	 * @param string|null $trigger_content_html Escaped trigger element markup.
	 */
	public function __construct(
		private string $code,
		private string $label,
		private string $compact_label,
		private string $url,
		private bool $is_active,
		private ?string $menu_html = null,
		private ?string $trigger_content_html = null
	) {
	}

	/**
	 * Currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Expanded list label as plain text.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Compact trigger label as plain text.
	 */
	public function compact_label(): string {
		return $this->compact_label;
	}

	/**
	 * Escaped structured markup for menu links, empty when unavailable.
	 */
	public function menu_html(): string {
		return $this->menu_html ?? '';
	}

	/**
	 * Escaped structured markup for the trigger, empty when unavailable.
	 */
	public function trigger_content_html(): string {
		return $this->trigger_content_html ?? '';
	}

	/**
	 * Switch URL.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Whether this option represents the active currency.
	 */
	public function is_active(): bool {
		return $this->is_active;
	}
}
