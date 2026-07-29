<?php
/**
 * Presentation data for the Multicurrency admin page shell.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

/**
 * View model for the Multicurrency settings page chrome.
 */
final class AdminPageShellViewModel {

	/**
	 * Plugin title shown in the header card.
	 *
	 * @var string
	 */
	public string $plugin_title;

	/**
	 * Plugin version for the header badge.
	 *
	 * @var string
	 */
	public string $version;

	/**
	 * Header subtitle.
	 *
	 * @var string
	 */
	public string $subtitle;

	/**
	 * Active section slug.
	 *
	 * @var string
	 */
	public string $active_section;

	/**
	 * Active section label.
	 *
	 * @var string
	 */
	public string $section_label;

	/**
	 * Dashicons class for the active section.
	 *
	 * @var string
	 */
	public string $section_icon_class;

	/**
	 * Active section description.
	 *
	 * @var string
	 */
	public string $section_description;

	/**
	 * Whether the active section exposes saveable settings.
	 *
	 * @var bool
	 */
	public bool $has_saveable_settings;

	/**
	 * Optional compatibility notice markup for the header card.
	 *
	 * @var string
	 */
	public string $notice_html;

	/**
	 * Navigation items for all sections.
	 *
	 * @var array<int, SectionNavItemViewModel>
	 */
	public array $navigation_items;

	/**
	 * Creates the shell view model.
	 *
	 * @param string                              $plugin_title           Plugin title.
	 * @param string                              $version                Plugin version.
	 * @param string                              $subtitle               Header subtitle.
	 * @param string                              $active_section         Active section slug.
	 * @param string                              $section_label          Active section label.
	 * @param string                              $section_icon_class     Active section icon class.
	 * @param string                              $section_description    Active section description.
	 * @param bool                                $has_saveable_settings  Whether save is available.
	 * @param string                              $notice_html            Optional notice markup.
	 * @param array<int, SectionNavItemViewModel> $navigation_items    Navigation items.
	 */
	public function __construct(
		string $plugin_title,
		string $version,
		string $subtitle,
		string $active_section,
		string $section_label,
		string $section_icon_class,
		string $section_description,
		bool $has_saveable_settings,
		string $notice_html,
		array $navigation_items
	) {
		$this->plugin_title          = $plugin_title;
		$this->version               = $version;
		$this->subtitle              = $subtitle;
		$this->active_section        = $active_section;
		$this->section_label         = $section_label;
		$this->section_icon_class    = $section_icon_class;
		$this->section_description   = $section_description;
		$this->has_saveable_settings = $has_saveable_settings;
		$this->notice_html           = $notice_html;
		$this->navigation_items      = $navigation_items;
	}
}
