<?php
/**
 * Checkout policy settings field for the Multicurrency admin tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Checkout\CheckoutSettings;
use UMC\Settings;

/**
 * Renders checkout currency policy settings.
 */
final class CheckoutSettingsField {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Binds the checkout settings field to the settings store.
	 *
	 * @param Settings $settings Merchant settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Renders checkout policy settings.
	 */
	public function render(): void {
		$checkout = CheckoutSettings::from_array( $this->settings->get()['checkout'] ?? array() );
		$mode     = $checkout->mode();
		?>
		<tr valign="top">
			<th scope="row">
				<label for="umc_checkout_mode"><?php esc_html_e( 'Checkout currency mode', 'universal-multicurrency' ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_html_e( 'Checkout currency mode', 'universal-multicurrency' ); ?></span></legend>
					<label for="umc_checkout_mode_selected">
						<input
							type="radio"
							name="umc_checkout[mode]"
							id="umc_checkout_mode_selected"
							value="<?php echo esc_attr( CheckoutSettings::MODE_SELECTED ); ?>"
							<?php checked( CheckoutSettings::MODE_SELECTED, $mode ); ?>
						/>
						<?php esc_html_e( 'Selected currency', 'universal-multicurrency' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Keep the shopper’s selected currency through checkout.', 'universal-multicurrency' ); ?></p>
					<label for="umc_checkout_mode_store">
						<input
							type="radio"
							name="umc_checkout[mode]"
							id="umc_checkout_mode_store"
							value="<?php echo esc_attr( CheckoutSettings::MODE_STORE ); ?>"
							<?php checked( CheckoutSettings::MODE_STORE, $mode ); ?>
						/>
						<?php esc_html_e( 'Store currency at checkout entry', 'universal-multicurrency' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Browse and cart in the selected currency, but switch to the store currency when checkout begins.', 'universal-multicurrency' ); ?></p>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="umc_checkout_show_notice"><?php esc_html_e( 'Customer notice', 'universal-multicurrency' ); ?></label>
			</th>
			<td class="forminp">
				<label for="umc_checkout_show_notice">
					<input
						type="checkbox"
						name="umc_checkout[show_notice]"
						id="umc_checkout_show_notice"
						value="1"
						<?php checked( $checkout->show_notice() ); ?>
					/>
					<?php esc_html_e( 'Show an informational notice when checkout currency changes.', 'universal-multicurrency' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses checkout settings from POST data.
	 *
	 * @return array<string, mixed>
	 */
	public function parse_post(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by WooCommerce settings save; sanitized via Settings::sanitize_checkout().
		$raw = isset( $_POST['umc_checkout'] ) && is_array( $_POST['umc_checkout'] )
			? wp_unslash( $_POST['umc_checkout'] )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return Settings::sanitize_checkout( $raw );
	}
}
