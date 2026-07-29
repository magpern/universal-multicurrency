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
	 * Binds asset policy to request context and dependencies.
	 *
	 * @param StorefrontRequestContext   $context             Request guards.
	 * @param SwitcherSettingsRepository $settings_repository Display settings.
	 * @param CurrencyContext            $currency_context    Currency facade.
	 */
	public function __construct(
		StorefrontRequestContext $context,
		SwitcherSettingsRepository $settings_repository,
		CurrencyContext $currency_context
	) {
		$this->context             = $context;
		$this->settings_repository = $settings_repository;
		$this->currency_context    = $currency_context;
	}

	/**
	 * Registers asset hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_automatic' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_from_post_content' ), 15 );
		add_filter( 'language_attributes', array( $this, 'append_no_js_class' ) );
	}

	/**
	 * Registers stylesheet and script handles.
	 */
	public function register_assets(): void {
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
	 * Enqueues assets for automatic placement when knowable before output.
	 */
	public function maybe_enqueue_automatic(): void {
		if ( ! $this->context->allows_storefront_assets() ) {
			return;
		}

		$settings = $this->settings_repository->get();

		if ( ! $settings->should_render_automatic() ) {
			return;
		}

		if ( count( $this->currency_context->get_selectable_codes() ) < 2 ) {
			return;
		}

		$this->ensure_enqueued();
	}

	/**
	 * Enqueues assets when a supported shortcode appears in the main post content.
	 */
	public function maybe_enqueue_from_post_content(): void {
		if ( ! $this->context->allows_storefront_assets() ) {
			return;
		}

		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! $this->post_contains_switcher_shortcode( $post->post_content ) ) {
			return;
		}

		if ( ! $this->settings_repository->get()->is_enabled() ) {
			return;
		}

		if ( count( $this->currency_context->get_selectable_codes() ) < 2 ) {
			return;
		}

		$this->ensure_enqueued();
	}

	/**
	 * Ensures switcher assets are queued for the current request.
	 */
	public function ensure_enqueued(): void {
		$this->register_assets();

		if ( ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	/**
	 * Prints a deduplicated stylesheet fallback when styles were not printed in time.
	 */
	public function maybe_print_stylesheet_fallback(): void {
		if ( $this->fallback_printed ) {
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

	/**
	 * Detects supported switcher shortcodes in post content.
	 *
	 * @param string $content Post content.
	 */
	private function post_contains_switcher_shortcode( string $content ): bool {
		return has_shortcode( $content, SwitcherShortcode::TAG_PRIMARY )
			|| has_shortcode( $content, SwitcherShortcode::TAG_LEGACY );
	}
}
