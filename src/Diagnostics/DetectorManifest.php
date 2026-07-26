<?php
/**
 * The one file permitted to name a third-party plugin.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Pure data — no control flow, no probes, no WordPress calls. This is the
 * sole source of concrete third-party knowledge in the plugin: every other
 * class in this namespace operates on generic `Detector`/`Signature` value
 * objects and cannot express a plugin-specific fact even if a future edit
 * tried to (see the structural guards confining foreign identifiers to this
 * file alone).
 *
 * **Empty as of this milestone's scoring-core commit.** Each admission
 * check the project's compatibility governance requires — a reproduced
 * conflict, a needle verified against the target's actual distributed
 * source, both evidence families reachable (`MQ1`/`MQ2`) — is a standing
 * requirement, not a one-time gate, and none has been discharged yet for
 * any real plugin. Rather than guess at WOOCS/Aelia/WCML/CURCY/YayCurrency
 * internals from memory and risk shipping fabricated needles in the one
 * file this plugin trusts for concrete third-party facts, built-in
 * detectors are added only once each one's signatures have been checked
 * against that plugin's real source or wp.org listing. Until then this
 * returns no detectors, and `umc_conflict_detectors` remains the only way
 * a site sees a warning.
 */
final class DetectorManifest {

	/**
	 * The built-in detector manifest, in raw (unsanitised) form.
	 *
	 * @return array<string, array{label: string, signatures: array<int, array{kind: string, needle: string, weight?: int}>}>
	 */
	public static function manifest(): array {
		return array();
	}
}
