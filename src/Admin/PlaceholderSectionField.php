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
	 * Renders the placeholder field.
	 */
	public function render(): void {
		?>
		<tr valign="top">
			<td class="forminp umc-settings" colspan="2">
				<div class="notice notice-info inline umc-section-placeholder">
					<p><?php esc_html_e( 'This section will be implemented in a future milestone.', 'universal-multicurrency' ); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}
}
