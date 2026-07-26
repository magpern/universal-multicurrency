<?php
/**
 * Integration tests for end-to-end conflict detection.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Integration\Diagnostics;

use UMC\Diagnostics\Confidence;
use UMC\Diagnostics\ConflictDetector;
use UMC\Diagnostics\ConflictScorer;
use UMC\Diagnostics\DetectorRegistry;
use UMC\Diagnostics\SignatureKind;
use UMC\Diagnostics\WordPressEnvironmentProbe;
use WP_UnitTestCase;

/**
 * Uses install-wp.sh fixtures and the umc_conflict_detectors filter so
 * production DetectorManifest never names test fixtures.
 */
final class ConflictDetectorIntegrationTest extends WP_UnitTestCase {

	private const FIXTURE_A = 'umc-fixture-switcher-a/umc-fixture-switcher-a.php';

	private const FIXTURE_B = 'umc-fixture-switcher-b/umc-fixture-switcher-b.php';

	private const FIXTURE_INERT = 'umc-fixture-inert/umc-fixture-inert.php';

	/**
	 * @var array<string, mixed>|null
	 */
	private ?array $active_plugins_backup = null;

	protected function setUp(): void {
		parent::setUp();

		$this->active_plugins_backup = get_option( 'active_plugins', array() );

		add_filter( DetectorRegistry::FILTER, array( $this, 'register_fixture_detectors' ) );
	}

	protected function tearDown(): void {
		remove_all_filters( DetectorRegistry::FILTER );

		if ( null !== $this->active_plugins_backup ) {
			update_option( 'active_plugins', $this->active_plugins_backup );
		}

		parent::tearDown();
	}

	/**
	 * @param array<string, array<string, mixed>> $manifest Built-in manifest from the registry.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_fixture_detectors( array $manifest ): array {
		$manifest['fixture-a'] = array(
			'label'      => 'Fixture Switcher A',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_A,
				),
				array(
					'kind'   => SignatureKind::CLASS_NAME,
					'needle' => 'UMC_Fixture_Switcher_A',
				),
				array(
					'kind'   => SignatureKind::CONSTANT,
					'needle' => 'UMC_FIXTURE_SWITCHER_A_VERSION',
				),
				array(
					'kind'   => SignatureKind::FUNCTION,
					'needle' => 'umc_fixture_switcher_a_symbol',
				),
				array(
					'kind'   => SignatureKind::SHORTCODE,
					'needle' => 'umc_fixture_switcher_a',
				),
				array(
					'kind'   => SignatureKind::HOOK,
					'needle' => 'umc_fixture_switcher_a_hook',
				),
			),
		);

		$manifest['fixture-b'] = array(
			'label'      => 'Fixture Switcher B',
			'signatures' => array(
				array(
					'kind'   => SignatureKind::PLUGIN_PATH,
					'needle' => self::FIXTURE_B,
				),
			),
		);

		return $manifest;
	}

	private function activate( string $plugin ): void {
		require_once WP_PLUGIN_DIR . '/' . $plugin;

		$active   = get_option( 'active_plugins', array() );
		$active   = is_array( $active ) ? $active : array();
		$active[] = $plugin;
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );
	}

	private function detector(): ConflictDetector {
		return new ConflictDetector( new DetectorRegistry(), new WordPressEnvironmentProbe(), new ConflictScorer() );
	}

	public function test_active_fixture_a_reaches_high_confidence(): void {
		$this->activate( self::FIXTURE_A );

		$findings = $this->detector()->findings();

		$this->assertCount( 1, $findings );
		$this->assertSame( 'fixture-a', $findings[0]->id() );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
	}

	public function test_inactive_fixture_is_not_detected(): void {
		$findings = $this->detector()->findings();

		$this->assertSame( array(), $findings );
	}

	public function test_inert_fixture_is_never_detected(): void {
		$this->activate( self::FIXTURE_INERT );

		$this->assertSame( array(), $this->detector()->findings() );
	}

	public function test_plugin_path_only_fixture_reaches_high(): void {
		$this->activate( self::FIXTURE_B );

		$findings = $this->detector()->findings();

		$this->assertCount( 1, $findings );
		$this->assertSame( 'fixture-b', $findings[0]->id() );
		$this->assertSame( Confidence::HIGH, $findings[0]->confidence() );
	}

	public function test_class_only_partial_evidence_can_reach_medium(): void {
		add_filter(
			DetectorRegistry::FILTER,
			static function ( array $manifest ): array {
				$manifest['fixture-class-only'] = array(
					'label'      => 'Fixture Class Only',
					'signatures' => array(
						array(
							'kind'   => SignatureKind::CLASS_NAME,
							'needle' => 'Fixture_Switcher_A_Class_Only',
						),
					),
				);

				return $manifest;
			}
		);

		require_once __DIR__ . '/fixtures/probe-class-only-fixture.php';

		$findings = $this->detector()->findings();

		$this->assertCount( 1, $findings );
		$this->assertSame( 'fixture-class-only', $findings[0]->id() );
		$this->assertSame( Confidence::MEDIUM, $findings[0]->confidence() );
	}

	public function test_active_plugins_option_is_unchanged_after_detection(): void {
		$this->activate( self::FIXTURE_A );

		$before = get_option( 'active_plugins', array() );
		$this->detector()->findings();
		$after = get_option( 'active_plugins', array() );

		$this->assertSame( $before, $after );
	}

	public function test_umc_settings_option_is_unchanged_after_detection(): void {
		$this->activate( self::FIXTURE_A );

		$before = get_option( 'umc_settings', array() );
		$this->detector()->findings();
		$after = get_option( 'umc_settings', array() );

		$this->assertSame( $before, $after );
	}
}
