<?php
/**
 * Presentation model for the currencies admin section.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

/**
 * Container for overview rows, editor rows, and add-currency options.
 */
final class CurrencyOverviewViewModel {

	/**
	 * Presentation constructor.
	 *
	 * @param array<int, CurrencyViewModel>       $rows         Overview rows including base currency first.
	 * @param array<int, CurrencyEditorViewModel> $editors      Expandable editor rows.
	 * @param array<string, string>               $add_options  Select options keyed by ISO code.
	 * @param string                              $add_url      Admin-post URL for adding a currency.
	 * @param string                              $open_editor  Currency code whose editor should open.
	 */
	public function __construct(
		public array $rows,
		public array $editors,
		public array $add_options,
		public string $add_url,
		public string $open_editor
	) {
	}
}
