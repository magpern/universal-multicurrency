<?php
/**
 * Merchant-visible characterized/integrated sub-labels.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Primary status lines shown in Compatibility Center without opening evidence.
 */
final class ExtensionCharacterizedSubLabel {

	public const CONTRACT_TESTS = 'Characterized — contract tests';

	public const SIMULATED_HOOKS = 'Characterized — simulated extension hooks';

	public const REAL_EXTENSION = 'Integrated — real extension validated';

	/**
	 * All sub-labels that indicate characterized (non-integrated) evidence.
	 */
	public const CHARACTERIZED_LABELS = array(
		self::CONTRACT_TESTS,
		self::SIMULATED_HOOKS,
	);
}
