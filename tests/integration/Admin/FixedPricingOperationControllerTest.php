<?php
/**
 * M24 WP3 acceptance: the Fixed Pricing preview -> confirm -> execute flow.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\Admin;

use UMC\Admin\FixedPricingOperationController;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Integration\PriceConversionService;
use UMC\Pricing\FixedPriceCatalogOperationsService;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;
use UMC\Pricing\FixedPriceDocument;
use UMC\Rates\ManualRateProvider;
use UMC\Settings;
use UMC\Tests\Support\M20PricingTestCase;
use UMC\Tests\Support\RedirectCapturedException;
use WPDieException;

/**
 * Drives `admin_post_umc_fixed_pricing_execute` end to end against the real
 * {@see FixedPriceCatalogOperationsService} and {@see FixedPriceRepository}
 * persistence boundary.
 *
 * @covers \UMC\Admin\FixedPricingOperationController
 */
final class FixedPricingOperationControllerTest extends M20PricingTestCase {

	private const ACTION = 'admin_post_umc_fixed_pricing_execute';
	private const NONCE  = 'umc_fixed_pricing_execute';

	public function set_up(): void {
		parent::set_up();

		// The real plugin bootstrap (tests/integration/bootstrap.php) may
		// already have constructed a production SettingsPage instance with
		// its own FixedPricingOperationController registered on this action
		// (mirroring how a real request lazily builds SettingsPage via the
		// woocommerce_get_settings_pages filter). That registration predates
		// this test's activate() calls and would intercept the action first
		// with stale services, so start from a clean slate.
		remove_all_actions( self::ACTION );

		add_filter(
			'wp_redirect',
			static function ( $location ): string {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow; never rendered.
				throw new RedirectCapturedException( (string) $location );
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_redirect' );
		remove_all_actions( self::ACTION );
		unset( $_POST['umc_fp_action'], $_POST['umc_fp_currency'], $_POST['umc_fp_scope'], $_POST['product_ids'], $_POST['umc_fp_status'], $_POST['umc_fp_search'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );

		parent::tear_down();
	}

	public function test_checked_scope_seed_persists_converted_authored_price(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100', '80' );
		$this->boot_controller();
		$this->authorize();

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $product->get_id() );

		$redirect = $this->dispatch();

		$this->assertSame( 'success', $redirect->query_arg( 'umc_typ' ), (string) $redirect->query_arg( 'umc_msg' ) );
		$document = $this->repository->get( $product->get_id() );
		$this->assertSame( '1150.00', $document->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '920.00', $document->get_currency( 'SEK' )?->sale() );
	}

	public function test_checked_scope_clear_preserves_other_currencies(): void {
		$this->activate(
			array(
				'SEK' => array( 'rate' => '11.50' ),
				'GBP' => array( 'rate' => '0.85' ),
			),
			'EUR'
		);
		$product = $this->simple_product( '100' );
		$this->repository->save(
			$product->get_id(),
			FixedPriceDocument::from_array(
				array(
					'SEK' => array( 'regular' => '1150' ),
					'GBP' => array( 'regular' => '85' ),
				),
				'EUR'
			)
		);
		$this->boot_controller();
		$this->authorize();

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_CLEAR;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $product->get_id() );

		$redirect = $this->dispatch();

		$this->assertSame( 'success', $redirect->query_arg( 'umc_typ' ) );
		$document = $this->repository->get( $product->get_id() );
		$this->assertNull( $document->get_currency( 'SEK' ) );
		$this->assertSame( '85.00', $document->get_currency( 'GBP' )?->regular() );
	}

	public function test_filtered_scope_recomputes_from_criteria_not_a_resubmitted_id_list(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$a = $this->simple_product( '100' );
		$b = $this->simple_product( '200' );
		$this->boot_controller();
		$this->authorize();

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_FILTERED;
		$_POST['umc_fp_status']   = '';
		$_POST['umc_fp_search']   = '';

		$redirect = $this->dispatch();

		$this->assertSame( 'success', $redirect->query_arg( 'umc_typ' ) );
		$this->assertSame( '1150.00', $this->repository->get( $a->get_id() )->get_currency( 'SEK' )?->regular() );
		$this->assertSame( '2300.00', $this->repository->get( $b->get_id() )->get_currency( 'SEK' )?->regular() );
	}

	/**
	 * M24 falsification B: seed/clear must never target the base currency.
	 */
	public function test_base_currency_is_rejected_without_writing(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );
		$this->boot_controller();
		$this->authorize();

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'EUR';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $product->get_id() );

