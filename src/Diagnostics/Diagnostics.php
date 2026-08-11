<?php
/**
 * Diagnostics sub-composition root — orchestration only in this milestone.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateHealthService;
use UMC\Settings;

/**
 * The only Diagnostics class {@see \UMC\Plugin} names outside this namespace.
 * Wires the detection stack and admin advisory surfaces.
 */
final class Diagnostics {

	/**
	 * Memoized conflict detector shared by advisory surfaces.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $detector;

	/**
	 * Merchant settings store for rate health checks.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings;

	/**
	 * Operational rate store for rate health checks.
	 *
	 * @var ExchangeRateStore|null
	 */
	private ?ExchangeRateStore $rate_store;

	/**
	 * Shared rate health aggregator.
	 *
	 * @var RateHealthService|null
	 */
	private ?RateHealthService $rate_health;

	/**
	 * Builds the diagnostics service and its detector stack.
	 *
	 * @param ConflictDetector|null  $detector    Optional detector for tests.
	 * @param Settings|null          $settings    Settings store for rate health.
	 * @param ExchangeRateStore|null $rate_store  Rate operational store.
	 * @param RateHealthService|null $rate_health Optional shared health service.
	 */
	public function __construct(
		?ConflictDetector $detector = null,
		?Settings $settings = null,
		?ExchangeRateStore $rate_store = null,
		?RateHealthService $rate_health = null
	) {
		$this->detector    = $detector ?? new ConflictDetector(
			new DetectorRegistry(),
			new WordPressEnvironmentProbe(),
			new ConflictScorer()
		);
		$this->settings    = $settings;
		$this->rate_store  = $rate_store;
		$this->rate_health = $rate_health;
	}

	/**
	 * Registers diagnostics admin surfaces.
	 */
	public function register(): void {
		$dismissal = new NoticeDismissal( $this->detector );
		$dismissal->register();

		( new ConflictNotice( $this->detector, $dismissal ) )->register();
		( new SiteHealthReport( $this->detector, null, $this->settings, $this->rate_store, $this->rate_health ) )->register();
	}

	/**
	 * The memoized conflict detector owned by this service.
	 */
	public function conflict_detector(): ConflictDetector {
		return $this->detector;
	}
}
