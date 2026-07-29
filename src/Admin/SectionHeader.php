<?php
/**
 * Renders the active section header inside the content card.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\AdminPageShellViewModel;

/**
 * Outputs the section icon, title, and description block.
 */
final class SectionHeader {

	/**
	 * Renders the section header row.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 */
	public function render( AdminPageShellViewModel $view_model ): void {
		?>
		<header class="umc-section-card__header">
			<div class="umc-section-card__icon-wrap" aria-hidden="true">
				<span class="umc-section-card__icon dashicons <?php echo esc_attr( $view_model->section_icon_class ); ?>"></span>
			</div>
			<div class="umc-section-card__heading">
				<h2 class="umc-section-card__title"><?php echo esc_html( $view_model->section_label ); ?></h2>
				<p class="umc-section-card__description"><?php echo esc_html( $view_model->section_description ); ?></p>
			</div>
		</header>
		<?php
	}
}
