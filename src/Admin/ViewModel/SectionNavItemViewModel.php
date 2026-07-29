<?php
/**
 * Presentation data for one Multicurrency settings navigation item.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

/**
 * Immutable navigation item for the Multicurrency settings shell.
 */
final class SectionNavItemViewModel {

	/**
	 * Section slug.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Localized section label.
	 *
	 * @var string
	 */
	public string $label;

	/**
	 * Dashicons class for the section icon.
	 *
	 * @var string
	 */
	public string $icon_class;

	/**
	 * Absolute admin URL for the section.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Whether this item represents the active section.
	 *
	 * @var bool
	 */
	public bool $is_active;

	/**
	 * Creates a navigation item view model.
	 *
	 * @param string $slug       Section slug.
	 * @param string $label      Localized label.
	 * @param string $icon_class Dashicons CSS class.
	 * @param string $url        Section URL.
	 * @param bool   $is_active  Whether the section is active.
	 */
	public function __construct(
		string $slug,
		string $label,
		string $icon_class,
		string $url,
		bool $is_active
	) {
		$this->slug       = $slug;
		$this->label      = $label;
		$this->icon_class = $icon_class;
		$this->url        = $url;
		$this->is_active  = $is_active;
	}
}
