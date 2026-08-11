<?php
/**
 * Composes storefront currency switcher labels.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Builds plain-text currency labels from configured content toggles.
 *
 * Structured markup is produced by {@see SwitcherElementComposer}; this class
 * renders the same ordered parts as text for accessible names and fallbacks.
 */
final class SwitcherLabelFormatter {

	/**
	 * Composer for the configured content context.
	 *
	 * @var SwitcherElementComposer
	 */
	private SwitcherElementComposer $composer;

	/**
	 * Composer for compact labels, which never include the currency name.
	 *
	 * @var SwitcherElementComposer
	 */
	private SwitcherElementComposer $compact_composer;

	/**
	 * Binds label formatting to content toggles and symbol disambiguation.
	 *
	 * @param array<string, mixed> $content           Content toggles and order.
	 * @param array<string, true>  $duplicate_symbols Duplicate symbol map.
	 */
	public function __construct( array $content, array $duplicate_symbols = array() ) {
		$this->composer = new SwitcherElementComposer( $content, $duplicate_symbols );

		$compact                = $content;
		$compact['show_name']   = false;
		$this->compact_composer = new SwitcherElementComposer( $compact, $duplicate_symbols );
	}

	/**
	 * Builds a list label for one currency option.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 */
	public function format( string $code, string $symbol, string $name ): string {
		return self::join( $this->composer->parts( $code, $symbol, $name ) );
	}

	/**
	 * Builds a compact label for the active trigger button.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 */
	public function format_compact( string $code, string $symbol, string $name ): string {
		return self::join( $this->compact_composer->parts( $code, $symbol, $name ) );
	}

	/**
	 * Detects duplicate symbols among selectable currencies.
	 *
	 * @param array<int, string> $symbols Symbol values keyed arbitrarily.
	 * @return array<string, true>
	 */
	public static function duplicate_symbol_map( array $symbols ): array {
		return SwitcherElementComposer::duplicate_symbol_map( $symbols );
	}

	/**
	 * Joins composed parts into a single label.
	 *
	 * The currency name is separated by an em dash when it follows another
	 * element, so long labels stay readable as plain text.
	 *
	 * @param array<int, array<string, string>> $parts Ordered content parts.
	 */
	private static function join( array $parts ): string {
		$label = '';

		foreach ( $parts as $index => $part ) {
			if ( 0 === $index ) {
				$label = $part['value'];
				continue;
			}

			$separator = SwitcherElementComposer::ELEMENT_NAME === $part['type'] ? ' — ' : ' ';
			$label    .= $separator . $part['value'];
		}

		return $label;
	}
}
