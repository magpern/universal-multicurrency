<?php
/**
 * Builds switcher option view models for one render pass.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Applies the trigger and menu content contexts to every currency option.
 */
final class SwitcherOptionFactory {

	/**
	 * Element composer for the trigger context.
	 *
	 * @var SwitcherElementComposer
	 */
	private SwitcherElementComposer $trigger_composer;

	/**
	 * Element composer for the menu context.
	 *
	 * @var SwitcherElementComposer
	 */
	private SwitcherElementComposer $menu_composer;

	/**
	 * Plain-text formatter for the trigger context.
	 *
	 * @var SwitcherLabelFormatter
	 */
	private SwitcherLabelFormatter $trigger_formatter;

	/**
	 * Plain-text formatter for the menu context.
	 *
	 * @var SwitcherLabelFormatter
	 */
	private SwitcherLabelFormatter $menu_formatter;

	/**
	 * Resolves both content contexts once per render pass.
	 *
	 * @param SwitcherSettings    $settings          Display settings.
	 * @param array<string, true> $duplicate_symbols Duplicate symbol map.
	 */
	public function __construct( SwitcherSettings $settings, array $duplicate_symbols = array() ) {
		$trigger      = $settings->trigger_content();
		$menu         = $settings->menu_content();
		$presentation = CurrencyPresentationResolver::from_settings( $settings );

		$this->trigger_composer  = new SwitcherElementComposer( $trigger, $duplicate_symbols, $presentation );
		$this->menu_composer     = new SwitcherElementComposer( $menu, $duplicate_symbols, $presentation );
		$this->trigger_formatter = new SwitcherLabelFormatter( $trigger, $duplicate_symbols );
		$this->menu_formatter    = new SwitcherLabelFormatter( $menu, $duplicate_symbols );
	}

	/**
	 * Builds one option with structured markup and plain-text labels.
	 *
	 * @param string $code      Currency code.
	 * @param string $symbol    Display symbol.
	 * @param string $name      Currency name.
	 * @param string $url       Switch URL.
	 * @param bool   $is_active Whether the option is active.
	 */
	public function create( string $code, string $symbol, string $name, string $url, bool $is_active ): SwitcherOptionViewModel {
		return new SwitcherOptionViewModel(
			$code,
			$this->menu_formatter->format( $code, $symbol, $name ),
			$this->trigger_formatter->format( $code, $symbol, $name ),
			$url,
			$is_active,
			$this->menu_composer->html( $code, $symbol, $name ),
			$this->trigger_composer->html( $code, $symbol, $name )
		);
	}
}
