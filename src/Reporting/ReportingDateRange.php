<?php
/**
 * Immutable reporting date range.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Site-timezone date bounds for order queries (inclusive start/end days).
 */
final class ReportingDateRange {

	public const PRESET_7_DAYS  = '7d';
	public const PRESET_30_DAYS = '30d';
	public const PRESET_90_DAYS = '90d';
	public const PRESET_YTD     = 'ytd';
	public const PRESET_CUSTOM  = 'custom';

	/**
	 * Inclusive range start in site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $start;

	/**
	 * Inclusive range end in site timezone.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $end;

	/**
	 * Active preset identifier.
	 *
	 * @var string
	 */
	private string $preset;

	/**
	 * Captures inclusive reporting date bounds.
	 *
	 * @param DateTimeImmutable $start  Inclusive range start.
	 * @param DateTimeImmutable $end    Inclusive range end.
	 * @param string            $preset Preset identifier.
	 */
	public function __construct( DateTimeImmutable $start, DateTimeImmutable $end, string $preset ) {
		$this->start  = $start;
		$this->end    = $end;
		$this->preset = $preset;
	}

	/**
	 * Inclusive range start in site timezone.
	 */
	public function start(): DateTimeImmutable {
		return $this->start;
	}

	/**
	 * Inclusive range end in site timezone.
	 */
	public function end(): DateTimeImmutable {
		return $this->end;
	}

	/**
	 * Active preset identifier.
	 */
	public function preset(): string {
		return $this->preset;
	}

	/**
	 * Builds a date range from raw admin request input.
	 *
	 * @param array<string, mixed> $input Raw request input.
	 */
	public static function from_input( array $input ): self {
		$preset = sanitize_key( (string) ( $input['preset'] ?? self::PRESET_30_DAYS ) );
		$zone   = wp_timezone();

		if ( self::PRESET_CUSTOM === $preset ) {
			$start_raw = sanitize_text_field( (string) ( $input['start'] ?? '' ) );
			$end_raw   = sanitize_text_field( (string) ( $input['end'] ?? '' ) );

			$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $start_raw . ' 00:00:00', $zone );
			$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $end_raw . ' 23:59:59', $zone );

			if ( false === $start || false === $end || $start > $end ) {
				return self::for_preset( self::PRESET_30_DAYS );
			}

			return new self( $start, $end, self::PRESET_CUSTOM );
		}

		return self::for_preset( $preset );
	}

	/**
	 * Builds a preset date range in site timezone.
	 *
	 * @param string $preset Preset identifier.
	 */
	public static function for_preset( string $preset ): self {
		$zone = wp_timezone();
		$now  = new DateTimeImmutable( 'now', $zone );
		$end  = $now->setTime( 23, 59, 59 );

		switch ( $preset ) {
			case self::PRESET_7_DAYS:
				$start = $now->modify( '-6 days' )->setTime( 0, 0, 0 );
				break;
			case self::PRESET_90_DAYS:
				$start = $now->modify( '-89 days' )->setTime( 0, 0, 0 );
				break;
			case self::PRESET_YTD:
				$start = new DateTimeImmutable( $now->format( 'Y-01-01 00:00:00' ), $zone );
				break;
			case self::PRESET_30_DAYS:
			default:
				$preset = self::PRESET_30_DAYS;
				$start  = $now->modify( '-29 days' )->setTime( 0, 0, 0 );
				break;
		}

		return new self( $start, $end, $preset );
	}

	/**
	 * WooCommerce order query bounds (UTC epoch range, inclusive).
	 */
	public function wc_date_query(): string {
		return $this->start->getTimestamp() . '...' . $this->end->getTimestamp();
	}
}
