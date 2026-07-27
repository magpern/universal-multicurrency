<?php
/**
 * Structural guard: the supported-version CI matrix stays exactly as wide
 * as approved (WordPress-free).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Parses `.github/workflows/ci.yml` as text rather than as YAML — the
 * project has no YAML-parsing dependency, and a targeted string scan is the
 * same technique the plan's own drift test (docs/COMPATIBILITY.md, a later
 * commit) is designed to use. Pins the five integration coordinates, the
 * bounded floor exclusion, and the unit job's PHP axis, so a coordinate or
 * an exclusion cannot drift without this test failing. Each assertion was
 * verified to fail when the condition it guards is violated, not merely to
 * pass today.
 */
final class CiMatrixGuardTest extends TestCase {

	/**
	 * Expected integration legs, keyed by `leg`.
	 *
	 * @var array<string, array{php: string, wp_phpunit: string, wc: string, exclude_group: string}>
	 */
	private const EXPECTED_LEGS = array(
		'floor'           => array(
			'php'           => '8.1',
			'wp_phpunit'    => '6.5.*',
			'wc'            => '8.2.5',
			'exclude_group' => 'wc-order-route-unavailable',
		),
		'current'         => array(
			'php'           => '8.3',
			'wp_phpunit'    => '',
			'wc'            => '10.9.4',
			'exclude_group' => '',
		),
		'mixed-php-floor' => array(
			'php'           => '8.1',
			'wp_phpunit'    => '',
			'wc'            => '10.9.4',
			'exclude_group' => '',
		),
		'mixed-wp-floor'  => array(
			'php'           => '8.3',
			'wp_phpunit'    => '6.5.*',
			'wc'            => '10.9.4',
			'exclude_group' => '',
		),
		'ceiling'         => array(
			'php'           => '8.4',
			'wp_phpunit'    => '',
			'wc'            => 'latest',
			'exclude_group' => '',
		),
	);

	/**
	 * The only group name any leg may exclude.
	 */
	private const APPROVED_EXCLUSION_GROUP = 'wc-order-route-unavailable';

	/**
	 * Expected PHP axis for the unit job.
	 *
	 * @var array<int, string>
	 */
	private const EXPECTED_UNIT_PHP_MATRIX = array( '8.1', '8.3', '8.4' );

	private function workflow_source(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/.github/workflows/ci.yml' );
	}

	/**
	 * @return array<string, array{php: string, wp_phpunit: string, wc: string, exclude_group: string}>
	 */
	private function parsed_legs(): array {
		$pattern = '/- leg:\s*(\S+)\s*'
			. '\n\s*php:\s*\'([^\']*)\'\s*'
			. '\n\s*wp_phpunit:\s*\'([^\']*)\'\s*'
			. '\n\s*wc:\s*\'([^\']*)\'\s*'
			. '\n\s*exclude_group:\s*\'([^\']*)\'/';

		preg_match_all( $pattern, $this->workflow_source(), $matches, PREG_SET_ORDER );

		$legs = array();

		foreach ( $matches as $match ) {
			$legs[ $match[1] ] = array(
				'php'           => $match[2],
				'wp_phpunit'    => $match[3],
				'wc'            => $match[4],
				'exclude_group' => $match[5],
			);
		}

		return $legs;
	}

	public function test_all_five_integration_legs_exist(): void {
		$legs = $this->parsed_legs();

		$this->assertSame(
			array_keys( self::EXPECTED_LEGS ),
			array_keys( $legs ),
			'The integration matrix must define exactly the five approved legs, in the approved order.'
		);
	}

	/**
	 * @dataProvider leg_names
	 */
	public function test_leg_coordinate_matches_the_approved_matrix( string $leg ): void {
		$legs = $this->parsed_legs();

		$this->assertArrayHasKey( $leg, $legs, "Leg '{$leg}' is missing from ci.yml." );

		$this->assertSame(
			self::EXPECTED_LEGS[ $leg ],
			$legs[ $leg ],
			"Leg '{$leg}' does not match its approved (php, wp_phpunit, wc, exclude_group) coordinate."
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function leg_names(): array {
		return array(
			'floor'           => array( 'floor' ),
			'current'         => array( 'current' ),
			'mixed-php-floor' => array( 'mixed-php-floor' ),
			'mixed-wp-floor'  => array( 'mixed-wp-floor' ),
			'ceiling'         => array( 'ceiling' ),
		);
	}

	public function test_only_the_floor_leg_excludes_a_group(): void {
		$legs      = $this->parsed_legs();
		$excluding = array();

		foreach ( $legs as $leg => $coordinate ) {
			if ( '' !== $coordinate['exclude_group'] ) {
				$excluding[ $leg ] = $coordinate['exclude_group'];
			}
		}

		$this->assertSame(
			array( 'floor' => self::APPROVED_EXCLUSION_GROUP ),
			$excluding,
			'Only the floor leg may exclude a test group, and only the approved group.'
		);
	}

	public function test_no_unapproved_exclusion_mechanism_is_used(): void {
		$source = $this->workflow_source();

		$this->assertStringNotContainsString(
			'wc-shape',
			$source,
			'wc-shape is reserved for a genuine response-shape incompatibility; none has been recorded in ci.yml.'
		);

		$this->assertDoesNotMatchRegularExpression(
			'/--filter\b/',
			$source,
			'ci.yml must not filter to arbitrary tests; the only sanctioned narrowing is --exclude-group on the floor leg.'
		);

		$this->assertSame(
			1,
			preg_match_all( '/--exclude-group\b/', $source ),
			'Exactly one --exclude-group invocation is expected, gated behind the floor-only shell conditional.'
		);
	}

	public function test_no_whole_file_or_suite_is_excluded_from_the_integration_run(): void {
		$config = (string) file_get_contents( dirname( __DIR__, 2 ) . '/phpunit-integration.xml.dist' );

		$this->assertStringNotContainsString( '<exclude', $config, 'The integration suite must not exclude any file or directory.' );
		$this->assertStringContainsString(
			'<directory suffix="Test.php">tests/integration</directory>',
			$config,
			'The integration suite must still cover the whole tests/integration directory.'
		);
	}

	public function test_unit_php_matrix_matches_policy(): void {
		$source = $this->workflow_source();

		$this->assertMatchesRegularExpression( '/php:\s*\[([^\]]+)\]/', $source, 'Unit job PHP matrix not found.' );

		preg_match( '/php:\s*\[([^\]]+)\]/', $source, $match );

		$php_versions = array_map(
			static function ( string $version ): string {
				return trim( $version, " \t'\"" );
			},
			explode( ',', $match[1] )
		);

		$this->assertSame(
			self::EXPECTED_UNIT_PHP_MATRIX,
			$php_versions,
			'The unit job PHP axis must match the floor/current/ceiling policy exactly.'
		);
	}

	public function test_ceiling_leg_is_non_blocking(): void {
		$this->assertMatchesRegularExpression(
			'/continue-on-error:\s*\$\{\{\s*matrix\.leg\s*==\s*\'ceiling\'\s*\}\}/',
			$this->workflow_source(),
			'The ceiling leg must be continue-on-error so upstream drift warns without blocking merges.'
		);
	}
}
