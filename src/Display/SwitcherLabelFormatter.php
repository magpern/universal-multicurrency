<?php
/**
 * Composes storefront currency switcher labels.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Builds human-readable currency labels from configured content toggles.
 */
final class SwitcherLabelFormatter {

	/**
	 * Content visibility toggles.
	 *
	 * @var array<string, bool>
	 */
	private array $content;

	/**
	 * Currency symbols that appear more than once among selectable currencies.
	 *
	 * @var array<string, true>
	 */
	private array $duplicate_symbols;

	/**
	 * Binds label formatting to content toggles and symbol disambiguation.
	 *
	 * @param array<string, bool> $content           Content toggles.
	 * @param array<string, true> $duplicate_symbols Duplicate symbol map.
	 */
	public function __construct( array $content, array $duplicate_symbols = array() ) {
		$this->content           = $content;
		$this->duplicate_symbols = $duplicate_symbols;
	}

	/**
	 * Builds a list label for one currency option.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 */
	public function format( string $code, string $symbol, string $name ): string {
		return $this->compose( $code, $symbol, $name, false );
	}

	/**
	 * Builds a compact label for the active trigger button.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 */
	public function format_compact( string $code, string $symbol, string $name ): string {
		return $this->compose( $code, $symbol, $name, true );
	}

	/**
	 * Detects duplicate symbols among selectable currencies.
	 *
	 * @param array<int, string> $symbols Symbol values keyed arbitrarily.
	 * @return array<string, true>
	 */
	public static function duplicate_symbol_map( array $symbols ): array {
		$counts = array();

		foreach ( $symbols as $symbol ) {
			if ( '' === $symbol ) {
				continue;
			}

			$counts[ $symbol ] = ( $counts[ $symbol ] ?? 0 ) + 1;
		}

		$duplicates = array();

		foreach ( $counts as $symbol => $count ) {
			if ( $count > 1 ) {
				$duplicates[ $symbol ] = true;
			}
		}

		return $duplicates;
	}

	/**
	 * Composes a label from configured visibility toggles.
	 *
	 * @param string $code    Currency code.
	 * @param string $symbol  Display symbol.
	 * @param string $name    Currency name.
	 * @param bool   $compact Whether to omit the name in compact mode.
	 */
	private function compose( string $code, string $symbol, string $name, bool $compact ): string {
		$parts = array();

		if ( $this->content['show_code'] ) {
			$parts[] = $code;
		}

		if ( $this->content['show_symbol'] && '' !== $symbol ) {
			$parts[] = $symbol;
		}

		if ( $this->needs_code_for_disambiguation( $code, $symbol ) && ! in_array( $code, $parts, true ) ) {
			array_unshift( $parts, $code );
		}

		if ( array() === $parts ) {
			if ( $this->content['show_name'] && '' !== $name ) {
				return $name;
			}

			$parts[] = $code;
		}

		$primary = implode( ' ', $parts );

		if ( ! $compact && $this->content['show_name'] && '' !== $name ) {
			return $primary . ' — ' . $name;
		}

		return $primary;
	}

	/**
	 * Whether the currency code must be shown to disambiguate duplicate symbols.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 */
	private function needs_code_for_disambiguation( string $code, string $symbol ): bool {
		if ( ! $this->content['show_symbol'] || '' === $symbol ) {
			return false;
		}

		if ( $this->content['show_code'] ) {
			return false;
		}

		return isset( $this->duplicate_symbols[ $symbol ] );
	}
}
