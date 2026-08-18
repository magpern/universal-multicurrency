<?php
/**
 * Deterministic cache-critical state factors.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\CacheState;

/**
 * Immutable value object over the exact four factors an external full-page
 * cache would need to key on. Pure PHP, no WordPress — see ADR-0032 and
 * docs/architecture/external-cache-state-readiness.md for the contract this
 * class implements.
 */
final class CacheStateFactors {

	/**
	 * Bump only when the set of hashed factors itself changes.
	 */
	public const CONTRACT_VERSION = 1;

	/**
	 * Base currency code, uppercase ISO-4217.
	 *
	 * @var string
	 */
	private string $base_currency;

	/**
	 * Selectable currency codes, uppercase, unique, sorted.
	 *
	 * @var list<string>
	 */
	private array $currencies;

	/**
	 * Whether geo-based currency routing is enabled.
	 *
	 * @var bool
	 */
	private bool $geo_enabled;

	/**
	 * Builds the factor set. Currency codes are normalized (uppercase,
	 * unique, sorted) so input order and case never perturb the hash.
	 *
	 * @param string             $base_currency Base currency code.
	 * @param array<int, string> $currencies    Selectable currency codes.
	 * @param bool               $geo_enabled   Whether geo routing is enabled.
	 */
	public function __construct( string $base_currency, array $currencies, bool $geo_enabled ) {
		$this->base_currency = strtoupper( trim( $base_currency ) );

		$normalized = array_values(
			array_unique(
				array_map(
					static fn( string $code ): string => strtoupper( trim( $code ) ),
					$currencies
				)
			)
		);
		sort( $normalized, SORT_STRING );
		$this->currencies = $normalized;

		$this->geo_enabled = $geo_enabled;
	}

	/**
	 * Base currency code.
	 */
	public function base_currency(): string {
		return $this->base_currency;
	}

	/**
	 * Selectable currency codes, normalized.
	 *
	 * @return list<string>
	 */
	public function currencies(): array {
		return $this->currencies;
	}

	/**
	 * Whether geo-based currency routing is enabled.
	 */
	public function geo_enabled(): bool {
		return $this->geo_enabled;
	}

	/**
	 * Canonical, human-readable serialization. Contains no timestamp.
	 */
	public function canonical_string(): string {
		return sprintf(
			'umc-cache-state/v%d|base=%s|currencies=%s|geo=%d',
			self::CONTRACT_VERSION,
			$this->base_currency,
			implode( ',', $this->currencies ),
			$this->geo_enabled ? 1 : 0
		);
	}

	/**
	 * Deterministic 16-lowercase-hex hash of the canonical string.
	 *
	 * Opaque to external infrastructure: compared byte-for-byte, never
	 * recomputed by the consumer.
	 */
	public function hash(): string {
		return substr( hash( 'sha256', $this->canonical_string() ), 0, 16 );
	}

	/**
	 * Flat array form for report/debug consumers.
	 *
	 * @return array{contract_version: int, base_currency: string, currencies: list<string>, geo_enabled: bool}
	 */
	public function to_array(): array {
		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'base_currency'    => $this->base_currency,
			'currencies'       => $this->currencies,
			'geo_enabled'      => $this->geo_enabled,
		);
	}
}
