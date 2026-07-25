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
use UMC\Frontend\Switcher;
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

	private function context( array $currencies, string $active ): CurrencyContext {
		( new Settings() )->save( array( 'currencies' => $currencies ) );
		$settings = new Settings();
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates    = new ManualRateProvider( $settings, 'EUR' );

		$_COOKIE[ CurrencyContext::COOKIE_NAME ] = $active;

		return new CurrencyContext( $registry, $rates, new CurrencyResolver() );
	}

	public function test_select_layout_lists_selectable_and_marks_active(): void {
		$context = $this->context(
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

		$html = ( new Switcher( $context ) )->render( array( 'layout' => 'select' ) );

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'EUR', $html );
		$this->assertStringContainsString( 'JPY', $html );
		$this->assertStringContainsString( 'selected', $html );
		$this->assertMatchesRegularExpression( '/currency=SEK[^>]*selected/', $html );
	}

	public function test_links_layout_has_nofollow_and_active_marker(): void {
		$context = $this->context(
			array( 'SEK' => array( 'rate' => '11.50' ) ),
			'SEK'
		);

		$html = ( new Switcher( $context ) )->render( array( 'layout' => 'links' ) );

		$this->assertStringContainsString( 'rel="nofollow"', $html );
		$this->assertStringContainsString( 'is-active', $html );
		$this->assertStringContainsString( 'aria-current="true"', $html );
	}

	public function test_renders_empty_when_only_base_is_selectable(): void {
		$context = $this->context( array(), 'EUR' );

		$this->assertSame( '', ( new Switcher( $context ) )->render() );
	}

	public function test_rateless_currency_is_not_offered(): void {
		$context = $this->context(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'JPY' => array( 'rate' => '' ),
			),
			'SEK'
		);

		$html = ( new Switcher( $context ) )->render( array( 'layout' => 'links' ) );

		$this->assertStringContainsString( 'SEK', $html );
		$this->assertStringNotContainsString( 'currency=JPY', $html );
	}
}
