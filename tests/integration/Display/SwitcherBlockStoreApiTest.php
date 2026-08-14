<?php
/**
 * Store API regression: block selection uses the same currency state path.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Display;

use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\CurrencyContext;
use UMC\CurrencySwitcher;
use UMC\Display\StorefrontRequestContext;
use UMC\Display\SwitcherAssets;
use UMC\Display\SwitcherBlock;
use UMC\Display\SwitcherPresence;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Settings;
use UMC\Tests\Integration\StoreApi\StoreApiTestCase;

/**
 * Proves block-rendered selection links feed the shared switcher state that
 * Store API extension data reads on the next request.
 */
final class SwitcherBlockStoreApiTest extends StoreApiTestCase {

	private const CURRENCIES = array(
		'SEK' => array( 'rate' => '11.50' ),
		'USD' => array( 'rate' => '1.20' ),
	);

	/**
	 * @return array<string, mixed>
	 */
	private function enabled_display_settings(): array {
		return array_merge(
			SwitcherSettings::default_array(),
			array(
				'enabled'  => true,
				'behavior' => array(
					'remember_selection' => true,
					'active_first'       => true,
				),
			)
		);
	}

	public function test_block_selection_updates_store_api_active_currency(): void {
		$display = $this->enabled_display_settings();

		$this->boot_plugin(
			self::CURRENCIES,
			'EUR',
			'EUR',
			2,
			array(
				'display' => $display,
			)
		);

		$settings = new Settings();
		$repo     = new SwitcherSettingsRepository( $settings );
		$block    = new SwitcherBlock();

		$block->bind(
			$repo,
			new SwitcherViewModelFactory(
				$this->context,
				new WooCommerceCurrencyProvider(),
				$repo
			),
			new SwitcherRenderer(),
			new SwitcherAssets(
				new StorefrontRequestContext(),
				$repo,
				$this->context,
				new SwitcherPresence()
			)
		);

		if ( function_exists( 'unregister_block_type' )
			&& \WP_Block_Type_Registry::get_instance()->is_registered( SwitcherBlock::BLOCK_NAME ) ) {
			unregister_block_type( SwitcherBlock::BLOCK_NAME );
		}

		$block->register();

		$html = do_blocks( '<!-- wp:universal-multicurrency/currency-switcher /-->' );

		$this->assertStringContainsString( 'currency=USD', $html );

		$_GET[ CurrencyContext::QUERY_VAR ] = 'USD';
		( new CurrencySwitcher( $this->context, $repo ) )->maybe_switch();
		unset( $_GET[ CurrencyContext::QUERY_VAR ] );

		$this->boot_plugin(
			self::CURRENCIES,
			'USD',
			'EUR',
			2,
			array(
				'display' => $display,
			)
		);

		$cart = $this->response_data( $this->store_api_request( 'GET', '/cart' ) );

		$this->assertSame(
			'USD',
			$cart['extensions']['umc']['active_currency'],
			'Store API must observe the currency selected through block output.'
		);
	}
}
