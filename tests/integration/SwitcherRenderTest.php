<?php
/**
 * Integration tests for the currency switcher renderer.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration;

use UMC\Currency;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use WP_UnitTestCase;

/**
 * Verifies the reusable switcher markup.
 */
final class SwitcherRenderTest extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_COOKIE[ CurrencyContext::COOKIE_NAME ] );
		delete_option( Settings::OPTION );
		parent::tear_down();
	}

	/**
	 * @param array<string, array<string, mixed>> $currencies Currency rows.
	 * @param string                              $active       Active currency code.
	 * @param array<string, mixed>                $display      Display overrides.
	 */
	private function render_html( array $currencies, string $active, array $display = array() ): string {
		( new Settings() )->save(
			array_merge(
				Settings::defaults(),
				array(
					'currencies' => $currencies,
					'display'    => array_merge(
						SwitcherSettings::default_array(),
						array(
							'enabled' => true,
						),
						$display
					),
				)
			)
		);

		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;

		$context = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$factory = new SwitcherViewModelFactory(
			$context,
			new WooCommerceCurrencyProvider(),
			new SwitcherSettingsRepository( $settings )
		);
		$model   = $factory->create();

		if ( null === $model ) {
			return '';
		}

		return ( new SwitcherRenderer() )->render( $model );
	}

	public function test_dropdown_lists_selectable_and_marks_active(): void {
		$html = $this->render_html(
			array(
				'SEK' => array(
					'symbol' => 'kr',
					'rate'   => '11.50',
				),
				'JPY' => array(
					'decimals' => 0,
					'rate'     => '161',
				),
			),
			'SEK'
		);

		$this->assertStringContainsString( 'umc-switcher__trigger', $html );
		$this->assertStringContainsString( 'EUR', $html );
		$this->assertStringContainsString( 'JPY', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
		$this->assertStringContainsString( 'currency=SEK', $html );
	}

	public function test_horizontal_list_has_nofollow_and_active_marker(): void {
		$html = $this->render_html(
			array( 'SEK' => array( 'rate' => '11.50' ) ),
			'SEK',
			array(
				'style' => SwitcherSettings::STYLE_HORIZONTAL_LIST,
			)
		);

		$this->assertStringContainsString( 'umc-switcher--horizontal-list', $html );
		$this->assertStringContainsString( 'rel="nofollow"', $html );
		$this->assertStringContainsString( 'is-active', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
	}

	public function test_renders_empty_when_only_base_is_selectable(): void {
		$this->assertSame( '', $this->render_html( array(), 'EUR' ) );
	}

	public function test_rateless_currency_is_not_offered(): void {
		$html = $this->render_html(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'JPY' => array( 'rate' => '' ),
			),
			'SEK',
			array(
				'style' => SwitcherSettings::STYLE_HORIZONTAL_LIST,
			)
		);

		$this->assertStringContainsString( 'SEK', $html );
		$this->assertStringNotContainsString( 'currency=JPY', $html );
	}
}
