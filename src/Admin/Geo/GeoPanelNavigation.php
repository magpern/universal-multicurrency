<?php
/**
 * Visitor Location hub secondary navigation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

use UMC\Admin\AdminComponentRenderer;

/**
 * Renders horizontal pill navigation inside the Visitor Location section.
 */
final class GeoPanelNavigation {

	/**
	 * Admin component renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $components;

	/**
	 * Creates the panel navigation renderer.
	 *
	 * @param AdminComponentRenderer|null $components Optional component renderer.
	 */
	public function __construct( ?AdminComponentRenderer $components = null ) {
		$this->components = $components ?? new AdminComponentRenderer();
	}

	/**
	 * Renders the panel navigation list.
	 *
	 * @param string $active_panel Active panel id.
	 */
	public function render( string $active_panel ): void {
		$items = array();

		foreach ( GeoPanelRegistry::panel_ids() as $panel ) {
			$items[] = array(
				'label'  => GeoPanelRegistry::label( $panel ),
				'url'    => GeoPanelRegistry::panel_url( $panel ),
				'icon'   => GeoPanelRegistry::icon( $panel ),
				'active' => $panel === $active_panel,
			);
		}

		echo $this->components->pill_navigation( __( 'Visitor Location panels', 'universal-multicurrency' ), $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in renderer.
	}
}
