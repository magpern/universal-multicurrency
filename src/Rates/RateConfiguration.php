<?php
/**
 * Global rate configuration value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Snapshot of global automatic-rate settings.
 */
final class RateConfiguration {

	/**
	 * Global rate mode.
	 *
	 * @var string
	 */
	private string $rate_mode;

	/**
	 * Configured rate provider identifier.
	 *
	 * @var string
	 */
	private string $rate_provider;

	/**
	 * Recurring update interval.
	 *
	 * @var RateUpdateInterval
	 */
	private RateUpdateInterval $rate_update_interval;

	/**
	 * Maximum accepted automatic rate age in hours.
	 *
	 * @var int
	 */
	private int $rate_max_age_hours;

	/**
	 * Builds a global rate configuration snapshot.
	 *
	 * @param string             $rate_mode            Global rate mode.
	 * @param string             $rate_provider        Configured provider identifier.
	 * @param RateUpdateInterval $rate_update_interval Recurring update interval.
	 * @param int                $rate_max_age_hours   Maximum accepted rate age in hours.
	 */
	public function __construct(
		string $rate_mode,
		string $rate_provider,
		RateUpdateInterval $rate_update_interval,
		int $rate_max_age_hours
	) {
		$this->rate_mode            = $rate_mode;
		$this->rate_provider        = $rate_provider;
		$this->rate_update_interval = $rate_update_interval;
		$this->rate_max_age_hours   = $rate_max_age_hours;
	}

	/**
	 * The global rate mode.
	 */
	public function rate_mode(): string {
		return $this->rate_mode;
	}

	/**
	 * The configured rate provider identifier.
	 */
	public function rate_provider(): string {
		return $this->rate_provider;
	}

	/**
	 * The recurring update interval.
	 */
	public function rate_update_interval(): RateUpdateInterval {
		return $this->rate_update_interval;
	}

	/**
	 * The maximum accepted automatic rate age in hours.
	 */
	public function rate_max_age_hours(): int {
		return $this->rate_max_age_hours;
	}

	/**
	 * Whether automatic rate updates are enabled globally.
	 */
	public function is_automatic_enabled(): bool {
		return Settings::RATE_MODE_AUTOMATIC === $this->rate_mode;
	}
}
