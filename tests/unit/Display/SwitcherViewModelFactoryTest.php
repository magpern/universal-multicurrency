<?php
/**
 * Unit tests for switcher view-model creation.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Display;

use PHPUnit\Framework\TestCase;
use UMC\Currency;
use UMC\Currency\CurrencyMetadata;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\CurrencyContext;
use UMC\CurrencyRegistry;
use UMC\CurrencyResolver;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;

/**
 * Covers option ordering, no-output guards, and unique instance ids.
 */
final class SwitcherViewModelFactoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		SwitcherViewModelFactory::reset_instance_counter();
	}

	public function test_returns_null_when_fewer_than_two_selectable_currencies(): void {
		$factory = $this->factory(
			new Settings(
				array(
					'display'    => array( 'enabled' => true ),
					'currencies' => array(),
				)
			)
		);

		$this->assertNull( $factory->create() );
	}

	public function test_returns_null_when_disabled_on_storefront(): void {
		$factory = $this->factory(
			new Settings(
				array(
					'display'    => array( 'enabled' => false ),
					'currencies' => array(
						'SEK' => array(
							'enabled' => true,
							'rate'    => '11.5',
						),
					),
				)
			)
		);

		$this->assertNull( $factory->create() );
	}

	public function test_preview_mode_allows_disabled_settings(): void {
		$factory = $this->factory(
			new Settings(
				array(
					'display'    => array( 'enabled' => false ),
					'currencies' => array(
						'SEK' => array(
							'enabled' => true,
							'rate'    => '11.5',
						),
					),
				)
			)
		);

		$this->assertNotNull( $factory->create( null, true ) );
	}

	public function test_active_first_orders_active_currency_first(): void {
		$factory = $this->factory(
			new Settings(
				array(
					'display'    => array(
						'enabled'  => true,
						'behavior' => array( 'active_first' => true ),
					),
					'currencies' => array(
						'SEK' => array(
							'enabled' => true,
							'rate'    => '11.5',
						),
						'USD' => array(
							'enabled' => true,
							'rate'    => '1.1',
						),
					),
				)
			)
		);

		$model = $factory->create(
			SwitcherSettings::from_array(
				array(
					'enabled'  => true,
					'behavior' => array( 'active_first' => true ),
				)
			),
			true
		);

		$this->assertNotNull( $model );
		$this->assertSame( 'EUR', $model->options()[0]->code() );
	}

	public function test_unique_instance_ids_increment(): void {
		$factory = $this->two_currency_factory();

		$first  = $factory->create( null, true );
		$second = $factory->create( null, true );

		$this->assertNotNull( $first );
		$this->assertNotNull( $second );
		$this->assertNotSame( $first->instance_id(), $second->instance_id() );
	}

	public function test_preview_urls_are_hash_links(): void {
		$model = $this->two_currency_factory()->create( null, true );

		$this->assertNotNull( $model );
		$this->assertSame( '#', $model->options()[0]->url() );
	}

	private function two_currency_factory(): SwitcherViewModelFactory {
		return $this->factory(
			new Settings(
				array(
					'display'    => array( 'enabled' => true ),
					'currencies' => array(
						'SEK' => array(
							'enabled' => true,
							'rate'    => '11.5',
						),
					),
				)
			)
		);
	}

	private function factory( Settings $settings ): SwitcherViewModelFactory {
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$context  = new CurrencyContext( $registry, new ManualRateProvider( $settings, 'EUR' ), new CurrencyResolver() );

		return new SwitcherViewModelFactory(
			$context,
			new class() implements CurrencyMetadataProvider {
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
			},
			new SwitcherSettingsRepository( $settings )
		);
	}
}
