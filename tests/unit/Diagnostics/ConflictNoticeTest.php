<?php
/**
 * Unit tests for ConflictNotice view-model scoping.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictNotice;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\Finding;
use UMC\Diagnostics\Signature;
use UMC\Diagnostics\SignatureKind;
use UMC\Tests\Unit\Doubles\ArrayEnvironmentProbe;
use UMC\Tests\Unit\Doubles\StaticDetectorRegistry;

/**
 * Covers plan case #15: screen scoping, CSS classes, and message keys.
 */
final class ConflictNoticeTest extends TestCase {

	private function notice(): ConflictNotice {
		return new ConflictNotice(
			new ConflictDetector(
				new StaticDetectorRegistry( array() ),
				new ArrayEnvironmentProbe( array() ),
				new ConflictScorer()
			)
		);
	}

	private function finding( string $id, string $label, string $confidence ): Finding {
		$score = Confidence::THRESHOLD_HIGH;

		if ( Confidence::MEDIUM === $confidence ) {
			$score = Confidence::THRESHOLD_MEDIUM;
		} elseif ( Confidence::LOW === $confidence ) {
			$score = Confidence::THRESHOLD_LOW;
		}

		return new Finding( $id, $label, $score, $confidence, array() );
	}

	public function test_high_on_dashboard_returns_error_notice_and_message_keys(): void {
		$view = $this->notice()->view_model(
			array( $this->finding( 'woocs', 'WOOCS', Confidence::HIGH ) ),
			'dashboard',
			false
		);

		$this->assertIsArray( $view );
		$this->assertSame( 'notice notice-error', $view['notice_class'] );
		$this->assertSame( Confidence::HIGH, $view['confidence'] );
		$this->assertSame(
			array(
				'detected'   => true,
				'why_unsafe' => true,
				'symptoms'   => true,
				'resolution' => true,
				'disclaimer' => true,
			),
			$view['messages']
		);
	}

	public function test_medium_on_dashboard_returns_no_notice(): void {
		$this->assertNull(
			$this->notice()->view_model(
				array( $this->finding( 'partial', 'Partial Switcher', Confidence::MEDIUM ) ),
				'dashboard',
				false
			)
		);
	}

	public function test_medium_on_plugins_returns_warning_notice(): void {
		$view = $this->notice()->view_model(
			array( $this->finding( 'partial', 'Partial Switcher', Confidence::MEDIUM ) ),
			'plugins',
			false
		);

		$this->assertIsArray( $view );
		$this->assertSame( 'notice notice-warning', $view['notice_class'] );
		$this->assertSame( Confidence::MEDIUM, $view['confidence'] );
	}

	public function test_low_confidence_returns_no_notice(): void {
		$this->assertNull(
			$this->notice()->view_model(
				array( $this->finding( 'leftover', 'Leftover Constant', Confidence::LOW ) ),
				'plugins',
				false
			)
		);
	}

