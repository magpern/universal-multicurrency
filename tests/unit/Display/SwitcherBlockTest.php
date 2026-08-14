<?php
/**
 * Unit tests for the native currency switcher block.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Display\StorefrontRequestContext;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherBlock;
use UMC\Display\SwitcherPresence;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Covers render delegation and embedded placement override behavior.
 */
final class SwitcherBlockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		SwitcherViewModelFactory::reset_instance_counter();
	}

	public function test_render_returns_empty_when_services_are_unbound(): void {
		$block = new SwitcherBlock();

		$this->assertSame( '', $block->render( array(), '' ) );
	}

	public function test_render_delegates_to_switcher_renderer_on_storefront(): void {
		$services = $this->services(
			array(
				'enabled' => true,
			)
		);

		$html = $services['block']->render( array(), '' );

		$this->assertStringContainsString( 'umc-switcher', $html );
		$this->assertStringContainsString( 'umc-switcher--manual', $html );
		$this->assertStringNotContainsString( 'umc-switcher--floating-side', $html );
	}

	public function test_render_uses_manual_placement_when_global_floating_is_configured(): void {
		$services = $this->services(
			array(
				'enabled'   => true,
				'placement' => SwitcherSettings::PLACEMENT_FLOATING_SIDE,
			)
		);

		$html = $services['block']->render( array(), '' );

		$this->assertStringContainsString( 'umc-switcher--manual', $html );
		$this->assertStringNotContainsString( 'umc-switcher--floating-side', $html );
	}

	/**
	 * @param array<string, mixed> $display Display overrides.
	 * @return array{block: SwitcherBlock}
	 */
	private function services( array $display ): array {
		$settings = new Settings(
			array(
				'currencies' => array(
					'SEK' => array(
						'manual_rate' => '11.50',
					),
				),
				'display'    => array_merge(
					SwitcherSettings::default_array(),
					$display
				),
			)
		);

		$base     = new Currency( 'EUR', 2 );
		$registry = new CurrencyRegistry( $settings, $base );
		$rates    = new ManualRateProvider( $settings, 'EUR' );
		$context  = new CurrencyContext( $registry, $rates, new CurrencyResolver() );
		$repo     = new SwitcherSettingsRepository( $settings );
		$metadata = $this->createMock( CurrencyMetadataProvider::class );
		$factory  = new SwitcherViewModelFactory( $context, $metadata, $repo );
		$renderer = new SwitcherRenderer();
		$assets   = new SwitcherAssets( new StorefrontRequestContext(), $repo, $context, new SwitcherPresence() );
		$block    = new SwitcherBlock();
		$block->bind( $repo, $factory, $renderer, $assets );

		return array(
			'block' => $block,
		);
	}
}
