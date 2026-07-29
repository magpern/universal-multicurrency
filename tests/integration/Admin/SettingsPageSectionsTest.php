<?php
/**
 * Integration tests for Multicurrency settings sections.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\SettingsPage;
use UMC\Currency;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies section navigation and save routing.
 */
final class SettingsPageSectionsTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET['section'] );
		parent::tear_down();
	}

	public function test_get_sections_includes_all_planned_navigation_items(): void {
		$page = $this->page();

		$this->assertSame(
			array(
				SettingsPage::SECTION_CURRENCIES,
				SettingsPage::SECTION_EXCHANGE_RATES,
				SettingsPage::SECTION_DISPLAY,
				SettingsPage::SECTION_CHECKOUT,
				SettingsPage::SECTION_COMPATIBILITY,
				SettingsPage::SECTION_ADVANCED,
			),
			array_keys( $page->get_sections() )
		);
	}

	public function test_exchange_rates_section_exposes_global_fields_only(): void {
		$page = $this->page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_EXCHANGE_RATES ), 'type' );

		$this->assertContains( 'umc_exchange_rates', $types );
		$this->assertNotContains( 'umc_currencies', $types );
	}

	public function test_checkout_section_exposes_checkout_field(): void {
		$page = $this->page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_CHECKOUT ), 'type' );

		$this->assertContains( 'umc_checkout', $types );
		$this->assertNotContains( 'umc_currencies', $types );
		$this->assertTrue( $page->section_has_saveable_settings( SettingsPage::SECTION_CHECKOUT ) );
	}

	public function test_advanced_placeholder_section_renders_without_currency_fields(): void {
		$page = $this->page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_ADVANCED ), 'type' );

		$this->assertContains( 'umc_placeholder', $types );
		$this->assertNotContains( 'umc_currencies', $types );
	}

	public function test_compatibility_section_exposes_compatibility_field(): void {
		$page = $this->page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_COMPATIBILITY ), 'type' );

		$this->assertContains( 'umc_compatibility', $types );
		$this->assertNotContains( 'umc_placeholder', $types );
		$this->assertFalse( $page->section_has_saveable_settings( SettingsPage::SECTION_COMPATIBILITY ) );
	}

	public function test_display_section_exposes_display_field(): void {
		$page = $this->page();

		$types = array_column( $page->get_settings_for_section( SettingsPage::SECTION_DISPLAY ), 'type' );

		$this->assertContains( 'umc_display', $types );
		$this->assertTrue( $page->section_has_saveable_settings( SettingsPage::SECTION_DISPLAY ) );
		$this->assertFalse( $page->section_has_header_save( SettingsPage::SECTION_DISPLAY ) );
		$this->assertTrue( $page->section_has_header_save( SettingsPage::SECTION_CURRENCIES ) );
	}

	private function page(): SettingsPage {
		$settings = new Settings();

		return new SettingsPage(
			$settings,
			new Currency( 'EUR', 2 ),
			new ExchangeRateStore( $settings, new RateUpdateState(), 'EUR', 'test-lock' )
		);
	}
}
