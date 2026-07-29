<?php
/**
 * Currency switcher shortcode registration.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Registers primary and legacy switcher shortcodes.
 */
final class SwitcherShortcode {

	public const TAG_PRIMARY = 'universal_multicurrency_switcher';

	public const TAG_LEGACY = 'umc_switcher';

	/**
	 * View-model factory for shortcode rendering.
	 *
	 * @var SwitcherViewModelFactory
	 */
	private SwitcherViewModelFactory $factory;

	/**
	 * Shared switcher HTML renderer.
	 *
	 * @var SwitcherRenderer
	 */
	private SwitcherRenderer $renderer;

	/**
	 * Storefront asset registration service.
	 *
	 * @var SwitcherAssets
	 */
	private SwitcherAssets $assets;

	/**
	 * Binds shortcode rendering to factory, renderer, and assets.
	 *
	 * @param SwitcherViewModelFactory $factory  View-model factory.
	 * @param SwitcherRenderer         $renderer Shared renderer.
	 * @param SwitcherAssets           $assets   Asset service.
	 */
	public function __construct(
		SwitcherViewModelFactory $factory,
		SwitcherRenderer $renderer,
		SwitcherAssets $assets
	) {
		$this->factory  = $factory;
		$this->renderer = $renderer;
		$this->assets   = $assets;
	}

	/**
	 * Registers supported shortcodes.
	 */
	public function register(): void {
		add_shortcode( self::TAG_PRIMARY, array( $this, 'render_shortcode' ) );
		add_shortcode( self::TAG_LEGACY, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renders a switcher shortcode instance.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_shortcode( $atts ): string {
		unset( $atts );

		$this->assets->ensure_enqueued();
		$this->assets->maybe_print_stylesheet_fallback();

		$model = $this->factory->create();

		if ( null === $model ) {
			return '';
		}

		return $this->renderer->render( $model );
	}
}
