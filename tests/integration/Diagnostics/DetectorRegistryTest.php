<?php
/**
 * Integration tests for DetectorRegistry::detectors().
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\DetectorManifest;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\SignatureKind;
use WP_UnitTestCase;

/**
 * Covers apply_filters integration, memoization, and malformed filter output.
 */
final class DetectorRegistryTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );
		parent::tearDown();
	}

	public function test_built_in_manifest_entries_are_included(): void {
		$registry = new DetectorRegistry();
		$ids      = array_map(
			static function ( $detector ): string {
				return $detector->id();
			},
			$registry->detectors()
		);

		foreach ( array_keys( DetectorManifest::manifest() ) as $expected ) {
			$this->assertContains( $expected, $ids );
		}
	}

	public function test_filter_can_add_a_valid_detector_and_reserved_ids_are_dropped(): void {
		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['fixture-extra'] = array(
					'label'      => 'Fixture Extra',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::HOOK,
							'needle' => 'fixture_extra_hook',
						),
					),
				);
				$manifest['umc']           = array(
					'label'      => 'Self Target',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::HOOK,
							'needle' => 'umc_self_target_hook',
						),
					),
				);

				return $manifest;
			}
		);

		$ids = array_map(
			static function ( $detector ): string {
				return $detector->id();
			},
			( new DetectorRegistry() )->detectors()
		);

		$this->assertContains( 'fixture-extra', $ids );
		$this->assertNotContains( 'umc', $ids );
	}

	public function test_detectors_are_memoized_and_ordered_by_id(): void {
		$registry = new DetectorRegistry();
		$first    = $registry->detectors();
		$second   = $registry->detectors();

		$this->assertSame( $first, $second );

		$ids    = array_map(
			static function ( $detector ): string {
				return $detector->id();
			},
			$first
		);
		$sorted = $ids;
		sort( $sorted );

		$this->assertSame( $sorted, $ids );
	}

	public function test_malformed_filter_entries_are_dropped(): void {
		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['bad'] = 'not-an-array';

				return $manifest;
			}
		);

		$ids = array_map(
			static function ( $detector ): string {
				return $detector->id();
			},
			( new DetectorRegistry() )->detectors()
		);

		$this->assertNotContains( 'bad', $ids );
	}
}
