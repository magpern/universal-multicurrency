<?php
/**
 * Registry of bundled switcher presentation assets.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Maps presentation-region identifiers to packaged SVG assets.
 */
final class CurrencyPresentationAssetRegistry {

	public const REGION_CH = 'CH';

	public const REGION_DK = 'DK';

	public const REGION_EU = 'EU';

	public const REGION_GB = 'GB';

	public const REGION_NO = 'NO';

	public const REGION_PL = 'PL';

	public const REGION_SE = 'SE';

	public const REGION_US = 'US';

	public const REGION_JP = 'JP';

	public const REGION_CA = 'CA';

	public const REGION_AU = 'AU';

	public const REGION_NZ = 'NZ';

	public const REGION_KR = 'KR';

	public const REGION_CN = 'CN';

	public const REGION_IN = 'IN';

	public const REGION_BR = 'BR';

	public const REGION_MX = 'MX';

	public const REGION_SG = 'SG';

	public const REGION_HK = 'HK';

	public const REGION_ZA = 'ZA';

	public const REGION_CZ = 'CZ';

	private const ASSET_DIRECTORY = 'assets/icons/presentation/';

	/**
	 * Registry entries keyed by presentation-region identifier.
	 *
	 * @var array<string, array{file: string, label: string}>
	 */
	private const REGIONS = array(
		self::REGION_CH => array(
			'file'  => 'CH.svg',
			'label' => 'Switzerland (presentation)',
		),
		self::REGION_DK => array(
			'file'  => 'DK.svg',
			'label' => 'Denmark (presentation)',
		),
		self::REGION_EU => array(
			'file'  => 'EU.svg',
			'label' => 'European Union (presentation)',
		),
		self::REGION_GB => array(
			'file'  => 'GB.svg',
			'label' => 'United Kingdom (presentation)',
		),
		self::REGION_NO => array(
			'file'  => 'NO.svg',
			'label' => 'Norway (presentation)',
		),
		self::REGION_PL => array(
			'file'  => 'PL.svg',
			'label' => 'Poland (presentation)',
		),
		self::REGION_SE => array(
			'file'  => 'SE.svg',
			'label' => 'Sweden (presentation)',
		),
		self::REGION_US => array(
			'file'  => 'US.svg',
			'label' => 'United States (presentation)',
		),
		self::REGION_JP => array(
			'file'  => 'JP.svg',
			'label' => 'Japan (presentation)',
		),
		self::REGION_CA => array(
			'file'  => 'CA.svg',
			'label' => 'Canada (presentation)',
		),
		self::REGION_AU => array(
			'file'  => 'AU.svg',
			'label' => 'Australia (presentation)',
		),
		self::REGION_NZ => array(
			'file'  => 'NZ.svg',
			'label' => 'New Zealand (presentation)',
		),
		self::REGION_KR => array(
			'file'  => 'KR.svg',
			'label' => 'South Korea (presentation)',
		),
		self::REGION_CN => array(
			'file'  => 'CN.svg',
			'label' => 'China (presentation)',
		),
		self::REGION_IN => array(
			'file'  => 'IN.svg',
			'label' => 'India (presentation)',
		),
		self::REGION_BR => array(
			'file'  => 'BR.svg',
			'label' => 'Brazil (presentation)',
		),
		self::REGION_MX => array(
			'file'  => 'MX.svg',
			'label' => 'Mexico (presentation)',
		),
		self::REGION_SG => array(
			'file'  => 'SG.svg',
			'label' => 'Singapore (presentation)',
		),
		self::REGION_HK => array(
			'file'  => 'HK.svg',
			'label' => 'Hong Kong (presentation)',
		),
		self::REGION_ZA => array(
			'file'  => 'ZA.svg',
			'label' => 'South Africa (presentation)',
		),
		self::REGION_CZ => array(
			'file'  => 'CZ.svg',
			'label' => 'Czechia (presentation)',
		),
	);

	/**
	 * Allowed presentation-region identifiers.
	 *
	 * @return array<int, string>
	 */
	public static function region_ids(): array {
		return array_keys( self::REGIONS );
	}

	/**
	 * Whether a presentation-region identifier is registered.
	 *
	 * @param string $region Presentation-region identifier.
	 */
	public static function is_valid_region( string $region ): bool {
		$region = strtoupper( trim( $region ) );

		return isset( self::REGIONS[ $region ] );
	}

	/**
	 * Human label for one registered region.
	 *
	 * @param string $region Presentation-region identifier.
	 */
	public static function region_label( string $region ): string {
		$region = strtoupper( trim( $region ) );

		return self::REGIONS[ $region ]['label'] ?? '';
	}

	/**
	 * Absolute filesystem path for one registered asset, when present.
	 *
	 * @param string $region Presentation-region identifier.
	 */
	public static function asset_path( string $region ): ?string {
		if ( ! self::is_valid_region( $region ) ) {
			return null;
		}

		$region = strtoupper( trim( $region ) );
		$file   = basename( self::REGIONS[ $region ]['file'] );
		$path   = self::plugin_root_path() . self::ASSET_DIRECTORY . $file;

		return is_readable( $path ) ? $path : null;
	}

	/**
	 * Public URL for one registered asset, when present.
	 *
	 * @param string $region Presentation-region identifier.
	 */
	public static function asset_url( string $region ): ?string {
		if ( null === self::asset_path( $region ) ) {
			return null;
		}

		if ( ! self::is_valid_region( $region ) ) {
			return null;
		}

		$region  = strtoupper( trim( $region ) );
		$file    = basename( self::REGIONS[ $region ]['file'] );
		$version = defined( 'UMC_VERSION' ) ? UMC_VERSION : '0';

		return self::plugin_root_url() . self::ASSET_DIRECTORY . $file . '?ver=' . rawurlencode( $version );
	}

	/**
	 * Absolute plugin root directory path.
	 */
	private static function plugin_root_path(): string {
		if ( defined( 'UMC_PLUGIN_FILE' ) && function_exists( 'plugin_dir_path' ) ) {
			return plugin_dir_path( UMC_PLUGIN_FILE );
		}

		return dirname( __DIR__, 2 ) . '/';
	}

	/**
	 * Public plugin root URL for asset resolution.
	 */
	private static function plugin_root_url(): string {
		if ( defined( 'UMC_PLUGIN_FILE' ) && function_exists( 'plugin_dir_url' ) ) {
			return plugin_dir_url( UMC_PLUGIN_FILE );
		}

		return 'https://example.test/wp-content/plugins/universal-multicurrency/';
	}
}
