<?php
/**
 * Storefront switcher asset registration and enqueueing.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

use UMC\CurrencyContext;

/**
 * Owns WordPress asset policy for the currency switcher.
 */
final class SwitcherAssets {

	public const STYLE_HANDLE = 'umc-switcher';

	public const SCRIPT_HANDLE = 'umc-switcher';

	/**
	 * Storefront request guards for asset loading.
	 *
	 * @var StorefrontRequestContext
	 */
	private StorefrontRequestContext $context;

	/**
	 * Bounded switcher presence detection.
	 *
	 * @var SwitcherPresence
	 */
	private SwitcherPresence $presence;

	/**
	 * Display settings repository.
	 *
	 * @var SwitcherSettingsRepository
	 */
	private SwitcherSettingsRepository $settings_repository;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $currency_context;

	/**
	 * Whether a deduplicated stylesheet fallback link was printed.
	 *
	 * @var bool
	 */
	private bool $fallback_printed = false;

	/**
	 * Whether merchant Custom CSS was already attached for this request.
	 *
	 * @var bool
	 */
	private bool $custom_css_attached = false;

	/**
	 * Binds asset policy to request context and dependencies.
	 *
	 * @param StorefrontRequestContext   $context             Request guards.
	 * @param SwitcherSettingsRepository $settings_repository Display settings.
	 * @param CurrencyContext            $currency_context    Currency facade.
	 * @param SwitcherPresence|null      $presence            Presence detector.
	 */
	public function __construct(
		StorefrontRequestContext $context,
		SwitcherSettingsRepository $settings_repository,
		CurrencyContext $currency_context,
		?SwitcherPresence $presence = null
	) {
		$this->context             = $context;
		$this->presence            = $presence ?? new SwitcherPresence();
		$this->settings_repository = $settings_repository;
		$this->currency_context    = $currency_context;
	}

	/**
	 * Registers asset hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_when_present' ), 20 );
		add_filter( 'language_attributes', array( $this, 'append_no_js_class' ) );
	}

	/**
	 * Registers stylesheet and script handles.
	 */
	public function register_assets(): void {
		if ( ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_register_script' ) ) {
			return;
		}

		if ( ! $this->context->allows_storefront_assets() ) {
			return;
		}

		if ( ! defined( 'UMC_VERSION' ) || ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		$base = plugin_dir_url( UMC_PLUGIN_FILE ) . 'assets/';
		$path = plugin_dir_path( UMC_PLUGIN_FILE ) . 'assets/';

		wp_register_style(
			self::STYLE_HANDLE,
			$base . 'css/switcher.css',
			array(),
			UMC_VERSION
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			$base . 'js/switcher.js',
			array(),
			UMC_VERSION,
			true
		);

		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
	}

	/**
	 * Enqueues assets when bounded presence detection finds a switcher surface.
	 */
	public function maybe_enqueue_when_present(): void {
		if ( ! $this->context->allows_storefront_assets() ) {
			return;
		}

		$settings = $this->settings_repository->get();

		if ( ! $this->presence->should_load_switcher_assets( $settings, count( $this->currency_context->get_selectable_codes() ) ) ) {
			return;
		}

		$this->ensure_enqueued();
	}

	/**
	 * Exposes bounded presence detection for tests and guards.
	 */
	public function presence(): SwitcherPresence {
		return $this->presence;
	}

	/**
	 * Ensures switcher assets are queued for the current request.
	 */
	public function ensure_enqueued(): void {
		if ( ! function_exists( 'wp_style_is' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$this->register_assets();

		if ( ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}

		$this->maybe_attach_custom_css();
	}

	/**
	 * Appends merchant Custom CSS after the enqueued switcher stylesheet.
	 *
	 * ADR-0022 permits exactly one emission path: `wp_add_inline_style()` on the
	 * storefront while the stylesheet is enqueued. Custom CSS must never reach
	 * ordinary wp-admin, where the same handle backs the Display live preview,
	 * so the admin request guard is re-checked here rather than trusted from the
	 * caller.
	 */
	public function maybe_attach_custom_css(): void {
		if ( $this->custom_css_attached ) {
			return;
		}

		if ( is_admin() || ! $this->context->allows_storefront_assets() ) {
			return;
		}

		if ( ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			return;
		}

		$this->custom_css_attached = true;

		$css = SwitcherPresentationCss::storefront_custom_css( $this->settings_repository->get() );

		if ( '' === $css ) {
			return;
		}

		wp_add_inline_style( self::STYLE_HANDLE, $css );
	}

	/**
	 * Prints a deduplicated stylesheet fallback when styles were not printed in time.
	 */
	public function maybe_print_stylesheet_fallback(): void {
		if ( $this->fallback_printed ) {
			return;
		}

		if ( ! function_exists( 'wp_style_is' ) || ! function_exists( 'esc_url' ) ) {
			return;
		}

		if ( ! defined( 'UMC_VERSION' ) || ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		if ( wp_style_is( self::STYLE_HANDLE, 'done' ) ) {
			return;
		}

		$url = esc_url( plugin_dir_url( UMC_PLUGIN_FILE ) . 'assets/css/switcher.css?ver=' . rawurlencode( UMC_VERSION ) );

		printf(
			'<link rel="stylesheet" id="%1$s-css-fallback" href="%2$s" media="all" />', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Intentional late shortcode stylesheet fallback when enqueue missed the print window.
			esc_attr( self::STYLE_HANDLE ),
			$url // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);

		$this->fallback_printed = true;
	}

	/**
	 * Appends the no-js document class for progressive enhancement.
	 *
	 * @param string $output Existing language attributes.
	 */
	public function append_no_js_class( string $output ): string {
		if ( ! $this->context->allows_storefront_assets() ) {
			return $output;
		}

		if ( ! $this->settings_repository->get()->is_enabled() ) {
			return $output;
		}

		if ( count( $this->currency_context->get_selectable_codes() ) < 2 ) {
			return $output;
		}

		if ( str_contains( $output, 'no-js' ) ) {
			return $output;
		}

		if ( str_contains( $output, 'class="' ) ) {
			return (string) preg_replace( '/class="/', 'class="no-js ', $output, 1 );
		}

		return trim( $output ) . ' class="no-js"';
	}
}
