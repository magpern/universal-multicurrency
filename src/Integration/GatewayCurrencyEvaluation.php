<?php
/**
 * Immutable gateway currency evaluation snapshot.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Integration;

/**
 * Structured classification of WooCommerce's pre-UMC gateway map.
 *
 * Pure and WordPress-free. Produced by {@see GatewayCurrencyClassifier} when
 * WooCommerce invokes the availability filter.
 */
final class GatewayCurrencyEvaluation {

	/**
	 * Currency code evaluated against.
	 *
	 * @var string
	 */
	private string $currency;

	/**
	 * Gateway ids WooCommerce passed into UMC before filtering.
	 *
	 * @var list<string>
	 */
	private array $before_umc_gateway_ids;

	/**
	 * Gateway ids retained after UMC filtering.
	 *
	 * @var list<string>
	 */
	private array $retained_gateway_ids;

	/**
	 * Gateway ids removed because they explicitly exclude the currency.
	 *
	 * @var list<string>
	 */
	private array $removed_for_currency_gateway_ids;

	/**
	 * Retained gateway ids with unknown currency support.
	 *
	 * @var list<string>
	 */
	private array $unknown_support_gateway_ids;

	/**
	 * Gateway ids present after UMC filtering.
	 *
	 * @var list<string>
	 */
	private array $after_umc_gateway_ids;

	/**
	 * Enabled gateways configured in the store.
	 *
	 * @var int
	 */
	private int $enabled_gateway_count;

	/**
	 * Whether UMC conclusively caused an empty gateway map.
	 *
	 * @var bool
	 */
	private bool $umc_caused_empty;

	/**
	 * Creates an evaluation snapshot.
	 *
	 * @param string             $currency                       Evaluated currency code.
	 * @param array<int, string> $before_umc_gateway_ids         Pre-UMC gateway ids.
	 * @param array<int, string> $retained_gateway_ids           Retained gateway ids.
	 * @param array<int, string> $removed_for_currency_gateway_ids Explicitly removed ids.
	 * @param array<int, string> $unknown_support_gateway_ids    Unknown-support ids.
	 * @param array<int, string> $after_umc_gateway_ids          Post-UMC gateway ids.
	 * @param int                $enabled_gateway_count          Enabled gateway count.
	 * @param bool               $umc_caused_empty               Derived causality flag.
	 */
	public function __construct(
		string $currency,
		array $before_umc_gateway_ids,
		array $retained_gateway_ids,
		array $removed_for_currency_gateway_ids,
		array $unknown_support_gateway_ids,
		array $after_umc_gateway_ids,
		int $enabled_gateway_count,
		bool $umc_caused_empty
	) {
		$this->currency                         = strtoupper( $currency );
		$this->before_umc_gateway_ids           = array_values( $before_umc_gateway_ids );
		$this->retained_gateway_ids             = array_values( $retained_gateway_ids );
		$this->removed_for_currency_gateway_ids = array_values( $removed_for_currency_gateway_ids );
		$this->unknown_support_gateway_ids      = array_values( $unknown_support_gateway_ids );
		$this->after_umc_gateway_ids            = array_values( $after_umc_gateway_ids );
		$this->enabled_gateway_count            = max( 0, $enabled_gateway_count );
		$this->umc_caused_empty                 = $umc_caused_empty;
	}

	/**
	 * The currency code evaluated against.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Gateway ids WooCommerce supplied before UMC filtering.
	 *
	 * @return list<string>
	 */
	public function before_umc_gateway_ids(): array {
		return $this->before_umc_gateway_ids;
	}

	/**
	 * Count of gateways WooCommerce supplied before UMC filtering.
	 */
	public function before_umc_count(): int {
		return count( $this->before_umc_gateway_ids );
	}

	/**
	 * Gateway ids retained by UMC (explicit support or unknown).
	 *
	 * @return list<string>
	 */
	public function retained_gateway_ids(): array {
		return $this->retained_gateway_ids;
	}

	/**
	 * Count of gateways retained by UMC.
	 */
	public function retained_count(): int {
		return count( $this->retained_gateway_ids );
	}

	/**
	 * Gateway ids removed because they explicitly exclude the currency.
	 *
	 * @return list<string>
	 */
	public function removed_for_currency_gateway_ids(): array {
		return $this->removed_for_currency_gateway_ids;
	}

	/**
	 * Count of gateways explicitly removed for currency.
	 */
	public function removed_for_currency_count(): int {
		return count( $this->removed_for_currency_gateway_ids );
	}

	/**
	 * Retained gateway ids with unknown currency support.
	 *
	 * @return list<string>
	 */
	public function unknown_support_gateway_ids(): array {
		return $this->unknown_support_gateway_ids;
	}

	/**
	 * Count of gateways with unknown currency support.
	 */
	public function unknown_support_count(): int {
		return count( $this->unknown_support_gateway_ids );
	}

	/**
	 * Gateway ids present after UMC filtering.
	 *
	 * @return list<string>
	 */
	public function after_umc_gateway_ids(): array {
		return $this->after_umc_gateway_ids;
	}

	/**
	 * Count of gateways after UMC filtering.
	 */
	public function after_umc_count(): int {
		return count( $this->after_umc_gateway_ids );
	}

	/**
	 * Enabled gateways configured in the store.
	 */
	public function enabled_gateway_count(): int {
		return $this->enabled_gateway_count;
	}

	/**
	 * Whether UMC conclusively caused the post-UMC gateway map to be empty.
	 */
	public function umc_caused_empty(): bool {
		return $this->umc_caused_empty;
	}
}
