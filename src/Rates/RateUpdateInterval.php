<?php
/**
 * Closed-set ISO-8601 update interval value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

/**
 * Wraps the five supported recurring update intervals.
 */
final class RateUpdateInterval {

	/** @var array<string, int> */
	private const DURATIONS = array(
		'PT6H'  => 21600,
		'PT12H' => 43200,
		'P1D'   => 86400,
		'P3D'   => 259200,
		'P1W'   => 604800,
	);

	private string $iso8601;

	private function __construct( string $iso8601 ) {
		$this->iso8601 = $iso8601;
	}

	public static function default(): self {
		return new self( 'P1D' );
	}

	public static function from_iso8601( string $value ): ?self {
		$value = strtoupper( trim( $value ) );

		if ( ! isset( self::DURATIONS[ $value ] ) ) {
			return null;
		}

		return new self( $value );
	}

	/**
	 * @return self[]
	 */
	public static function options(): array {
		$options = array();

		foreach ( array_keys( self::DURATIONS ) as $iso ) {
			$options[] = new self( $iso );
		}

		return $options;
	}

	public function iso8601(): string {
		return $this->iso8601;
	}

	public function seconds(): int {
		return self::DURATIONS[ $this->iso8601 ];
	}

	public function label(): string {
		return match ( $this->iso8601 ) {
			'PT6H'  => 'Every 6 hours',
			'PT12H' => 'Every 12 hours',
			'P1D'   => 'Daily',
			'P3D'   => 'Every 3 days',
			'P1W'   => 'Weekly',
			default => $this->iso8601,
		};
	}
}
