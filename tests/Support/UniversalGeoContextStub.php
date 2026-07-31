<?php
/**
 * Test double for Universal Geo Context's public global functions.
 *
 * Universal Geo Context ships no consumer test doubles, so this plugin owns
 * its own — the same four functions {@see \UMC\Geo\UniversalGeoContextAdapter}
 * and {@see \UMC\Geo\UgcIntegrationStatus} feature-detect via `function_exists()`.
 *
 * Call {@see UniversalGeoContextStub::install()} to define the global
 * functions (idempotent within a process — PHP cannot undefine a function).
 * Tests that must observe "Universal Geo Context not installed" behaviour
 * (i.e. `function_exists()` returning false) must run with
 * `@runInSeparateProcess` and must NOT call install().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Support;

/**
 * Mutable state backing the stubbed Universal Geo Context globals.
 */
final class UniversalGeoContextStub {

	/**
	 * Whether {@see install()} has defined the global functions.
	 *
	 * @var bool
	 */
	private static bool $installed = false;

	/**
	 * Stubbed resolved country code.
	 *
	 * @var string|null
	 */
	private static ?string $country = null;

	/**
	 * Stubbed public API version.
	 *
	 * @var int
	 */
	private static int $api_version = 1;

	/**
	 * Stubbed resolution source.
	 *
	 * @var string
	 */
	private static string $source = 'universal_geo_context';

	/**
	 * Stubbed resolution confidence.
	 *
	 * @var float
	 */
	private static float $confidence = 1.0;

	/**
	 * Defines the global Universal Geo Context functions for this process.
	 */
	public static function install(): void {
		self::$installed = true;

		require_once __DIR__ . '/universal-geo-context-stub-functions.php';
	}

	/**
	 * Whether install() has run in this process.
	 */
	public static function is_installed(): bool {
		return self::$installed;
	}

	/**
	 * Sets the stubbed country code.
	 *
	 * @param string|null $country ISO alpha-2 code, or null/invalid to simulate "unknown".
	 */
	public static function set_country( ?string $country ): void {
		self::$country = $country;
	}

	/**
	 * Sets the stubbed public API version.
	 *
	 * @param int $version API version to report.
	 */
	public static function set_api_version( int $version ): void {
		self::$api_version = $version;
	}

	/**
	 * Sets the stubbed resolution source.
	 *
	 * @param string $source Source identifier, e.g. 'simulation'.
	 */
	public static function set_source( string $source ): void {
		self::$source = $source;
	}

	/**
	 * Sets the stubbed resolution confidence.
	 *
	 * @param float $confidence Confidence between 0 and 1.
	 */
	public static function set_confidence( float $confidence ): void {
		self::$confidence = $confidence;
	}

	/**
	 * Current stubbed country code.
	 */
	public static function country(): ?string {
		return self::$country;
	}

	/**
	 * Current stubbed API version.
	 */
	public static function api_version(): int {
		return self::$api_version;
	}

	/**
	 * Current stubbed source.
	 */
	public static function source(): string {
		return self::$source;
	}

	/**
	 * Current stubbed confidence.
	 */
	public static function confidence(): float {
		return self::$confidence;
	}

	/**
	 * Resets stubbed state to defaults (does not un-install the functions).
	 */
	public static function reset(): void {
		self::$country     = null;
		self::$api_version = 1;
		self::$source      = 'universal_geo_context';
		self::$confidence  = 1.0;
	}
}
