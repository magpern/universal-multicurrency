<?php
/**
 * Unit tests for SiteHealthReport pure helpers.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictNotice;
use UMC\Diagnostics\Finding;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\SiteHealthReport;
use UMC\Diagnostics\VersionPolicy;

/**
 * Covers conflict/environment status mapping and debug serialisation.
 */
final class SiteHealthReportTest extends TestCase {

	private function finding( string $id, string $label, string $confidence, array $matched = array() ): Finding {
		$score = Confidence::THRESHOLD_HIGH;

		if ( Confidence::MEDIUM === $confidence ) {
			$score = Confidence::THRESHOLD_MEDIUM;
		} elseif ( Confidence::LOW === $confidence ) {
			$score = Confidence::THRESHOLD_LOW;
		}

		return new Finding( $id, $label, $score, $confidence, $matched );
	}

	/**
	 * @dataProvider conflict_status_cases
	 */
	public function test_conflict_status_for_confidence( string $confidence, string $expected ): void {
		$this->assertSame( $expected, SiteHealthReport::conflict_status_for_confidence( $confidence ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function conflict_status_cases(): array {
		return array(
			'none'   => array( Confidence::NONE, 'good' ),
			'low'    => array( Confidence::LOW, 'good' ),
			'medium' => array( Confidence::MEDIUM, 'recommended' ),
			'high'   => array( Confidence::HIGH, 'critical' ),
		);
	}

	public function test_environment_status_critical_when_any_axis_is_below_floor(): void {
		$this->assertSame(
			'critical',
			SiteHealthReport::environment_status(
				array(
					'php' => VersionPolicy::SUPPORTED,
					'wp'  => VersionPolicy::SUPPORTED,
					'wc'  => VersionPolicy::BELOW_FLOOR,
				),
				true,
				false
			)
		);
	}

	public function test_environment_status_recommended_when_above_tested(): void {
		$this->assertSame(
			'recommended',
			SiteHealthReport::environment_status(
				array(
					'php' => VersionPolicy::ABOVE_TESTED,
					'wp'  => VersionPolicy::SUPPORTED,
					'wc'  => VersionPolicy::SUPPORTED,
				),
				true,
				false
			)
		);
	}

	public function test_environment_status_recommended_when_hpos_disabled(): void {
		$this->assertSame(
			'recommended',
			SiteHealthReport::environment_status(
				array(
					'php' => VersionPolicy::SUPPORTED,
					'wp'  => VersionPolicy::SUPPORTED,
					'wc'  => VersionPolicy::SUPPORTED,
				),
				false,
				false
			)
		);
	}

	public function test_environment_status_good_when_in_box_and_hpos_enabled(): void {
		$this->assertSame(
			'good',
			SiteHealthReport::environment_status(
				array(
					'php' => VersionPolicy::SUPPORTED,
					'wp'  => VersionPolicy::SUPPORTED,
					'wc'  => VersionPolicy::SUPPORTED,
				),
				true,
				false
			)
		);
	}

	public function test_conflicts_detected_rows_include_signature_keys_only(): void {
		$matched = array(
			new Signature( SignatureKind::PLUGIN_PATH, 'other/switcher.php', 60 ),
			new Signature( SignatureKind::CLASS_NAME, 'Other_Switcher', 40 ),
		);

		$rows = SiteHealthReport::conflicts_detected_rows(
			array(
				$this->finding( 'other', 'Other Switcher', Confidence::HIGH, $matched ),
			)
		);

		$this->assertSame(
			array(
				array(
					'id'         => 'other',
					'label'      => 'Other Switcher',
					'confidence' => Confidence::HIGH,
					'matched'    => array(
						'plugin_path:other/switcher.php',
						'class:Other_Switcher',
					),
				),
			),
			$rows
		);
	}

	public function test_currency_counts_never_expose_rate_values(): void {
		$counts = SiteHealthReport::currency_counts(
			array(
				'USD' => array(
					'enabled' => true,
					'rate'    => '1.23456789',
				),
				'EUR' => array(
					'enabled' => false,
					'rate'    => '0.91',
				),
				'GBP' => array(
					'enabled' => true,
					'rate'    => '',
				),
			)
		);

		$this->assertSame(
			array(
				'configured'        => 3,
				'enabled_and_rated' => 1,
			),
			$counts
		);
	}

	public function test_evaluate_environment_axes_uses_declared_floors_and_tested_ceilings(): void {
		$policy = new VersionPolicy();

		$axes = SiteHealthReport::evaluate_environment_axes(
			$policy,
			array(
				'php'       => '8.1',
				'wp'        => '6.5',
				'wc'        => '8.2',
				'wc_tested' => '10.9',
			),
			array(
				'php' => '8.0',
				'wp'  => '6.5',
				'wc'  => '8.2',
			)
		);

		$this->assertSame( VersionPolicy::BELOW_FLOOR, $axes['php'] );
		$this->assertSame( VersionPolicy::AT_FLOOR, $axes['wp'] );
		$this->assertSame( VersionPolicy::AT_FLOOR, $axes['wc'] );
	}

	public function test_highest_confidence_is_shared_with_conflict_notice(): void {
		$findings = array(
			$this->finding( 'a', 'A', Confidence::LOW ),
			$this->finding( 'b', 'B', Confidence::HIGH ),
		);

		$this->assertSame( Confidence::HIGH, ConflictNotice::highest_confidence( $findings ) );
		$this->assertSame( 'critical', SiteHealthReport::conflict_status_for_confidence( ConflictNotice::highest_confidence( $findings ) ) );
	}
}
