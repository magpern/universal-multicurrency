<?php
/**
 * Unit tests for DetectorRegistry::sanitize() — the total, never-throws
 * contract shared with `\UMC\Settings::sanitize()`.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\SignatureKind;

/**
 * Covers the sanitiser's silent-drop contract: caps, reserved ids,
 * per-kind needle patterns, weight clamping, ordering and self-detection.
 */
final class DetectorRegistrySanitizeTest extends TestCase {

	/**
	 * @dataProvider non_array_inputs
	 */
	public function test_non_array_input_never_throws_and_yields_nothing( mixed $raw ): void {
		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function non_array_inputs(): array {
		return array(
			'null'   => array( null ),
			'string' => array( 'garbage' ),
			'int'    => array( 42 ),
			'bool'   => array( true ),
			'object' => array( new \stdClass() ),
		);
	}

	public function test_a_valid_detector_survives_intact(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'example/index.php',
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertArrayHasKey( 'example', $sanitized );
		$this->assertSame( 'Example', $sanitized['example']['label'] );
		$this->assertSame( SignatureKind::PLUGIN_PATH, $sanitized['example']['signatures'][0]['kind'] );
		$this->assertSame( 'example/index.php', $sanitized['example']['signatures'][0]['needle'] );
		$this->assertSame( 60, $sanitized['example']['signatures'][0]['weight'], 'A missing weight falls back to the kind default.' );
	}

	/**
	 * @dataProvider reserved_ids
	 */
	public function test_reserved_ids_are_dropped( string $id ): void {
		$raw = array(
			$id => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function reserved_ids(): array {
		return array(
			'umc'                     => array( 'umc' ),
			'universal-multicurrency' => array( 'universal-multicurrency' ),
		);
	}

	/**
	 * @dataProvider invalid_ids
	 */
	public function test_a_malformed_id_is_dropped( string $id ): void {
		$raw = array(
			$id => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function invalid_ids(): array {
		return array(
			'too short'       => array( 'a' ),
			'uppercase'       => array( 'Example' ),
			'spaces'          => array( 'ex ample' ),
			'over max length' => array( str_repeat( 'a', 33 ) ),
		);
	}

	public function test_a_non_array_row_is_dropped(): void {
		$this->assertSame( array(), DetectorRegistry::sanitize( array( 'example' => 'not an array' ) ) );
	}

	public function test_a_non_array_signatures_field_is_dropped(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => 'not an array',
			),
		);

		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}

	public function test_a_signature_with_an_unknown_kind_is_dropped_but_the_detector_survives(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => 'option',
						'needle' => 'example_option',
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertCount( 1, $sanitized['example']['signatures'] );
		$this->assertSame( SignatureKind::HOOK, $sanitized['example']['signatures'][0]['kind'] );
	}

	public function test_a_detector_with_zero_surviving_signatures_is_dropped_entirely(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => 'option',
						'needle' => 'example_option',
					),
				),
			),
		);

		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}

	public function test_a_signature_needle_that_does_not_match_its_kind_pattern_is_dropped(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'a',
					), // Too short.
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertCount( 1, $sanitized['example']['signatures'] );
		$this->assertSame( 'example_hook', $sanitized['example']['signatures'][0]['needle'] );
	}

	/**
	 * #10: self-detection. A manifest row targeting a UMC symbol must never
	 * survive sanitisation, however it is spelled.
	 *
	 * @dataProvider self_targeting_needles
	 */
	public function test_a_needle_targeting_a_umc_symbol_is_dropped( string $kind, string $needle ): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => $kind,
						'needle' => $needle,
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertCount(
			1,
			$sanitized['example']['signatures'],
			'The self-targeting signature must be dropped; the unrelated one must survive.'
		);
		$this->assertSame( 'example_hook', $sanitized['example']['signatures'][0]['needle'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function self_targeting_needles(): array {
		return array(
			'plugin path containing universal-multicurrency' => array( SignatureKind::PLUGIN_PATH, 'universal-multicurrency/universal-multicurrency.php' ),
			'class prefixed UMC\\'                  => array( SignatureKind::CLASS_NAME, 'UMC\\Plugin' ),
			// The plugin's own function/hook/constant prefix is umc_, not the
			// spelled-out slug — a real self-reference is spelled this way.
			'function prefixed umc_'                => array( SignatureKind::FUNCTION, 'umc_is_request_convertible' ),
			'hook prefixed UMC_ (case-insensitive)' => array( SignatureKind::HOOK, 'UMC_currency_signature' ),
		);
	}

	public function test_class_needle_strips_a_leading_backslash(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => '\\Example_Class',
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertSame( 'Example_Class', $sanitized['example']['signatures'][0]['needle'] );
	}

	/**
	 * @dataProvider weight_inputs
	 */
	public function test_weight_is_clamped_into_the_admissible_range( mixed $weight, int $expected ): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
						'weight' => $weight,
					),
				),
			),
		);

		$sanitized = DetectorRegistry::sanitize( $raw );

		$this->assertSame( $expected, $sanitized['example']['signatures'][0]['weight'] );
	}

	/**
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function weight_inputs(): array {
		return array(
			'in range'                          => array( 25, 25 ),
			'numeric string'                    => array( '25', 25 ),
			'below minimum'                     => array( 0, 1 ),
			'above maximum'                     => array( 100, 60 ),
			'negative'                          => array( -5, 1 ),
			'non-numeric falls back to default' => array( 'not-a-number', SignatureKind::default_weight( SignatureKind::HOOK ) ),
		);
	}

	public function test_label_falls_back_to_the_id_when_missing_or_empty(): void {
		$raw = array(
			'example' => array(
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$this->assertSame( 'example', DetectorRegistry::sanitize( $raw )['example']['label'] );
	}

	public function test_label_strips_tags_and_is_truncated(): void {
		$raw = array(
			'example' => array(
				'label'      => '<script>' . str_repeat( 'a', 130 ) . '</script>',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$label = DetectorRegistry::sanitize( $raw )['example']['label'];

		$this->assertStringNotContainsString( '<script>', $label );
		$this->assertLessThanOrEqual( 120, strlen( $label ) );
	}

	public function test_signatures_are_sorted_by_weight_descending_then_kind_then_needle(): void {
		$raw = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'zzz_hook',
					),
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'example/index.php',
					),
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => 'Example_Class',
					),
				),
			),
		);

		$sorted = array_column( DetectorRegistry::sanitize( $raw )['example']['signatures'], 'kind' );

		$this->assertSame( array( SignatureKind::PLUGIN_PATH, SignatureKind::CLASS_NAME, SignatureKind::HOOK ), $sorted );
	}

	public function test_signatures_over_the_cap_are_truncated_keeping_the_highest_weight(): void {
		$signatures = array();

		for ( $i = 0; $i < 15; $i++ ) {
			$signatures[] = array(
				'kind'   => SignatureKind::HOOK,
				'needle' => "example_hook_{$i}",
				'weight' => $i + 1,
			);
		}

		$raw       = array(
			'example' => array(
				'label'      => 'Example',
				'signatures' => $signatures,
			),
		);
		$sanitized = DetectorRegistry::sanitize( $raw )['example']['signatures'];

		$this->assertCount( 12, $sanitized );
		$this->assertSame( 15, $sanitized[0]['weight'], 'The highest-weight signatures must survive truncation.' );
	}

	public function test_detectors_are_ordered_by_id_ascending_regardless_of_input_order(): void {
		$raw = array(
			'zebra' => array(
				'label'      => 'Zebra',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'zebra_hook',
					),
				),
			),
			'alpha' => array(
				'label'      => 'Alpha',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'alpha_hook',
					),
				),
			),
		);

		$this->assertSame( array( 'alpha', 'zebra' ), array_keys( DetectorRegistry::sanitize( $raw ) ) );
	}

	public function test_detectors_over_the_cap_are_truncated(): void {
		$raw = array();

		for ( $i = 0; $i < DetectorRegistry::MAX_DETECTORS + 5; $i++ ) {
			$id         = sprintf( 'detector-%02d', $i );
			$raw[ $id ] = array(
				'label'      => "Detector {$i}",
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => "hook_{$i}",
					),
				),
			);
		}

		$this->assertCount( DetectorRegistry::MAX_DETECTORS, DetectorRegistry::sanitize( $raw ) );
	}

	public function test_deeply_malformed_input_never_throws(): void {
		$raw = array(
			'example' => 'not an array',
			'another' => array( 'signatures' => array( 'not an array either', 42, null, array( 'kind' => 123 ) ) ),
			1         => array( 'signatures' => array() ),
			'UMC'     => array(
				'signatures' => array(
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'example_hook',
					),
				),
			),
		);

		$this->assertSame( array(), DetectorRegistry::sanitize( $raw ) );
	}
}
