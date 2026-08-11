<?php
/**
 * Unit tests for Display settings POST parsing.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use UMC\Admin\DisplaySettingsField;
use UMC\Currency;
use UMC\Currency\CurrencyMetadata;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Covers Custom CSS authority and the schema-6 Content card payload.
 *
 * Unit tests run without WordPress, so `current_user_can()` is undefined and
 * {@see \UMC\Display\SwitcherCustomCss::can_edit()} reports false. That is the
 * unauthorized actor the forged-save cases below assert against; the authorized
 * branch is covered by SwitcherCustomCssTest against `resolve_for_save()`.
 *
 * @covers \UMC\Admin\DisplaySettingsField
 */
final class DisplaySettingsFieldTest extends TestCase {

	private const STORED_CSS = '.umc-switcher { letter-spacing: 0.02em; }';

	protected function tearDown(): void {
		unset( $_POST['umc_display'] );

		parent::tearDown();
	}

	public function test_forged_custom_css_submission_cannot_replace_stored_css(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'custom_css' => '.umc-switcher { display: none; }',
		);

		$result = $this->field( self::STORED_CSS )->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( self::STORED_CSS, $result['display']['custom_css'] );
	}

	public function test_forged_custom_css_clear_cannot_delete_stored_css(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'custom_css' => '',
		);

		$result = $this->field( self::STORED_CSS )->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( self::STORED_CSS, $result['display']['custom_css'] );
	}

	public function test_saving_other_display_settings_preserves_stored_custom_css(): void {
		$_POST['umc_display'] = array(
			'enabled'   => '1',
			'placement' => SwitcherSettings::PLACEMENT_MANUAL,
			'style'     => SwitcherSettings::STYLE_HORIZONTAL_LIST,
		);

		$result = $this->field( self::STORED_CSS )->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( self::STORED_CSS, $result['display']['custom_css'] );
		$this->assertSame( SwitcherSettings::STYLE_HORIZONTAL_LIST, $result['display']['style'] );
	}

	public function test_stored_css_that_would_be_rejected_today_is_not_re_persisted(): void {
		$_POST['umc_display'] = array( 'enabled' => '1' );

		$result = $this->field( '.umc-switcher { background: url(https://evil.test/x.png); }' )->parse_post();

		$this->assertNotNull( $result );
		$this->assertSame( '', $result['display']['custom_css'] );
	}

	public function test_content_card_payload_round_trips_per_context_composition(): void {
		$_POST['umc_display'] = array(
			'enabled' => '1',
			'content' => array(
				'trigger'      => array(
					'show_code'   => '1',
					'show_symbol' => '0',
					'order'       => 'symbol,name,code',
				),
				'menu'         => array(
					'show_code'   => '1',
					'show_symbol' => '1',
					'show_name'   => '1',
					'order'       => 'code,symbol,name',
				),
				'show_chevron' => '1',
			),
		);

		$result = $this->field()->parse_post();

		$this->assertNotNull( $result );

		$content = $result['display']['content'];

		$this->assertTrue( $content['trigger']['show_code'] );
		$this->assertFalse( $content['trigger']['show_symbol'] );
		$this->assertFalse( $content['trigger']['show_name'] );
		$this->assertSame( array( 'code' ), $content['trigger']['order'] );
		$this->assertTrue( $content['menu']['show_name'] );
		$this->assertSame( array( 'code', 'symbol', 'name' ), $content['menu']['order'] );
		$this->assertTrue( $content['show_chevron'] );
	}

	public function test_design_and_responsive_payload_is_persisted(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'design'     => array(
				'preset'    => SwitcherSettings::PRESET_PILL,
				'motion'    => SwitcherSettings::MOTION_NONE,
				'overrides' => array(
					'surface'        => '#111827',
					'radius'         => '14',
					'control_height' => '',
					'spacing'        => 'not-a-number',
				),
			),
			'responsive' => array(
				'hide_name_on_mobile' => '1',
				'compact_on_mobile'   => '1',
			),
		);

		$result = $this->field()->parse_post();

		$this->assertNotNull( $result );

		$display = $result['display'];

		$this->assertSame( SwitcherSettings::PRESET_PILL, $display['design']['preset'] );
		$this->assertSame( SwitcherSettings::MOTION_NONE, $display['design']['motion'] );
		$this->assertSame( '#111827', $display['design']['overrides']['surface'] );
		$this->assertSame( 14, $display['design']['overrides']['radius'] );
		$this->assertArrayNotHasKey( 'control_height', $display['design']['overrides'] );
		$this->assertArrayNotHasKey( 'spacing', $display['design']['overrides'] );
		$this->assertTrue( $display['responsive']['hide_name_on_mobile'] );
		$this->assertTrue( $display['responsive']['compact_on_mobile'] );
	}

	public function test_invalid_visibility_submission_is_rejected(): void {
		$_POST['umc_display'] = array(
			'enabled'    => '1',
			'visibility' => array(
				'desktop' => '0',
				'mobile'  => '0',
			),
		);

		$this->assertNull( $this->field()->parse_post() );
	}

	/**
	 * Builds the field with an injected Display subtree.
	 *
	 * @param string $custom_css Stored Custom CSS.
	 */
	private function field( string $custom_css = '' ): DisplaySettingsField {
		$display = SwitcherSettings::default_array();

		$display['custom_css'] = $custom_css;

		$settings = new Settings(
			array(
				'currencies' => array(),
				'display'    => $display,
			)
		);

		$repository = new SwitcherSettingsRepository( $settings );
		$registry   = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context    = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		$factory = new SwitcherViewModelFactory( $context, $this->metadata(), $repository );

		return new DisplaySettingsField( $settings, $factory, new SwitcherRenderer(), $repository );
	}

	private function metadata(): CurrencyMetadataProvider {
		return new class() implements CurrencyMetadataProvider {
			public function get( string $code ): ?CurrencyMetadata {
				return new CurrencyMetadata( $code, $code . ' name', 'EUR' === $code ? '€' : '$', 2, 'left' );
			}

			public function all(): array {
				return array();
			}

			public function search( string $query ): array {
				return array();
			}

			public function is_known( string $code ): bool {
				return true;
			}
		};
	}
}
