<?php
/**
 * Automatic floating switcher placement.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Injects the automatic switcher in the footer when configured.
 */
final class AutomaticSwitcherPlacement {

	/**
	 * Display settings repository.
	 *
	 * @var SwitcherSettingsRepository
	 */
	private SwitcherSettingsRepository $settings_repository;

	/**
	 * View-model factory for automatic placement.
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
	 * Storefront request guards for automatic rendering.
	 *
	 * @var StorefrontRequestContext
	 */
	private StorefrontRequestContext $context;

	/**
	 * Tracks whether automatic placement already rendered this request.
	 *
	 * @var AutomaticRenderRegistry
	 */
	private AutomaticRenderRegistry $registry;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private \UMC\CurrencyContext $currency_context;

	/**
	 * Binds automatic placement to settings, rendering, and request guards.
	 *
	 * @param SwitcherSettingsRepository $settings_repository Display settings.
	 * @param SwitcherViewModelFactory   $factory             View-model factory.
	 * @param SwitcherRenderer           $renderer            Shared renderer.
	 * @param SwitcherAssets             $assets              Asset service.
	 * @param StorefrontRequestContext   $context             Request guards.
	 * @param AutomaticRenderRegistry    $registry            Automatic render registry.
	 * @param \UMC\CurrencyContext       $currency_context    Currency facade.
	 */
	public function __construct(
		SwitcherSettingsRepository $settings_repository,
		SwitcherViewModelFactory $factory,
		SwitcherRenderer $renderer,
		SwitcherAssets $assets,
		StorefrontRequestContext $context,
		AutomaticRenderRegistry $registry,
		\UMC\CurrencyContext $currency_context
	) {
		$this->settings_repository = $settings_repository;
		$this->factory             = $factory;
		$this->renderer            = $renderer;
		$this->assets              = $assets;
		$this->context             = $context;
		$this->registry            = $registry;
		$this->currency_context    = $currency_context;
	}

	/**
	 * Registers the footer hook.
	 */
	public function register(): void {
		add_action( 'wp_footer', array( $this, 'maybe_render' ), 20 );
	}

	/**
	 * Renders the automatic switcher once per request when allowed.
	 */
	public function maybe_render(): void {
		if ( ! $this->context->allows_automatic_render() ) {
			return;
		}

		if ( $this->registry->has_automatic_rendered() ) {
			return;
		}

		$settings = $this->settings_repository->get();

		if ( ! $settings->should_render_automatic() ) {
			return;
		}

		if ( count( $this->currency_context->get_selectable_codes() ) < 2 ) {
			return;
		}

		$this->assets->ensure_enqueued();

		$model = $this->factory->create( $settings );

		if ( null === $model ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer returns fully escaped HTML.
		echo $this->renderer->render( $model );

		$this->registry->mark_automatic_rendered();
	}
}
