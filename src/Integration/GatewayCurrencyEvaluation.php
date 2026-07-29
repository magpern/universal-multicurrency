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
	 * @param string       $currency                       Evaluated currency code.
	 * @param list<string> $before_umc_gateway_ids         Pre-UMC gateway ids.
	 * @param list<string> $retained_gateway_ids           Retained gateway ids.
	 * @param list<string> $removed_for_currency_gateway_ids Explicitly removed ids.
	 * @param list<string> $unknown_support_gateway_ids    Unknown-support ids.
	 * @param list<string> $after_umc_gateway_ids          Post-UMC gateway ids.
	 * @param int          $enabled_gateway_count          Enabled gateway count.
	 * @param bool         $umc_caused_empty               Derived causality flag.
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
		$this->retained_gateway_ids               = array_values( $retained_gateway_ids );
		$this->removed_for_currency_gateway_ids   = array_values( $removed_for_currency_gateway_ids );
		$this->unknown_support_gateway_ids        = array_values( $unknown_support_gateway_ids );
		$this->after_umc_gateway_ids              = array_values( $after_umc_gateway_ids );
		$this->enabled_gateway_count              = max( 0, $enabled_gateway_count );
		$this->umc_caused_empty                   = $umc_caused_empty;
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
	public function beforeUmcGatewayIds(): array {
		return $this->before_umc_gateway_ids;
	}

	/**
	 * Count of gateways WooCommerce supplied before UMC filtering.
	 */
	public function beforeUmcCount(): int {
		return count( $this->before_umc_gateway_ids );
	}

	/**
	 * Gateway ids retained by UMC (explicit support or unknown).
	 *
	 * @return list<string>
	 */
	public function retainedGatewayIds(): array {
		return $this->retained_gateway_ids;
	}

	/**
	 * Count of gateways retained by UMC.
	 */
	public function retainedCount(): int {
		return count( $this->retained_gateway_ids );
	}

	/**
	 * Gateway ids removed because they explicitly exclude the currency.
	 *
	 * @return list<string>
	 */
	public function removedForCurrencyGatewayIds(): array {
		return $this->removed_for_currency_gateway_ids;
	}

	/**
	 * Count of gateways explicitly removed for currency.
	 */
	public function removedForCurrencyCount(): int {
		return count( $this->removed_for_currency_gateway_ids );
	}

	/**
	 * Retained gateway ids with unknown currency support.
	 *
	 * @return list<string>
	 */
	public function unknownSupportGatewayIds(): array {
		return $this->unknown_support_gateway_ids;
	}

	/**
	 * Count of gateways with unknown currency support.
	 */
	public function unknownSupportCount(): int {
		return count( $this->unknown_support_gateway_ids );
	}

	/**
	 * Gateway ids present after UMC filtering.
	 *
	 * @return list<string>
	 */
	public function afterUmcGatewayIds(): array {
		return $this->after_umc_gateway_ids;
	}

	/**
	 * Count of gateways after UMC filtering.
	 */
	public function afterUmcCount(): int {
		return count( $this->after_umc_gateway_ids );
	}

	/**
	 * Enabled gateways configured in the store.
	 */
	public function enabledGatewayCount(): int {
		return $this->enabled_gateway_count;
	}

	/**
	 * Whether UMC conclusively caused the post-UMC gateway map to be empty.
	 */
	public function umcCausedEmpty(): bool {
		return $this->umc_caused_empty;
	}
}
