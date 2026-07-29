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
	 * @param string $code         Currency code.
	 * @param string $label        List label.
	 * @param string $compact_label Trigger label.
	 * @param string $url          Switch URL.
	 * @param bool   $is_active    Whether this option is active.
	 */
	public function __construct(
		private string $code,
		private string $label,
		private string $compact_label,
		private string $url,
		private bool $is_active
	) {
	}

	/**
	 * Currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Expanded list label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Compact trigger label.
	 */
	public function compact_label(): string {
		return $this->compact_label;
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
