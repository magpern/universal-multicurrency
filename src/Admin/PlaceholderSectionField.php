<?php
/**
 * Placeholder settings section renderer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Renders a standard placeholder for future settings sections.
 */
final class PlaceholderSectionField {

	/**
	 * Secondary line shown beneath the milestone message.
	 *
	 * @var string
	 */
	private string $secondary_line;

	/**
	 * Creates a placeholder field renderer.
	 *
	 * @param string $secondary_line Localized secondary description line.
	 */
	public function __construct( string $secondary_line = '' ) {
		$this->secondary_line = $secondary_line;
	}

	/**
	 * Renders the placeholder field.
	 */
	public function render(): void {
		?>
		<tr valign="top" class="umc-placeholder-row">
			<td class="forminp umc-settings" colspan="2">
				<div class="umc-placeholder-panel" role="note">
					<span class="umc-placeholder-panel__icon dashicons dashicons-info-outline" aria-hidden="true"></span>
					<div class="umc-placeholder-panel__content">
						<p class="umc-placeholder-panel__title">
							<strong><?php esc_html_e( 'This section will be implemented in a future milestone.', 'universal-multicurrency' ); ?></strong>
						</p>
						<?php if ( '' !== $this->secondary_line ) : ?>
							<p class="umc-placeholder-panel__text"><?php echo esc_html( $this->secondary_line ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}
}
