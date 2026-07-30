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
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $controls;

	/**
	 * Binds the checkout settings field to the settings store.
	 *
	 * @param Settings                    $settings Merchant settings store.
	 * @param Currency                    $base     Store base currency.
	 * @param AdminComponentRenderer|null $controls Optional presentation helper.
	 */
	public function __construct(
		Settings $settings,
		Currency $base,
		?AdminComponentRenderer $controls = null
	) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->controls = $controls ?? new AdminComponentRenderer();
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
		$mode_name   = 'umc_checkout[mode]';
		$notice_name = 'umc_checkout[show_notice]';

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-checkout-settings" colspan="2" data-umc-sticky-root="checkout">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in AdminComponentRenderer.
				echo $this->controls->page_intro(
					__( 'Checkout currency', 'universal-multicurrency' ),
					__( 'Choose which currency customers use during checkout.', 'universal-multicurrency' )
				);

				echo $this->controls->settings_card_open(
					__( 'Checkout currency policy', 'universal-multicurrency' ),
					__( 'Select whether checkout keeps the browsing currency or switches to the store currency.', 'universal-multicurrency' )
				);
				echo $this->controls->choice_cards_open();
				echo $this->controls->choice_card(
					$mode_name,
					CheckoutSettings::MODE_SELECTED,
					CheckoutSettings::MODE_SELECTED === $mode,
					__( 'Keep selected currency', 'universal-multicurrency' ),
					__( 'Customers continue checkout in the currency they selected while browsing.', 'universal-multicurrency' ),
					'',
					array( 'id' => 'umc_checkout_mode_selected' ),
					__( 'Recommended', 'universal-multicurrency' )
				);
				echo $this->controls->choice_card(
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
				echo $this->controls->choice_cards_close();
				echo $this->controls->settings_card_close();

				echo $this->controls->settings_card_open(
					__( 'Information notice', 'universal-multicurrency' ),
					__( 'Display an informational notice when checkout currency changes.', 'universal-multicurrency' )
				);
				echo $this->controls->toggle_row(
					$notice_name,
					$checkout->show_notice(),
					__( 'Show an informational notice when checkout currency changes.', 'universal-multicurrency' ),
					__( 'Displays a customer-friendly notice whenever checkout switches to another currency.', 'universal-multicurrency' ),
					array( 'id' => 'umc_checkout_show_notice' )
				);
				echo $this->controls->settings_card_close();
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
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