		$redirect = $this->dispatch();

		$this->assertSame( 'error', $redirect->query_arg( 'umc_typ' ) );
		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'EUR' ) );
	}

	/**
	 * M24 falsification D: a mixed-permission scope must exclude products
	 * the current user cannot edit, not abort or silently include them.
	 */
	public function test_mixed_permission_scope_excludes_unauthorized_products(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$editable   = $this->simple_product( '100' );
		$restricted = $this->simple_product( '200' );
		$this->boot_controller();

		// A shop_manager holds manage_woocommerce (passes the controller's
		// top-level capability check) but this filter additionally denies
		// edit_post for one specific product, simulating a mixed-permission
		// scope (e.g. a restricted/locked product) at the per-product gate.
		$editor = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $editor );

		add_filter(
			'map_meta_cap',
			static function ( array $caps, string $cap, int $user_id, array $args ) use ( $restricted ) {
				if ( 'edit_post' === $cap && isset( $args[0] ) && (int) $args[0] === $restricted->get_id() ) {
					return array( 'do_not_allow' );
				}
				return $caps;
			},
			10,
			4
		);

		$this->sign_request();
		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $editable->get_id(), (string) $restricted->get_id() );

		$redirect = $this->dispatch();

		remove_all_filters( 'map_meta_cap' );

		$this->assertSame( 'success', $redirect->query_arg( 'umc_typ' ) );
		$this->assertNotNull( $this->repository->get( $editable->get_id() )->get_currency( 'SEK' ), 'The editable product must be seeded.' );
		$this->assertNull( $this->repository->get( $restricted->get_id() )->get_currency( 'SEK' ), 'The restricted product must be excluded, not seeded.' );
	}

	public function test_unauthorized_user_cannot_execute(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );
		$this->boot_controller();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->sign_request();

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $product->get_id() );

		try {
			do_action( self::ACTION );
			$this->fail( 'Unauthorized execution must terminate the request.' );
		} catch ( WPDieException $exception ) {
			$this->assertStringContainsString( 'permission', $exception->getMessage() );
		}

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * M24 falsification J: a forged/missing nonce cannot succeed.
	 */
	public function test_missing_nonce_cannot_execute(): void {
		$this->activate( array( 'SEK' => array( 'rate' => '11.50' ) ), 'EUR' );
		$product = $this->simple_product( '100' );
		$this->boot_controller();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['umc_fp_action']   = FixedPricingOperationController::ACTION_SEED;
		$_POST['umc_fp_currency'] = 'SEK';
		$_POST['umc_fp_scope']    = FixedPricingOperationController::SCOPE_CHECKED;
		$_POST['product_ids']     = array( (string) $product->get_id() );

		try {
			do_action( self::ACTION );
			$this->fail( 'A request without a valid nonce must terminate.' );
		} catch ( WPDieException $exception ) {
			unset( $exception );
		}

		$this->assertNull( $this->repository->get( $product->get_id() )->get_currency( 'SEK' ) );
	}

	/**
	 * Registers the production controller wired to the real orchestration
	 * service, sharing this test's repository so assertions observe the
	 * same persisted state the controller wrote.
	 */
	private function boot_controller(): void {
		$settings  = new Settings();
		$registry  = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );
		$rates     = new ManualRateProvider( $settings, 'EUR' );
		$context   = $this->context;
		$converter = new PriceConversionService( $context );
		$coverage  = new FixedPriceCoverageReport( $this->repository );
		$query     = new FixedPriceCatalogQuery( $coverage );
		$service   = new FixedPriceCatalogOperationsService( $this->repository, $coverage, $rates, $converter, $registry );

		( new FixedPricingOperationController( $service, $query ) )->register();
	}

	private function dispatch(): RedirectCapturedException {
		try {
			do_action( self::ACTION );
		} catch ( RedirectCapturedException $redirect ) {
			return $redirect;
		}

		$this->fail( 'The controller must redirect after handling a request.' );
	}

	private function authorize(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->sign_request();
	}

	private function sign_request(): void {
		$nonce                = wp_create_nonce( self::NONCE );
		$_REQUEST['_wpnonce'] = $nonce;
		$_POST['_wpnonce']    = $nonce;
	}
}