	public function test_two_findings_use_highest_confidence_for_screen_and_class(): void {
		$view = $this->notice()->view_model(
			array(
				$this->finding( 'alpha', 'Alpha Switcher', Confidence::MEDIUM ),
				$this->finding( 'beta', 'Beta Switcher', Confidence::HIGH ),
			),
			'dashboard',
			false
		);

		$this->assertIsArray( $view );
		$this->assertSame( 'notice notice-error', $view['notice_class'] );
		$this->assertSame( array( 'Alpha Switcher', 'Beta Switcher' ), $view['labels'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public function dismissible_provider(): array {
		return array(
			'high dashboard'     => array( Confidence::HIGH, 'dashboard', true ),
			'high plugins'       => array( Confidence::HIGH, 'plugins', false ),
			'high wc settings'   => array( Confidence::HIGH, 'woocommerce_page_wc-settings', false ),
			'medium plugins'     => array( Confidence::MEDIUM, 'plugins', true ),
			'medium wc settings' => array( Confidence::MEDIUM, 'woocommerce_page_wc-settings', false ),
		);
	}

	/**
	 * @dataProvider dismissible_provider
	 */
	public function test_is_dismissible( string $confidence, string $screen_id, bool $expected ): void {
		$this->assertSame( $expected, ConflictNotice::is_dismissible( $confidence, $screen_id ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public function screen_scope_provider(): array {
		return array(
			'high dashboard'     => array( Confidence::HIGH, 'dashboard', true ),
			'high plugins'       => array( Confidence::HIGH, 'plugins', true ),
			'high wc settings'   => array( Confidence::HIGH, 'woocommerce_page_wc-settings', true ),
			'high update core'   => array( Confidence::HIGH, 'update-core', true ),
			'high edit post'     => array( Confidence::HIGH, 'post', false ),
			'medium plugins'     => array( Confidence::MEDIUM, 'plugins', true ),
			'medium wc settings' => array( Confidence::MEDIUM, 'woocommerce_page_wc-settings', true ),
			'medium dashboard'   => array( Confidence::MEDIUM, 'dashboard', false ),
			'medium update core' => array( Confidence::MEDIUM, 'update-core', false ),
			'low plugins'        => array( Confidence::LOW, 'plugins', false ),
		);
	}

	/**
	 * @dataProvider screen_scope_provider
	 */
	public function test_should_show_on_screen( string $confidence, string $screen_id, bool $expected ): void {
		$this->assertSame( $expected, ConflictNotice::should_show_on_screen( $confidence, $screen_id ) );
	}

	public function test_settings_view_model_includes_matched_evidence_only(): void {
		$finding = new Finding(
			'fixture-a',
			'Fixture Switcher A',
			Confidence::THRESHOLD_HIGH,
			Confidence::HIGH,
			array(
				new Signature( SignatureKind::PLUGIN_PATH, 'fixture-a/index.php', 60 ),
				new Signature( SignatureKind::CLASS_NAME, 'Fixture_A', 40 ),
			)
		);

		$view = $this->notice()->settings_view_model( array( $finding ), true );

		$this->assertIsArray( $view );
		$this->assertSame( 'settings', $view['surface'] );
		$this->assertSame( 'notice', $view['render_mode'] );
		$this->assertSame( 'notice notice-error inline', $view['notice_class'] );
		$this->assertSame(
			array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => 'fixture-a/index.php',
				),
				array(
					'kind'   => SignatureKind::CLASS_NAME,
					'needle' => 'Fixture_A',
				),
			),
			$view['findings'][0]['evidence']
		);
	}

	public function test_settings_view_model_low_uses_description_mode(): void {
		$finding = new Finding(
			'leftover',
			'Leftover Constant',
			Confidence::THRESHOLD_LOW,
			Confidence::LOW,
			array(
				new Signature( SignatureKind::CONSTANT, 'FOREIGN_CURRENCY_VERSION', 25 ),
			)
		);

		$view = $this->notice()->settings_view_model( array( $finding ), false );

		$this->assertIsArray( $view );
		$this->assertSame( Confidence::LOW, $view['confidence'] );
		$this->assertSame( 'description', $view['render_mode'] );
		$this->assertSame( '', $view['notice_class'] );
		$this->assertFalse( $view['can_activate_plugins'] );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function settings_notice_class_provider(): array {
		return array(
			'high'   => array( Confidence::HIGH, 'notice notice-error inline' ),
			'medium' => array( Confidence::MEDIUM, 'notice notice-warning inline' ),
			'low'    => array( Confidence::LOW, '' ),
		);
	}

	/**
	 * @dataProvider settings_notice_class_provider
	 */
	public function test_settings_notice_class( string $confidence, string $expected ): void {
		$this->assertSame( $expected, ConflictNotice::settings_notice_class( $confidence ) );
	}

	public function test_format_evidence_sentence_joins_matched_phrases(): void {
		$sentence = ConflictNotice::format_evidence_sentence(
			array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => 'fixture-a/index.php',
				),
				array(
					'kind'   => SignatureKind::CLASS_NAME,
					'needle' => 'Fixture_A',
				),
			)
		);

		$this->assertSame(
			'the plugin "fixture-a/index.php" is active; and the class "Fixture_A" exists',
			$sentence
		);
	}
}
