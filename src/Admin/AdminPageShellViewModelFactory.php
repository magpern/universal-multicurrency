<?php
/**
 * Builds presentation data for the Multicurrency admin page shell.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\AdminPageShellViewModel;
use UMC\Admin\ViewModel\SectionNavItemViewModel;

/**
 * Maps section metadata and runtime context into a shell view model.
 */
final class AdminPageShellViewModelFactory {

	/**
	 * Section icon classes keyed by slug.
	 *
	 * @var array<string, string>
	 */
	private const SECTION_ICONS = array(
		SettingsPage::SECTION_CURRENCIES     => 'dashicons-money-alt',
		SettingsPage::SECTION_EXCHANGE_RATES => 'dashicons-update',
		SettingsPage::SECTION_DISPLAY        => 'dashicons-art',
		SettingsPage::SECTION_CHECKOUT       => 'dashicons-cart',
		SettingsPage::SECTION_COMPATIBILITY  => 'dashicons-admin-plugins',
		SettingsPage::SECTION_ADVANCED       => 'dashicons-admin-generic',
	);

	/**
	 * Settings page supplying section labels and URLs.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $page;

	/**
	 * Creates the view-model factory.
	 *
	 * @param SettingsPage $page Settings page instance.
	 */
	public function __construct( SettingsPage $page ) {
		$this->page = $page;
	}

	/**
	 * Builds the shell view model for one active section.
	 *
	 * @param string $active_section Active section slug.
	 * @param string $notice_html    Optional compatibility notice markup.
	 */
	public function build( string $active_section, string $notice_html = '' ): AdminPageShellViewModel {
		$sections = $this->page->get_sections();
		$version  = defined( 'UMC_VERSION' ) ? (string) UMC_VERSION : '';

		return new AdminPageShellViewModel(
			__( 'Universal Multicurrency', 'universal-multicurrency' ),
			$version,
			__( 'for WooCommerce', 'universal-multicurrency' ),
			$active_section,
			$sections[ $active_section ] ?? __( 'Multicurrency', 'universal-multicurrency' ),
			$this->icon_for( $active_section ),
			$this->description_for( $active_section ),
			$this->page->section_has_header_save( $active_section ),
			$notice_html,
			$this->navigation_items( $active_section, $sections )
		);
	}

	/**
	 * Returns the localized description for one section.
	 *
	 * @param string $section Section slug.
	 */
	public function description_for( string $section ): string {
		switch ( $section ) {
			case SettingsPage::SECTION_CURRENCIES:
				return __( 'Manage the currencies available across your store.', 'universal-multicurrency' );
			case SettingsPage::SECTION_EXCHANGE_RATES:
				return __( 'Configure providers, update schedules, and effective rates.', 'universal-multicurrency' );
			case SettingsPage::SECTION_DISPLAY:
				return __( 'Configure how prices and currency information are displayed across your store.', 'universal-multicurrency' );
			case SettingsPage::SECTION_CHECKOUT:
				return __( 'Choose which currency customers use during checkout.', 'universal-multicurrency' );
			case SettingsPage::SECTION_COMPATIBILITY:
				return __( 'Review store compatibility, conflicts, cache interactions, and support diagnostics.', 'universal-multicurrency' );
			case SettingsPage::SECTION_ADVANCED:
				return __( 'Manage advanced behavior and diagnostic settings.', 'universal-multicurrency' );
		}

		return '';
	}

	/**
	 * Returns the placeholder secondary line for one section.
	 *
	 * @param string $section Section slug.
	 */
	public function placeholder_secondary_line( string $section ): string {
		switch ( $section ) {
			case SettingsPage::SECTION_DISPLAY:
				return __( 'We\'re working on display customization options for your store.', 'universal-multicurrency' );
			case SettingsPage::SECTION_CHECKOUT:
				return __( 'We\'re working on checkout currency behavior options for your store.', 'universal-multicurrency' );
			case SettingsPage::SECTION_COMPATIBILITY:
				return __( 'We\'re working on compatibility guidance and tooling for your store.', 'universal-multicurrency' );
			case SettingsPage::SECTION_ADVANCED:
				return __( 'We\'re working on advanced configuration options for your store.', 'universal-multicurrency' );
		}

		return __( 'We\'re working on additional settings for this section.', 'universal-multicurrency' );
	}

	/**
	 * Returns the icon class for one section.
	 *
	 * @param string $section Section slug.
	 */
	public function icon_for( string $section ): string {
		return self::SECTION_ICONS[ $section ] ?? 'dashicons-admin-generic';
	}

	/**
	 * Builds navigation items for all sections.
	 *
	 * @param string               $active_section Active section slug.
	 * @param array<string,string> $sections       Section labels keyed by slug.
	 * @return array<int, SectionNavItemViewModel>
	 */
	private function navigation_items( string $active_section, array $sections ): array {
		$items = array();

		foreach ( $sections as $slug => $label ) {
			$items[] = new SectionNavItemViewModel(
				$slug,
				$label,
				$this->icon_for( $slug ),
				$this->page->section_url( $slug ),
				$slug === $active_section
			);
		}

		return $items;
	}
}
