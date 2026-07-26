<?php
/**
 * Test double returning a fixed detector list.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Doubles;

use UMC\Diagnostics\Detector;
use UMC\Diagnostics\DetectorCatalog;

/**
 * Supplies a constructor-provided detector list for unit tests.
 */
final class StaticDetectorRegistry implements DetectorCatalog {

	/**
	 * @var array<int, Detector>
	 */
	private array $detectors;

	/**
	 * @param array<int, Detector> $detectors Fixed detector list.
	 */
	public function __construct( array $detectors ) {
		$this->detectors = $detectors;
	}

	public function detectors(): array {
		return $this->detectors;
	}
}
