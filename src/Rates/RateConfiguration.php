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

	private string $rate_mode;

	private string $rate_provider;

	private RateUpdateInterval $rate_update_interval;

	private int $rate_max_age_hours;

	public function __construct(
		string $rate_mode,
		string $rate_provider,
		RateUpdateInterval $rate_update_interval,
		int $rate_max_age_hours
	) {
		$this->rate_mode             = $rate_mode;
		$this->rate_provider         = $rate_provider;
		$this->rate_update_interval  = $rate_update_interval;
		$this->rate_max_age_hours    = $rate_max_age_hours;
	}

	public function rate_mode(): string {
		return $this->rate_mode;
	}

	public function rate_provider(): string {
		return $this->rate_provider;
	}

	public function rate_update_interval(): RateUpdateInterval {
		return $this->rate_update_interval;
	}

	public function rate_max_age_hours(): int {
		return $this->rate_max_age_hours;
	}

	public function is_automatic_enabled(): bool {
		return Settings::RATE_MODE_AUTOMATIC === $this->rate_mode;
	}
}
