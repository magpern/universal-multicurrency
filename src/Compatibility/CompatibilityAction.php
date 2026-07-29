<?php
/**
 * Recommended action for a compatibility result.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Optional merchant-facing action link.
 */
final class CompatibilityAction {

	/**
	 * Visible action label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Optional safe admin URL.
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Creates an action.
	 *
	 * @param string $label Visible label.
	 * @param string $url   Optional admin URL.
	 */
	public function __construct( string $label, string $url = '' ) {
		$this->label = $label;
		$this->url   = $url;
	}

	/**
	 * Action label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Optional admin URL.
	 */
	public function url(): string {
		return $this->url;
	}

	/**
	 * Whether the action has a URL.
	 */
	public function has_url(): bool {
		return '' !== $this->url;
	}
}
