<?php
/**
 * Presentation model for one expandable currency editor row.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

/**
 * Read-only view data for the expandable currency editor.
 */
final class CurrencyEditorViewModel {

	/**
	 * Presentation constructor.
	 *
	 * @param int    $index                   POST array index.
	 * @param string $code                    ISO currency code.
	 * @param string $name                    Display name.
	 * @param bool   $enabled                 Whether the currency is enabled.
	 * @param string $symbol                  Symbol override.
	 * @param string $position                Symbol position.
	 * @param int    $decimals                Decimal count.
	 * @param string $rate_mode               Stored per-currency rate mode override.
	 * @param string $manual_rate             Manual rate input value.
	 * @param string $provider_rate           Read-only provider rate.
	 * @param string $merchant_adjustment     Adjustment percentage.
	 * @param string $effective_rate_value    Effective rate value.
	 * @param string $effective_rate_source   Derivation label.
	 * @param bool   $show_manual_rate        Whether manual rate input is visible.
	 * @param bool   $show_adjustment         Whether adjustment input is visible.
	 * @param string $update_rate_url         Single-currency update URL.
	 * @param bool   $is_open                 Whether the editor should render expanded.
	 */
	public function __construct(
		public int $index,
		public string $code,
		public string $name,
		public bool $enabled,
		public string $symbol,
		public string $position,
		public int $decimals,
		public string $rate_mode,
		public string $manual_rate,
		public string $provider_rate,
		public string $merchant_adjustment,
		public string $effective_rate_value,
		public string $effective_rate_source,
		public bool $show_manual_rate,
		public bool $show_adjustment,
		public string $update_rate_url,
		public bool $is_open
	) {
	}
}
