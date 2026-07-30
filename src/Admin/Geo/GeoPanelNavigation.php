<?php
/**
 * Geo Detection hub secondary navigation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\Geo;

/**
 * Renders horizontal panel navigation inside the Geo Detection section.
 */
final class GeoPanelNavigation {

	/**
	 * Renders the panel navigation list.
	 *
	 * @param string $active_panel Active panel id.
	 */
	public function render( string $active_panel ): void {
		?>
		<nav class="umc-geo-panel-nav" aria-label="<?php esc_attr_e( 'Geo Detection panels', 'universal-multicurrency' ); ?>">
			<ul class="umc-geo-panel-nav__list">
				<?php foreach ( GeoPanelRegistry::panel_ids() as $panel ) : ?>
					<?php
					$classes = array( 'umc-geo-panel-nav__item' );

					if ( $panel === $active_panel ) {
						$classes[] = 'umc-geo-panel-nav__item--active';
					}
					?>
					<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
						<a
							class="umc-geo-panel-nav__link"
							href="<?php echo esc_url( GeoPanelRegistry::panel_url( $panel ) ); ?>"
							<?php echo $panel === $active_panel ? ' aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute. ?>
						>
							<?php echo esc_html( GeoPanelRegistry::label( $panel ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}
}
