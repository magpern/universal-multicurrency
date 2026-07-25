<?php
/**
 * Immutable currency value object.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC;

use UMC\Exceptions\InvalidCurrencyCodeException;
use UMC\Exceptions\InvalidCurrencyException;

/**
 * An immutable currency: identity and display formatting only.
 *
 * A Currency never stores an exchange rate. Rates are configuration held in
 * {@see Settings} and resolved through a {@see \UMC\Rates\RateProvider}; they
 * are never a property of a currency.
 */
final class Currency {

	public const MAX_DECIMALS = 4;

	public const POSITIONS = array( 'left', 'right', 'left_space', 'right_space' );

	public const DEFAULT_DECIMALS = 2;

	public const DEFAULT_POSITION = 'left';

	/**
	 * Uppercase three-letter code, e.g. "SEK".
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Fraction digits, 0..MAX_DECIMALS.
	 *
	 * @var int
	 */
	private int $decimals;

	/**
	 * Display symbol; empty means "use the WooCommerce default at display time".
	 *
	 * @var string
	 */
	private string $symbol;

	/**
	 * Symbol position, one of self::POSITIONS.
	 *
	 * @var string
	 */
	private string $position;

	/**
	 * Whether the currency is offered to shoppers.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Validates and constructs. A Currency is always valid once constructed.
	 *
	 * @param string $code     Currency code; normalized to uppercase.
	 * @param int    $decimals Fraction digits, 0..MAX_DECIMALS.
	 * @param string $symbol   Display symbol; '' allowed.
	 * @param string $position One of self::POSITIONS.
	 * @param bool   $enabled  Whether the currency is offered to shoppers.
	 *
	 * @throws InvalidCurrencyCodeException When the code is not three uppercase letters.
	 * @throws InvalidCurrencyException When decimals are out of range or the position is unknown.
	 */
	public function __construct(
		string $code,
		int $decimals = self::DEFAULT_DECIMALS,
		string $symbol = '',
		string $position = self::DEFAULT_POSITION,
		bool $enabled = true
	) {
		$code = strtoupper( $code );

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $code ) ) {
			throw InvalidCurrencyCodeException::for_code( $code );
		}

		if ( $decimals < 0 || $decimals > self::MAX_DECIMALS ) {
			throw InvalidCurrencyException::for_decimals( $decimals, self::MAX_DECIMALS );
		}

		if ( ! in_array( $position, self::POSITIONS, true ) ) {
			throw InvalidCurrencyException::for_position( $position, implode( ', ', self::POSITIONS ) );
		}

		$this->code     = $code;
		$this->decimals = $decimals;
		$this->symbol   = $symbol;
		$this->position = $position;
		$this->enabled  = $enabled;
	}

	/**
	 * Builds a Currency from a settings-shaped config array (no 'code' key).
	 *
	 * Missing attributes fall back to defaults. Intended for sanitized config,
	 * where every value is already valid.
	 *
	 * @param string               $code   Currency code.
	 * @param array<string, mixed> $config Attributes: decimals, symbol, position, enabled.
	 */
	public static function from_array( string $code, array $config ): self {
		return new self(
			$code,
			isset( $config['decimals'] ) ? (int) $config['decimals'] : self::DEFAULT_DECIMALS,
			isset( $config['symbol'] ) ? (string) $config['symbol'] : '',
			isset( $config['position'] ) ? (string) $config['position'] : self::DEFAULT_POSITION,
			! isset( $config['enabled'] ) || (bool) $config['enabled']
		);
	}

	/**
	 * The uppercase currency code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * The number of fraction digits.
	 */
	public function decimals(): int {
		return $this->decimals;
	}

	/**
	 * The display symbol ('' means use the WooCommerce default).
	 */
	public function symbol(): string {
		return $this->symbol;
	}

	/**
	 * The symbol position.
	 */
	public function position(): string {
		return $this->position;
	}

	/**
	 * Whether the currency is offered to shoppers.
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Value equality across all attributes.
	 *
	 * @param Currency $other Currency to compare with.
	 */
	public function equals( Currency $other ): bool {
		return $this->code === $other->code
			&& $this->decimals === $other->decimals
			&& $this->symbol === $other->symbol
			&& $this->position === $other->position
			&& $this->enabled === $other->enabled;
	}

	/**
	 * The config-shaped representation (no 'code' key), mirroring from_array().
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'enabled'  => $this->enabled,
			'symbol'   => $this->symbol,
			'position' => $this->position,
			'decimals' => $this->decimals,
		);
	}
}
