<?php
/**
 * Structured currency decision explanation.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Decision;

use UMC\CurrencyResolutionResult;

/**
 * Immutable composed explanation for Decision Inspector and tests.
 */
final class CurrencyDecisionExplanation {

	/**
	 * Creates an explanation.
	 *
	 * @param string                       $display_currency   Browse/display currency.
	 * @param string|null                  $checkout_currency  Effective checkout currency, if evaluated.
	 * @param CurrencyResolutionResult     $shopper_resolution Shopper ladder result.
	 * @param string|null                  $currency_origin    Provenance metadata, if any.
	 * @param array<int, ExplanationStage> $stages             Ordered stages.
	 */
	public function __construct(
		private string $display_currency,
		private ?string $checkout_currency,
		private CurrencyResolutionResult $shopper_resolution,
		private ?string $currency_origin,
		private array $stages
	) {
		$this->display_currency  = strtoupper( $display_currency );
		$this->checkout_currency = is_string( $checkout_currency ) ? strtoupper( $checkout_currency ) : null;
	}

	/**
	 * Display/browse currency.
	 */
	public function display_currency(): string {
		return $this->display_currency;
	}

	/**
	 * Effective checkout currency, when checkout was explained.
	 */
	public function checkout_currency(): ?string {
		return $this->checkout_currency;
	}

	/**
	 * Shopper resolution result.
	 */
	public function shopper_resolution(): CurrencyResolutionResult {
		return $this->shopper_resolution;
	}

	/**
	 * Provenance origin metadata.
	 */
	public function currency_origin(): ?string {
		return $this->currency_origin;
	}

	/**
	 * Ordered stages.
	 *
	 * @return array<int, ExplanationStage>
	 */
	public function stages(): array {
		return $this->stages;
	}

	/**
	 * Array representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'display_currency'   => $this->display_currency,
			'checkout_currency'  => $this->checkout_currency,
			'currency_origin'    => $this->currency_origin,
			'shopper_resolution' => $this->shopper_resolution->to_array(),
			'stages'             => array_map(
				static fn( ExplanationStage $stage ): array => $stage->to_array(),
				$this->stages
			),
		);
	}
}
