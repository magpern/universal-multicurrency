<?php
/**
 * Composes structured switcher content elements.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Builds ordered code/symbol/name elements for one switcher context.
 *
 * A composer instance is bound to a single content context (trigger or menu),
 * so trigger and menu presentation are configured independently.
 */
final class SwitcherElementComposer {

	public const ELEMENT_CODE = 'code';

	public const ELEMENT_SYMBOL = 'symbol';

	public const ELEMENT_NAME = 'name';

	/**
	 * Element order applied when no valid merchant order is configured.
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_ORDER = array( self::ELEMENT_CODE, self::ELEMENT_SYMBOL, self::ELEMENT_NAME );

	/**
	 * Element visibility toggles.
	 *
	 * @var array<string, bool>
	 */
	private array $visible;

	/**
	 * Element order.
	 *
	 * @var array<int, string>
	 */
	private array $order;

	/**
	 * Currency symbols that appear more than once among selectable currencies.
	 *
	 * @var array<string, true>
	 */
	private array $duplicate_symbols;

	/**
	 * Binds composition to one content context and symbol disambiguation.
	 *
	 * @param array<string, mixed> $content           Content context toggles and order.
	 * @param array<string, true>  $duplicate_symbols Duplicate symbol map.
	 */
	public function __construct( array $content, array $duplicate_symbols = array() ) {
		$this->visible = array(
			self::ELEMENT_CODE   => ! empty( $content['show_code'] ),
			self::ELEMENT_SYMBOL => ! empty( $content['show_symbol'] ),
			self::ELEMENT_NAME   => ! empty( $content['show_name'] ),
		);

		$this->order             = self::normalize_order( $content['order'] ?? array() );
		$this->duplicate_symbols = $duplicate_symbols;
	}

	/**
	 * Normalizes a merchant element order to the known element set.
	 *
	 * Unknown entries are dropped and missing elements are appended in default
	 * order, so the composer always knows a position for every element.
	 *
	 * @param mixed $raw Raw order value.
	 * @return array<int, string>
	 */
	public static function normalize_order( mixed $raw ): array {
		$order = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $element ) {
				if ( ! is_string( $element ) ) {
					continue;
				}

				if ( ! in_array( $element, self::DEFAULT_ORDER, true ) ) {
					continue;
				}

				if ( in_array( $element, $order, true ) ) {
					continue;
				}

				$order[] = $element;
			}
		}

		foreach ( self::DEFAULT_ORDER as $element ) {
			if ( ! in_array( $element, $order, true ) ) {
				$order[] = $element;
			}
		}

		return $order;
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
	 * Composes ordered content parts for one currency.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 * @return array<int, array<string, string>>
	 */
	public function parts( string $code, string $symbol, string $name ): array {
		$values = array(
			self::ELEMENT_CODE   => $code,
			self::ELEMENT_SYMBOL => $symbol,
			self::ELEMENT_NAME   => $name,
		);

		$included = array(
			self::ELEMENT_CODE   => $this->visible[ self::ELEMENT_CODE ],
			self::ELEMENT_SYMBOL => $this->visible[ self::ELEMENT_SYMBOL ] && '' !== $symbol,
			self::ELEMENT_NAME   => $this->visible[ self::ELEMENT_NAME ] && '' !== $name,
		);

		$parts = array();

		foreach ( $this->order as $element ) {
			if ( ! $included[ $element ] ) {
				continue;
			}

			$parts[] = array(
				'type'  => $element,
				'value' => $values[ $element ],
			);
		}

		// A code the merchant hid has no configured position, so it leads.
		if ( $this->needs_code_for_disambiguation( $included, $symbol ) ) {
			array_unshift(
				$parts,
				array(
					'type'  => self::ELEMENT_CODE,
					'value' => $code,
				)
			);
		}

		if ( array() === $parts ) {
			$parts[] = array(
				'type'  => self::ELEMENT_CODE,
				'value' => $code,
			);
		}

		return $parts;
	}

	/**
	 * Composes escaped element markup for one currency.
	 *
	 * @param string $code   Currency code.
	 * @param string $symbol Display symbol.
	 * @param string $name   Currency name.
	 */
	public function html( string $code, string $symbol, string $name ): string {
		$html = '';

		foreach ( $this->parts( $code, $symbol, $name ) as $part ) {
			$html .= sprintf(
				'<span class="umc-switcher__%1$s">%2$s</span>',
				esc_attr( $part['type'] ),
				esc_html( $part['value'] )
			);
		}

		return $html;
	}

	/**
	 * Whether the currency code must be shown to disambiguate duplicate symbols.
	 *
	 * @param array<string, bool> $included Element inclusion map.
	 * @param string              $symbol   Display symbol.
	 */
	private function needs_code_for_disambiguation( array $included, string $symbol ): bool {
		if ( ! $included[ self::ELEMENT_SYMBOL ] ) {
			return false;
		}

		if ( $included[ self::ELEMENT_CODE ] ) {
			return false;
		}

		return isset( $this->duplicate_symbols[ $symbol ] );
	}
}
