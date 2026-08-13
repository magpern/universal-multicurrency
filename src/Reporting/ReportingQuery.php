<?php
/**
 * Immutable reporting query specification.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

use UMC\CurrencySwitcher;

/**
 * Immutable reporting query specification.
 */
final class ReportingQuery {

	/**
	 * Builds a reporting query from normalized filter inputs.
	 *
	 * @param ReportingDateRange $range                Inclusive date bounds.
	 * @param array<int, string> $statuses             WooCommerce order statuses.
	 * @param string             $transaction_currency Optional currency filter.
	 * @param string             $origin               Optional origin filter.
	 * @param string             $fallback             Optional fallback filter.
	 * @param string             $pricing_source       Optional pricing source filter.
	 */
	public function __construct(
		private ReportingDateRange $range,
		private array $statuses,
		private string $transaction_currency = '',
		private string $origin = '',
		private string $fallback = '',
		private string $pricing_source = ''
	) {
		$this->statuses = array_values( array_unique( array_filter( $statuses ) ) );
	}

	/**
	 * Inclusive date bounds.
	 */
	public function range(): ReportingDateRange {
		return $this->range;
	}

	/**
	 * Selected WooCommerce order statuses.
	 *
	 * @return list<string>
	 */
	public function statuses(): array {
		return $this->statuses;
	}

	/**
	 * Optional transaction currency filter.
	 */
	public function transaction_currency(): string {
		return $this->transaction_currency;
	}

	/**
	 * Optional currency origin filter.
	 */
	public function origin(): string {
		return $this->origin;
	}

	/**
	 * Optional checkout fallback filter.
	 */
	public function fallback(): string {
		return $this->fallback;
	}

	/**
	 * Optional pricing source filter.
	 */
	public function pricing_source(): string {
		return $this->pricing_source;
	}

	/**
	 * Builds a query from raw admin request input.
	 *
	 * @param array<string, mixed> $input Raw request input.
	 */
	public static function from_input( array $input ): self {
		$range = ReportingDateRange::from_input( $input );

		$statuses = array();
		if ( isset( $input['statuses'] ) && is_array( $input['statuses'] ) ) {
			foreach ( $input['statuses'] as $status ) {
				$status = sanitize_key( (string) $status );
				if ( in_array( $status, ReportingConstants::selectable_statuses(), true ) ) {
					$statuses[] = $status;
				}
			}
		}

		if ( array() === $statuses ) {
			$statuses = ReportingConstants::default_statuses();
		}

		$currency = strtoupper( sanitize_text_field( (string) ( $input['currency'] ?? '' ) ) );
		$origin   = sanitize_key( (string) ( $input['origin'] ?? '' ) );
		$fallback = sanitize_key( (string) ( $input['fallback'] ?? '' ) );
		$source   = sanitize_key( (string) ( $input['pricing_source'] ?? '' ) );

		if ( ! in_array( $origin, array( '', CurrencySwitcher::ORIGIN_CUSTOMER, CurrencySwitcher::ORIGIN_VISITOR_LOCATION, ReportingConstants::ORIGIN_UNKNOWN ), true ) ) {
			$origin = '';
		}

		if ( ! in_array( $fallback, array( '', 'yes', 'no' ), true ) ) {
			$fallback = '';
		}

		if ( ! in_array( $source, array( '', ReportingConstants::SOURCE_FIXED, ReportingConstants::SOURCE_CONVERTED, ReportingConstants::SOURCE_UNKNOWN ), true ) ) {
			$source = '';
		}

		return new self( $range, $statuses, $currency, $origin, $fallback, $source );
	}
}
