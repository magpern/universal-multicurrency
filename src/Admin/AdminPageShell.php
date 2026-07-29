<?php
/**
 * Renders the Multicurrency settings page shell chrome.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\AdminPageShellViewModel;

/**
 * Outputs the header card and icon navigation for the settings tab.
 */
final class AdminPageShell {

	/**
	 * Icon navigation renderer.
	 *
	 * @var SectionNavigation
	 */
	private SectionNavigation $navigation;

	/**
	 * Creates the page shell renderer.
	 *
	 * @param SectionNavigation $navigation Icon navigation renderer.
	 */
	public function __construct( SectionNavigation $navigation ) {
		$this->navigation = $navigation;
	}

	/**
	 * Renders the page shell above the WooCommerce settings form.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 */
	public function render( AdminPageShellViewModel $view_model ): void {
		?>
		<div class="umc-settings-shell">
			<?php $this->render_header( $view_model ); ?>
			<?php $this->navigation->render( $view_model ); ?>
		</div>
		<?php
	}

	/**
	 * Opens the active section content card.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 * @param SectionHeader           $header     Section header renderer.
	 */
	public function open_section_card( AdminPageShellViewModel $view_model, SectionHeader $header ): void {
		?>
		<div class="umc-section-card">
			<?php $header->render( $view_model ); ?>
			<div class="umc-section-card__body">
		<?php
	}

	/**
	 * Closes the active section content card.
	 */
	public function close_section_card(): void {
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the top plugin header card.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 */
	private function render_header( AdminPageShellViewModel $view_model ): void {
		?>
		<header class="umc-shell-header">
			<div class="umc-shell-header__brand">
				<div class="umc-shell-header__mark" aria-hidden="true">
					<span class="umc-shell-header__mark-text">UMC</span>
				</div>
				<div class="umc-shell-header__titles">
					<div class="umc-shell-header__title-row">
						<h2 class="umc-shell-header__title"><?php echo esc_html( $view_model->plugin_title ); ?></h2>
						<?php if ( '' !== $view_model->version ) : ?>
							<span class="umc-shell-header__version">v<?php echo esc_html( $view_model->version ); ?></span>
						<?php endif; ?>
					</div>
					<p class="umc-shell-header__subtitle"><?php echo esc_html( $view_model->subtitle ); ?></p>
				</div>
			</div>

			<?php if ( '' !== $view_model->notice_html ) : ?>
				<div class="umc-shell-header__notice">
					<?php echo $view_model->notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped at source in ConflictNotice. ?>
				</div>
			<?php endif; ?>

			<?php if ( $view_model->has_saveable_settings ) : ?>
				<div class="umc-shell-header__actions">
					<button type="submit" name="save" value="<?php echo esc_attr__( 'Save changes', 'universal-multicurrency' ); ?>" form="mainform" class="button button-primary umc-shell-header__save">
						<?php esc_html_e( 'Save changes', 'universal-multicurrency' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</header>
		<?php
	}
}
