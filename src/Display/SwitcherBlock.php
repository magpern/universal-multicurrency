<?php
/**
 * Native currency switcher Gutenberg block.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Registers and renders the dynamic currency switcher block.
 */
final class SwitcherBlock {

	public const BLOCK_NAME = SwitcherPresence::BLOCK_NAME;

	/**
	 * Display settings repository.
	 *
	 * @var SwitcherSettingsRepository|null
	 */
	private ?SwitcherSettingsRepository $settings_repository = null;

	/**
	 * View-model factory.
	 *
	 * @var SwitcherViewModelFactory|null
	 */
	private ?SwitcherViewModelFactory $factory = null;

	/**
	 * Shared switcher HTML renderer.
	 *
	 * @var SwitcherRenderer|null
	 */
	private ?SwitcherRenderer $renderer = null;

	/**
	 * Storefront asset registration service.
	 *
	 * @var SwitcherAssets|null
	 */
	private ?SwitcherAssets $assets = null;

	/**
	 * Binds runtime services after WooCommerce bootstrap.
	 *
	 * @param SwitcherSettingsRepository $settings_repository Display settings.
	 * @param SwitcherViewModelFactory   $factory             View-model factory.
	 * @param SwitcherRenderer           $renderer            Shared renderer.
	 * @param SwitcherAssets             $assets              Asset service.
	 */
	public function bind(
		SwitcherSettingsRepository $settings_repository,
		SwitcherViewModelFactory $factory,
		SwitcherRenderer $renderer,
		SwitcherAssets $assets
	): void {
		$this->settings_repository = $settings_repository;
		$this->factory             = $factory;
		$this->renderer            = $renderer;
		$this->assets              = $assets;
	}

	/**
	 * Registers block metadata and render callback.
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		if ( defined( 'UMC_PLUGIN_FILE' ) && class_exists( \WP_Block_Type_Registry::class ) ) {
			$registry = \WP_Block_Type_Registry::get_instance();

			if ( $registry->is_registered( self::BLOCK_NAME ) ) {
				return;
			}
		}

		if ( ! defined( 'UMC_PLUGIN_FILE' ) ) {
			return;
		}

		register_block_type(
			plugin_dir_path( UMC_PLUGIN_FILE ) . 'blocks/currency-switcher',
			array(
				'render_callback' => array( $this, 'render' ),
				'editor_script'   => SwitcherBlockEditorAssets::SCRIPT_HANDLE,
			)
		);
	}

	/**
	 * Renders one block instance on the storefront or in the editor preview.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner block content.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public function render( array $attributes, string $content, ?\WP_Block $block = null ): string {
		unset( $attributes, $content, $block );

		if ( null === $this->factory || null === $this->renderer || null === $this->assets || null === $this->settings_repository ) {
			return '';
		}

		$settings = $this->settings_repository->get()->for_embedded_surface();

		if ( $this->is_editor_preview_context() ) {
			$model = $this->factory->create_for_admin_preview( $settings );
		} else {
			$this->assets->ensure_enqueued();
			$this->assets->maybe_print_stylesheet_fallback();

			$model = $this->factory->create( $settings );
		}

		if ( null === $model ) {
			return '';
		}

		$inner = $this->renderer->render( $model );

		if ( '' === $inner ) {
			return '';
		}

		if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
			return $inner;
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes(),
			$inner
		);
	}

	/**
	 * Whether the render request is an editor/server preview context.
	 */
	private function is_editor_preview_context(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}

		return false;
	}
}
