<?php
/**
 * Prevents duplicate automatic switcher output per request.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Tracks whether the automatic switcher has already rendered.
 */
final class AutomaticRenderRegistry {

	/**
	 * Whether automatic output has rendered.
	 *
	 * @var bool
	 */
	private bool $automatic_rendered = false;

	/**
	 * Marks automatic output as rendered.
	 */
	public function mark_automatic_rendered(): void {
		$this->automatic_rendered = true;
	}

	/**
	 * Whether automatic output has already rendered.
	 */
	public function has_automatic_rendered(): bool {
		return $this->automatic_rendered;
	}

	/**
	 * Resets registry state (tests only).
	 */
	public function reset(): void {
		$this->automatic_rendered = false;
	}
}
