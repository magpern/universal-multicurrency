<?php
/**
 * Read-only access to the hydrated detector list.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The seam {@see ConflictDetector} uses so unit tests can supply a fixed
 * detector list without bootstrapping WordPress filter machinery.
 */
interface DetectorCatalog {

	/**
	 * Returns hydrated detectors in registry order.
	 *
	 * @return array<int, Detector> Ordered by id ascending.
	 */
	public function detectors(): array;
}
