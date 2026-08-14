<?php
/**
 * Resolves currency codes to bundled presentation assets.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Applies merchant overrides and runtime built-in defaults.
 */
final class CurrencyPresentationResolver {

	/**
	 * Non-persisted built-in currency → presentation-region defaults.
	 *
	 * @var array<string, string>
	 */
	private const BUILTIN_DEFAULTS = array(
		'SEK' => CurrencyPresentationAssetRegistry::REGION_SE,
		'DKK' => CurrencyPresentationAssetRegistry::REGION_DK,
		'NOK' => CurrencyPresentationAssetRegistry::REGION_NO,
		'PLN' => CurrencyPresentationAssetRegistry::REGION_PL,
		'GBP' => CurrencyPresentationAssetRegistry::REGION_GB,
		'EUR' => CurrencyPresentationAssetRegistry::REGION_EU,
		'USD' => CurrencyPresentationAssetRegistry::REGION_US,
		'CHF' => CurrencyPresentationAssetRegistry::REGION_CH,
	);

	/**
	 * Merchant overrides keyed by currency code.
	 *
	 * @var array<string, string>
	 */
	private array $overrides;

	/**
	 * Creates a resolver from sanitized merchant overrides.
	 *
	 * @param array<string, string> $overrides Sanitized merchant overrides.
	 */
	public function __construct( array $overrides = array() ) {
		$this->overrides = $overrides;
	}

	/**
	 * Builds a resolver from normalized switcher settings.
	 *
	 * @param SwitcherSettings $settings Normalized switcher settings.
	 */
	public static function from_settings( SwitcherSettings $settings ): self {
		return new self( $settings->icon_overrides() );
	}

	/**
	 * Built-in default region for one currency, if any.
	 *
	 * @param string $code Currency code.
	 */
	public static function built_in_region_for_currency( string $code ): ?string {
		$code = strtoupper( trim( $code ) );

		return self::BUILTIN_DEFAULTS[ $code ] ?? null;
	}

	/**
	 * Effective presentation-region identifier for one currency.
	 *
	 * @param string $code Currency code.
	 */
	public function region_for_currency( string $code ): ?string {
		$code   = strtoupper( trim( $code ) );
		$region = $this->overrides[ $code ] ?? self::BUILTIN_DEFAULTS[ $code ] ?? null;

		if ( null === $region || ! CurrencyPresentationAssetRegistry::is_valid_region( $region ) ) {
			return null;
		}

		return strtoupper( $region );
	}

	/**
	 * Bundled asset URL for one currency, when resolvable.
	 *
	 * @param string $code Currency code.
	 */
	public function asset_url_for_currency( string $code ): ?string {
		$region = $this->region_for_currency( $code );

		if ( null === $region ) {
			return null;
		}

		return CurrencyPresentationAssetRegistry::asset_url( $region );
	}
}
