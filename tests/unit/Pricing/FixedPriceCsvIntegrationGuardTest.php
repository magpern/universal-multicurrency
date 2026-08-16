<?php
/**
 * M25 WP7: architecture guards for fixed pricing CSV interchange.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Executable architecture invariants for ADR-0030, enforced without loading
 * WordPress — the same static-source-scan discipline
 * {@see \UMC\Tests\Unit\Pricing\FixedPricingCatalogOperationsGuardTest} already
 * uses for M24.
 */
final class FixedPriceCsvIntegrationGuardTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * The M25 CSV interchange files this guard suite covers.
	 *
	 * @var array<int, string>
	 */
	private const M25_CSV_FILES = array(
		'src/Pricing/FixedPriceCsvIntegration.php',
		'src/Pricing/FixedPriceDocumentMerger.php',
	);

	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * @return array<int, string>
	 */
	private function m25_csv_files(): array {
		return array_map(
			fn( string $relative ): string => $this->root() . '/' . $relative,
			self::M25_CSV_FILES
		);
	}

	/**
	 * CSV import/export is authoring and presentation of already-authored
	 * data, never conversion (ADR-0030 § FX exclusion). Neither direction may
	 * derive a value from an exchange rate — the CSV cell is either the
	 * merchant's authored figure or nothing at all.
	 */
	public function test_m25_csv_files_never_reference_fx_conversion_authorities(): void {
		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bRateProvider\b/',
			'M25 CSV files must never reference RateProvider — CSV import/export authors ' .
			'and presents already-authored fixed prices, it never derives a value from an ' .
			'exchange rate.'
		);

		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bPriceConversionService\b/',
			'M25 CSV files must never reference PriceConversionService — the same ' .
			'FX-exclusion invariant as RateProvider.'
		);

		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bDisplayPriceConverter\b/',
			'M25 CSV files must never reference DisplayPriceConverter — the seam M24\'s ' .
			'FX-derived seed() uses, which has no place in explicitly-authored CSV interchange.'
		);
	}

	/**
	 * CSV import/export must never couple to storefront price resolution —
	 * it authors the fixed-price document, it never resolves or intercepts
	 * an active display price.
	 */
	public function test_m25_csv_files_have_no_storefront_price_resolution_coupling(): void {
		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bPriceHooks\b/',
			'M25 CSV files must never couple to PriceHooks — this is an admin-side bulk ' .
			'authoring path, not a storefront price-resolution path.'
		);

		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bProductPriceResolutionService\b/',
			'M25 CSV files must never couple to ProductPriceResolutionService — import/export ' .
			'reads and writes the authored document directly, it does not resolve an active price.'
		);
	}

	/**
	 * M25 introduces no live external HTTP anywhere — the entire feature is
	 * local CSV I/O plus already-persisted-document reads/writes.
	 */
	public function test_m25_csv_files_perform_no_http(): void {
		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\b(wp_remote_get|wp_remote_post|wp_remote_request|curl_exec|curl_init|fsockopen|stream_socket_client)\s*\(/',
			'M25 CSV files must never perform live external HTTP.'
		);
	}

	/**
	 * CSV interchange must never read or mutate order or reporting data —
	 * it is exclusively a product/variation fixed-price authoring surface.
	 */
	public function test_m25_csv_files_have_no_order_or_reporting_coupling(): void {
		$this->assert_pattern_absent_from(
			$this->m25_csv_files(),
			'/\bWC_Order\b|\bOrderSnapshot\b|\bRefundSnapshot\b|\bReportingService\b|\bOrderReportingRepository\b|wc_get_order(s)?\s*\(/',
			'M25 CSV files must never read or mutate order or reporting data.'
		);
	}
}
