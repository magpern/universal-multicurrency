<?php
/**
 * Presentation model for one currency overview row.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

/**
 * Read-only view data for the currency overview table.
 */
final class CurrencyViewModel {

	/**
	 * Presentation constructor.
	 *
	 * @param bool   $is_base                 Whether this is the store base currency row.
	 * @param bool   $enabled                 Whether the currency is enabled.
	 * @param string $code                    ISO currency code.
	 * @param string $name                    Display name.
	 * @param string $symbol                  Effective symbol.
	 * @param string $mode_label              Rate mode label for the Mode column.
	 * @param string $effective_rate_value    Effective rate value or em dash.
	 * @param string $effective_rate_source   Derivation label for the effective rate.
	 * @param string $adjustment_label        Adjustment column value.
	 * @param string $status_label            Localized status label.
	 * @param string $status_class            Badge CSS class suffix.
	 * @param string $updated_label           Last updated label.
	 * @param string $edit_anchor             DOM id for the editor row.
	 * @param string $toggle_url              Enable/disable admin-post URL.
	 * @param string $toggle_label            Enable/disable action label.
	 * @param string $remove_url              Remove admin-post URL.
	 * @param string $update_rate_url         Single-currency rate update URL.
	 * @param bool   $can_update_rate         Whether update-rate action is available.
	 * @param string $general_settings_url    WooCommerce general settings URL for base row.
	 */
	public function __construct(
		public bool $is_base,
		public bool $enabled,
		public string $code,
		public string $name,
		public string $symbol,
		public string $mode_label,
		public string $effective_rate_value,
		public string $effective_rate_source,
		public string $adjustment_label,
		public string $status_label,
		public string $status_class,
		public string $updated_label,
		public string $edit_anchor,
		public string $toggle_url,
		public string $toggle_label,
		public string $remove_url,
		public string $update_rate_url,
		public bool $can_update_rate,
		public string $general_settings_url
	) {
	}
}
