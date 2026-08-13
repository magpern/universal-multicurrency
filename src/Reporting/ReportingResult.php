<?php
/**
 * Immutable aggregate reporting result.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Reporting;

/**
 * Immutable aggregate reporting result.
 */
final class ReportingResult {

	/**
	 * Captures one fully aggregated reporting result.
	 *
	 * @param ReportingQuery             $query                 Query that produced this result.
	 * @param CurrencyPerformanceReport  $currency_performance  Per-currency totals.
	 * @param PricingSourceReport        $pricing_source        Product-line pricing totals.
	 * @param OriginReport               $origin                Currency origin counts.
	 * @param CheckoutFallbackReport     $checkout_fallback     Checkout fallback counts.
	 * @param RateProvenanceReport       $rate_provenance       Rate source counts.
	 * @param ReportingStatisticsSummary $statistics            Summary card metrics.
	 * @param ReportingDiagnostics       $diagnostics           Data-quality counters.
	 * @param int                        $repository_load_count Orders loaded from storage.
	 */
	public function __construct(
		private ReportingQuery $query,
		private CurrencyPerformanceReport $currency_performance,
		private PricingSourceReport $pricing_source,
		private OriginReport $origin,
		private CheckoutFallbackReport $checkout_fallback,
		private RateProvenanceReport $rate_provenance,
		private ReportingStatisticsSummary $statistics,
		private ReportingDiagnostics $diagnostics,
		private int $repository_load_count
	) {
	}

	/**
	 * Query that produced this result.
	 */
	public function query(): ReportingQuery {
		return $this->query;
	}

	/**
	 * Per-currency performance report.
	 */
	public function currency_performance(): CurrencyPerformanceReport {
		return $this->currency_performance;
	}

	/**
	 * Product-line pricing source totals.
	 */
	public function pricing_source(): PricingSourceReport {
		return $this->pricing_source;
	}

	/**
	 * Currency origin counts.
	 */
	public function origin(): OriginReport {
		return $this->origin;
	}

	/**
	 * Checkout fallback summary.
	 */
	public function checkout_fallback(): CheckoutFallbackReport {
		return $this->checkout_fallback;
	}

	/**
	 * Rate provenance counts.
	 */
	public function rate_provenance(): RateProvenanceReport {
		return $this->rate_provenance;
	}

	/**
	 * Summary card metrics.
	 */
	public function statistics(): ReportingStatisticsSummary {
		return $this->statistics;
	}

	/**
	 * Data-quality diagnostics.
	 */
	public function diagnostics(): ReportingDiagnostics {
		return $this->diagnostics;
	}

	/**
	 * Number of orders loaded from storage.
	 */
	public function repository_load_count(): int {
		return $this->repository_load_count;
	}
}
