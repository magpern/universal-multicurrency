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
 *
 * EUR always resolves to the European Union presentation region. Merchant
 * `icon_overrides['EUR']` is ignored at presentation time (ADR-0037).
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
		'JPY' => CurrencyPresentationAssetRegistry::REGION_JP,
		'CAD' => CurrencyPresentationAssetRegistry::REGION_CA,
		'AUD' => CurrencyPresentationAssetRegistry::REGION_AU,
		'NZD' => CurrencyPresentationAssetRegistry::REGION_NZ,
		'KRW' => CurrencyPresentationAssetRegistry::REGION_KR,
		'CNY' => CurrencyPresentationAssetRegistry::REGION_CN,
		'INR' => CurrencyPresentationAssetRegistry::REGION_IN,
		'BRL' => CurrencyPresentationAssetRegistry::REGION_BR,
		'MXN' => CurrencyPresentationAssetRegistry::REGION_MX,
		'SGD' => CurrencyPresentationAssetRegistry::REGION_SG,
		'HKD' => CurrencyPresentationAssetRegistry::REGION_HK,
		'ZAR' => CurrencyPresentationAssetRegistry::REGION_ZA,
		'CZK' => CurrencyPresentationAssetRegistry::REGION_CZ,
	);

	/**
	 * ISO currency codes that must never receive a default flag.
	 *
	 * @var array<int, string>
	 */
	private const NO_FLAG_CODES = array(
		'XOF',
		'XAF',
		'XCD',
		'XPF',
		'XDR',
		'XAU',
		'XAG',
		'XPT',
		'XPD',
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

		if ( in_array( $code, self::NO_FLAG_CODES, true ) ) {
			return null;
		}

		return self::BUILTIN_DEFAULTS[ $code ] ?? null;
	}

	/**
	 * Whether merchant overrides are ignored for this currency at presentation time.
	 *
	 * @param string $code Currency code.
	 */
	public static function is_override_locked( string $code ): bool {
		return 'EUR' === strtoupper( trim( $code ) );
	}

	/**
	 * Effective presentation-region identifier for one currency.
	 *
	 * @param string $code Currency code.
	 */
	public function region_for_currency( string $code ): ?string {
		$code = strtoupper( trim( $code ) );

		if ( self::is_override_locked( $code ) ) {
			$region = CurrencyPresentationAssetRegistry::REGION_EU;

			return CurrencyPresentationAssetRegistry::is_valid_region( $region ) ? $region : null;
		}

		if ( in_array( $code, self::NO_FLAG_CODES, true ) ) {
			return null;
		}

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
