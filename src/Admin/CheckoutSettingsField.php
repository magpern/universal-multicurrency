<?php
/**
 * Checkout policy settings field for the Multicurrency admin tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Checkout\CheckoutSettings;
use UMC\Currency;
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
	 * Store base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Presentation markup helper.
	 *
	 * @var DisplayControlRenderer
	 */
	private DisplayControlRenderer $controls;

	/**
	 * Binds the checkout settings field to the settings store.
	 *
	 * @param Settings                    $settings Merchant settings store.
	 * @param Currency                    $base     Store base currency.
	 * @param DisplayControlRenderer|null $controls Optional presentation helper.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		?DisplayControlRenderer $controls = null
	) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->controls = $controls ?? new DisplayControlRenderer();
	}

	/**
	 * Renders checkout policy settings.
	 */
	public function render(): void {
		$checkout    = CheckoutSettings::from_array( $this->settings->get()['checkout'] ?? array() );
		$mode        = $checkout->mode();
		$store_code  = $this->base->code();
		$store_title = sprintf(
			/* translators: %s: store currency code, for example EUR. */
			__( 'Switch to store currency (%s)', 'universal-multicurrency' ),
			$store_code
		);
		$mode_name     = 'umc_checkout[mode]';
		$notice_name   = 'umc_checkout[show_notice]';
		$selected_card = $this->controls->choice_card(
			$mode_name,
			CheckoutSettings::MODE_SELECTED,
			CheckoutSettings::MODE_SELECTED === $mode,
			__( 'Keep selected currency', 'universal-multicurrency' ),
			__( 'Customers continue checkout in the currency they selected while browsing.', 'universal-multicurrency' ),
			'',
			array( 'id' => 'umc_checkout_mode_selected' ),
			__( 'Recommended', 'universal-multicurrency' )
		);
		$store_card    = $this->controls->choice_card(
			$mode_name,
			CheckoutSettings::MODE_STORE,
			CheckoutSettings::MODE_STORE === $mode,
			$store_title,
			__( 'Customers browse and use the cart in their selected currency, then checkout switches to the store currency.', 'universal-multicurrency' ),
			'',
			array( 'id' => 'umc_checkout_mode_store' ),
			'',
			__( 'This does not change the customer\'s preferred browsing currency.', 'universal-multicurrency' )
		);

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-checkout-settings" colspan="2">
				<div class="umc-display-card">
					<h3 class="umc-display-card__title"><?php esc_html_e( 'Checkout currency', 'universal-multicurrency' ); ?></h3>
					<p class="umc-checkout-settings__intro"><?php esc_html_e( 'Choose which currency customers use during checkout.', 'universal-multicurrency' ); ?></p>
					<fieldset class="umc-display-fieldset umc-checkout-settings__modes">
						<legend class="screen-reader-text"><?php esc_html_e( 'Checkout currency', 'universal-multicurrency' ); ?></legend>
						<div class="umc-display-choice-cards umc-checkout-choice-cards">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DisplayControlRenderer.
							echo $selected_card;
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DisplayControlRenderer.
							echo $store_card;
							?>
						</div>
					</fieldset>
					<div class="umc-checkout-notice-panel">
						<label class="umc-display-toggle-row umc-checkout-notice-panel__toggle" for="umc_checkout_show_notice">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $notice_name ); ?>"
								id="umc_checkout_show_notice"
								value="1"
								<?php checked( $checkout->show_notice() ); ?>
							/>
							<span class="umc-display-toggle-row__label"><?php esc_html_e( 'Show an informational notice when checkout currency changes.', 'universal-multicurrency' ); ?></span>
							<span class="umc-display-toggle-row__description"><?php esc_html_e( 'Displays a customer-friendly notice whenever checkout switches to another currency.', 'universal-multicurrency' ); ?></span>
						</label>
					</div>
				</div>
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
