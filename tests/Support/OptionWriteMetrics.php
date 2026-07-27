<?php
/**
 * Option write counters for WordPress-free unit tests.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Support;

/**
 * Tracks attempted option writes via the unit-test bootstrap stubs.
 */
final class OptionWriteMetrics {

	/**
	 * Attempted writes of the merchant settings option.
	 *
	 * @var int
	 */
	public static int $umc_settings_writes = 0;

	/**
	 * Attempted writes of the operational rate-state option.
	 *
	 * @var int
	 */
	public static int $umc_rate_state_writes = 0;

	/**
	 * Resets all counters.
	 */
	public static function reset(): void {
		self::$umc_settings_writes   = 0;
		self::$umc_rate_state_writes = 0;
	}

	/**
	 * Records one attempted settings option write.
	 */
	public static function record_settings_write(): void {
		++self::$umc_settings_writes;
	}

	/**
	 * Records one attempted rate-state option write.
	 */
	public static function record_rate_state_write(): void {
		++self::$umc_rate_state_writes;
	}
}
