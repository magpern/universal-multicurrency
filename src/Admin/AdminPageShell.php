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
	 * Dashicon class for the plugin mark in the header brand.
	 */
	private const PLUGIN_MARK_ICON = 'dashicons-money-alt';

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
	 * Renders the unified header card with brand, navigation, and actions.
	 *
	 * @param AdminPageShellViewModel $view_model Shell presentation data.
	 */
	private function render_header( AdminPageShellViewModel $view_model ): void {
		?>
		<header class="umc-shell-header">
			<div class="umc-shell-header__main">
				<div class="umc-shell-header__brand">
					<div class="umc-shell-header__mark" aria-hidden="true">
						<span class="umc-shell-header__mark-icon dashicons <?php echo esc_attr( self::PLUGIN_MARK_ICON ); ?>"></span>
					</div>
					<div class="umc-shell-header__titles">
						<h2 class="umc-shell-header__title"><?php echo esc_html( $view_model->plugin_title ); ?></h2>
						<p class="umc-shell-header__subtitle"><?php echo esc_html( $view_model->subtitle ); ?></p>
					</div>
				</div>

				<?php $this->navigation->render( $view_model ); ?>

				<?php if ( $view_model->has_header_save ) : ?>
					<div class="umc-shell-header__actions">
						<button type="submit" name="save" value="<?php echo esc_attr__( 'Save changes', 'universal-multicurrency' ); ?>" form="mainform" class="button button-primary umc-shell-header__save">
							<?php esc_html_e( 'Save changes', 'universal-multicurrency' ); ?>
						</button>
						<p class="umc-shell-header__status" aria-live="polite">
							<span class="umc-shell-header__status-icon dashicons dashicons-yes" aria-hidden="true"></span>
							<?php esc_html_e( 'All changes saved', 'universal-multicurrency' ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $view_model->notice_html ) : ?>
				<div class="umc-shell-header__notice">
					<?php echo $view_model->notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped at source in ConflictNotice. ?>
				</div>
			<?php endif; ?>
		</header>
		<?php
	}
}
