<?php
/**
 * Manifest quality invariants over DetectorManifest::manifest().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Detector;
use UMC\Diagnostics\DetectorManifest;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;

/**
 * `DetectorManifest::manifest()` returns no detectors as of this milestone's
 * scoring-core commit (see that class's docblock — every built-in awaits
 * signature verification against real plugin source before it ships). Every
 * assertion below therefore holds vacuously today: each `foreach` runs zero
 * times, so nothing can fail. That is correct, not a gap — these invariants
 * exist to constrain whatever a later commit adds, and each one starts doing
 * real work the moment the first built-in detector lands.
 */
final class ManifestQualityTest extends TestCase {

	/**
	 * @return array<int, Detector>
	 */
	private function hydrated_detectors(): array {
		$sanitized = DetectorRegistry::sanitize( DetectorManifest::manifest() );
		$detectors = array();

		foreach ( $sanitized as $id => $row ) {
			$signatures = array();

			foreach ( $row['signatures'] as $signature ) {
				$signatures[] = new Signature( $signature['kind'], $signature['needle'], $signature['weight'] );
			}

			$detectors[] = new Detector( $id, $row['label'], $signatures );
		}

		return $detectors;
	}

	public function test_mq1_every_built_in_has_at_least_one_plugin_path_signature(): void {
		$detectors = $this->hydrated_detectors();
		$this->assertIsArray( $detectors ); // Guarantees an assertion runs even while the manifest is empty.

		foreach ( $detectors as $detector ) {
			$kinds = array_map( static fn( Signature $s ): string => $s->kind(), $detector->signatures() );

			$this->assertContains(
				SignatureKind::PLUGIN_PATH,
				$kinds,
				"Detector '{$detector->id()}' has no plugin_path signature — HIGH confidence would not survive a total symbol rename."
			);
		}
	}

	public function test_mq2_every_built_in_reaches_at_least_medium_from_non_plugin_path_signatures_alone(): void {
		$detectors = $this->hydrated_detectors();
		$this->assertIsArray( $detectors ); // Guarantees an assertion runs even while the manifest is empty.

		foreach ( $detectors as $detector ) {
			$non_plugin_path_weight = 0;

			foreach ( $detector->signatures() as $signature ) {
				if ( SignatureKind::PLUGIN_PATH !== $signature->kind() ) {
					$non_plugin_path_weight += $signature->weight();
				}
			}

			$this->assertGreaterThanOrEqual(
				30,
				min( 100, $non_plugin_path_weight ),
				"Detector '{$detector->id()}' cannot reach MEDIUM from symbol evidence alone — a mu-plugin install would go undetected."
			);
		}
	}

	public function test_mq3_no_needle_is_shared_between_two_built_in_detectors(): void {
		$seen = array();

		foreach ( $this->hydrated_detectors() as $detector ) {
			foreach ( $detector->signatures() as $signature ) {
				$owner = $seen[ $signature->key() ] ?? '';

				$this->assertArrayNotHasKey(
					$signature->key(),
					$seen,
					"Signature '{$signature->key()}' is shared between '{$owner}' and '{$detector->id()}' — cross-detection risk."
				);

				$seen[ $signature->key() ] = $detector->id();
			}
		}

		$this->assertIsArray( $seen );
	}

	public function test_mq4_no_built_in_signature_targets_a_umc_symbol(): void {
		$detectors = $this->hydrated_detectors();
		$this->assertIsArray( $detectors ); // Guarantees an assertion runs even while the manifest is empty.

		foreach ( $detectors as $detector ) {
			foreach ( $detector->signatures() as $signature ) {
				$this->assertStringNotContainsStringIgnoringCase( 'universal-multicurrency', $signature->needle() );
				$this->assertDoesNotMatchRegularExpression( '/^UMC\\\\/', $signature->needle() );
				$this->assertDoesNotMatchRegularExpression( '/^umc_/i', $signature->needle() );
			}
		}
	}

	public function test_mq5_sanitizing_the_manifest_is_a_fixpoint(): void {
		$once  = DetectorRegistry::sanitize( DetectorManifest::manifest() );
		$twice = DetectorRegistry::sanitize( $once );

		$this->assertSame( $once, $twice, 'Built-in detectors must already be in canonical sanitized form.' );
	}

	public function test_mq6_fingerprint_relevant_ordering_is_invariant_under_manifest_shuffling(): void {
		$manifest = DetectorManifest::manifest();

		$shuffled_keys = array_keys( $manifest );
		shuffle( $shuffled_keys );

		$shuffled = array();
		foreach ( $shuffled_keys as $key ) {
			$shuffled[ $key ] = $manifest[ $key ];
		}

		$this->assertSame(
			array_keys( DetectorRegistry::sanitize( $manifest ) ),
			array_keys( DetectorRegistry::sanitize( $shuffled ) ),
			'Sanitized detector order must not depend on the input array order — the dismissal fingerprint depends on this.'
		);
	}
}
