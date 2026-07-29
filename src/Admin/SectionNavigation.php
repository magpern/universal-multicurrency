<?php
/**
 * Renders the Multicurrency settings icon navigation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\AdminPageShellViewModel;
use UMC\Admin\ViewModel\SectionNavItemViewModel;

/**
 * Outputs the segmented section navigation card.
 */
final class SectionNavigation {

	/**
	 * Renders the navigation card.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 */
	public function render( AdminPageShellViewModel $view_model ): void {
		?>
		<nav class="umc-shell-nav" aria-label="<?php esc_attr_e( 'Multicurrency settings sections', 'universal-multicurrency' ); ?>">
			<ul class="umc-shell-nav__list">
				<?php foreach ( $view_model->navigation_items as $item ) : ?>
					<?php $this->render_item( $item ); ?>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Renders one navigation item.
	 *
	 * @param SectionNavItemViewModel $item Navigation item view model.
	 */
	private function render_item( SectionNavItemViewModel $item ): void {
		$classes = array( 'umc-shell-nav__item' );

		if ( $item->is_active ) {
			$classes[] = 'umc-shell-nav__item--active';
		}

		$current = $item->is_active ? ' aria-current="page"' : '';
		?>
		<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<a class="umc-shell-nav__link" href="<?php echo esc_url( $item->url ); ?>"<?php echo $current; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute name. ?>>
				<span class="umc-shell-nav__icon dashicons <?php echo esc_attr( $item->icon_class ); ?>" aria-hidden="true"></span>
				<span class="umc-shell-nav__label"><?php echo esc_html( $item->label ); ?></span>
			</a>
		</li>
		<?php
	}
}
