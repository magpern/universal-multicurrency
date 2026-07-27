<?php
/**
 * Structural guard: every version source agrees with docs/COMPATIBILITY.md
 * (WordPress-free).
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `docs/COMPATIBILITY.md` is the documented policy source. This test parses
 * its machine-readable summary once, then asserts the plugin header,
 * `composer.json`, `phpcs.xml.dist`, `CLAUDE.md` and `.github/workflows/ci.yml`
 * all agree with it — never the reverse, and never a second independent
 * table. Deliberate distinctions the policy relies on (a bare policy floor
 * versus an exercised patch coordinate; a pinned "tested up to" versus the
 * floating `ceiling` leg) are asserted directly, not merely assumed by which
 * column happens to be read. Each assertion was verified to fail when the
 * condition it guards is violated, not merely to pass today.
 */
final class CompatibilityMatrixTest extends TestCase {

	private const EXPECTED_AXES = array( 'PHP', 'WordPress', 'WooCommerce' );

	// ---------------------------------------------------------------------
	// Source readers.
	// ---------------------------------------------------------------------

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function doc_source(): string {
		return (string) file_get_contents( $this->root() . '/docs/COMPATIBILITY.md' );
	}

	private function header_source(): string {
		return (string) file_get_contents( $this->root() . '/universal-multicurrency.php' );
	}

	private function composer_json(): array {
		$decoded = json_decode( (string) file_get_contents( $this->root() . '/composer.json' ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	private function phpcs_source(): string {
		return (string) file_get_contents( $this->root() . '/phpcs.xml.dist' );
	}

	private function claude_md_source(): string {
		return (string) file_get_contents( $this->root() . '/CLAUDE.md' );
	}

	private function ci_yml_source(): string {
		return (string) file_get_contents( $this->root() . '/.github/workflows/ci.yml' );
	}

	// ---------------------------------------------------------------------
	// docs/COMPATIBILITY.md parsing — the policy source.
	// ---------------------------------------------------------------------

	/**
	 * Parses the fenced machine-readable table into `[axis => [min, tested]]`.
	 *
	 * Strict by design: throws rather than silently guessing on a missing
	 * row, a duplicate row, an unrecognised axis, a "minimum supported" cell
	 * carrying anything but a bare `X.Y` token, or a "tested up to" cell that
	 * contains a second version-like token after its leading value (the
	 * ambiguity a "10.9.4 ... 11.0.0-beta.2" cell would otherwise hide).
	 *
	 * @return array<string, array{min: string, tested: string}>
	 *
	 * @throws RuntimeException If the version block is missing, malformed, or ambiguous.
	 */
	private function parse_matrix( string $source ): array {
		if ( 1 !== preg_match( '/<!-- umc:versions:start -->(.*?)<!-- umc:versions:end -->/s', $source, $block_match ) ) {
			throw new RuntimeException( 'No machine-readable version block found.' );
		}

		preg_match_all(
			'/^\|\s*(PHP|WordPress|WooCommerce)\s*\|\s*([^|]*?)\s*\|\s*[^|]*?\s*\|\s*([^|]*?)\s*\|\s*[^|]*?\s*\|\s*[^|]*?\s*\|\s*$/m',
			$block_match[1],
			$rows,
			PREG_SET_ORDER
		);

		$axes_seen = array_column( $rows, 1 );

		if ( count( $axes_seen ) !== count( array_unique( $axes_seen ) ) ) {
			throw new RuntimeException( 'Duplicate axis row in the version block.' );
		}

		$missing = array_diff( self::EXPECTED_AXES, $axes_seen );
		if ( array() !== $missing ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
			throw new RuntimeException( 'Missing axis row(s): ' . implode( ', ', $missing ) );
		}

		$unexpected = array_diff( $axes_seen, self::EXPECTED_AXES );
		if ( array() !== $unexpected ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
			throw new RuntimeException( 'Unrecognised axis row(s): ' . implode( ', ', $unexpected ) );
		}

		$matrix = array();

		foreach ( $rows as $row ) {
			list( , $axis, $min_cell, $tested_cell ) = $row;

			if ( 1 !== preg_match( '/^\d+\.\d+$/', $min_cell ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
				throw new RuntimeException( "Malformed 'Minimum supported' cell for {$axis}: '{$min_cell}'." );
			}

			if ( 1 !== preg_match( '/^(\d+\.\d+(?:\.\d+)?)(.*)$/', $tested_cell, $tested_match ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
				throw new RuntimeException( "Malformed 'Tested up to' cell for {$axis}: '{$tested_cell}'." );
			}

			if ( 1 === preg_match( '/\d+\.\d+/', $tested_match[2] ) ) {
				throw new RuntimeException(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
					"Ambiguous 'Tested up to' cell for {$axis}: a second version-like token follows '{$tested_match[1]}' in '{$tested_cell}'."
				);
			}

			$matrix[ $axis ] = array(
				'min'    => $min_cell,
				'tested' => $tested_match[1],
			);
		}

		return $matrix;
	}

	private function doc_matrix(): array {
		return $this->parse_matrix( $this->doc_source() );
	}

	// ---------------------------------------------------------------------
	// Parser strictness — synthetic fixtures, not the real file.
	// ---------------------------------------------------------------------

	private function fixture( string $rows ): string {
		return "<!-- umc:versions:start -->\n"
			. "| Axis | Minimum supported | Recommended | Tested up to | CI-exercised | Label at minimum |\n"
			. "|---|---|---|---|---|---|\n"
			. $rows
			. "<!-- umc:versions:end -->\n";
	}

	public function test_parser_rejects_a_missing_axis_row(): void {
		$source = $this->fixture(
			"| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |\n"
			. "| WordPress | 6.5 | latest | 7.0.2 | 6.5.8, 7.0.2 | Supported |\n"
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Missing axis row(s): WooCommerce' );

		$this->parse_matrix( $source );
	}

	public function test_parser_rejects_a_duplicate_axis_row(): void {
		$source = $this->fixture(
			"| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |\n"
			. "| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |\n"
			. "| WordPress | 6.5 | latest | 7.0.2 | 6.5.8, 7.0.2 | Supported |\n"
			. "| WooCommerce | 8.2 | 10.x | 10.9.4 | 8.2.5, 10.9.4 | Supported |\n"
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Duplicate axis row' );

		$this->parse_matrix( $source );
	}

	public function test_parser_rejects_a_malformed_minimum_cell(): void {
		$source = $this->fixture(
			"| PHP | eight-point-one | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |\n"
			. "| WordPress | 6.5 | latest | 7.0.2 | 6.5.8, 7.0.2 | Supported |\n"
			. "| WooCommerce | 8.2 | 10.x | 10.9.4 | 8.2.5, 10.9.4 | Supported |\n"
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( "Malformed 'Minimum supported' cell for PHP" );

		$this->parse_matrix( $source );
	}

	public function test_parser_rejects_an_ambiguous_tested_cell(): void {
		$source = $this->fixture(
			"| PHP | 8.1 | 8.3 | 8.4 | 8.1, 8.3, 8.4 | Supported |\n"
			. "| WordPress | 6.5 | latest | 7.0.2 | 6.5.8, 7.0.2 | Supported |\n"
			. "| WooCommerce | 8.2 | 10.x | 10.9.4 (pinned); latest observed at 11.0.0-beta.2 | 8.2.5, 10.9.4 | Supported |\n"
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( "Ambiguous 'Tested up to' cell for WooCommerce" );

		$this->parse_matrix( $source );
	}

	public function test_the_real_document_parses_into_exactly_three_axes(): void {
		$matrix = $this->doc_matrix();

		$this->assertSame( self::EXPECTED_AXES, array_keys( $matrix ) );

		foreach ( $matrix as $axis => $values ) {
			$this->assertArrayHasKey( 'min', $values, "{$axis} row missing a parsed minimum." );
			$this->assertArrayHasKey( 'tested', $values, "{$axis} row missing a parsed tested-up-to value." );
		}
	}

	// ---------------------------------------------------------------------
	// .github/workflows/ci.yml — leg and unit-matrix extraction.
	// ---------------------------------------------------------------------

	/**
	 * @return array<string, array{php: string, wp_phpunit: string, wc: string}>
	 */
	private function ci_legs(): array {
		preg_match_all(
			'/- leg:\s*(\S+)\s*'
				. '\n\s*php:\s*\'([^\']*)\'\s*'
				. '\n\s*wp_phpunit:\s*\'([^\']*)\'\s*'
				. '\n\s*wc:\s*\'([^\']*)\'/',
			$this->ci_yml_source(),
			$matches,
			PREG_SET_ORDER
		);

		$legs = array();

		foreach ( $matches as $match ) {
			$legs[ $match[1] ] = array(
				'php'        => $match[2],
				'wp_phpunit' => $match[3],
				'wc'         => $match[4],
			);
		}

		return $legs;
	}

	/**
	 * @return array<int, string>
	 */
	private function ci_unit_php_versions(): array {
		preg_match( '/php:\s*\[([^\]]+)\]/', $this->ci_yml_source(), $match );

		return array_map(
			static function ( string $version ): string {
				return trim( $version, " \t'\"" );
			},
			explode( ',', $match[1] ?? '' )
		);
	}

	// ---------------------------------------------------------------------
	// PHP floor.
	// ---------------------------------------------------------------------

	public function test_php_floor_matches_the_plugin_header(): void {
		preg_match( '/Requires PHP:\s*(\S+)/', $this->header_source(), $match );

		$this->assertSame( $this->doc_matrix()['PHP']['min'], $match[1] ?? null );
	}

	public function test_php_floor_matches_composer_require(): void {
		$require = $this->composer_json()['require']['php'] ?? '';

		preg_match( '/^>=\s*(\d+\.\d+)$/', $require, $match );

		$this->assertNotEmpty( $match, "composer.json require.php ('{$require}') is not in the expected '>=X.Y' form." );
		$this->assertSame( $this->doc_matrix()['PHP']['min'], $match[1] );
	}

	public function test_php_floor_matches_composer_platform(): void {
		$platform = $this->composer_json()['config']['platform']['php'] ?? '';

		$this->assertSame( $this->doc_matrix()['PHP']['min'] . '.99', $platform );
	}

	public function test_php_floor_matches_phpcs_test_version(): void {
		preg_match( '/testVersion"\s+value="([^"]+)"/', $this->phpcs_source(), $match );

		$this->assertSame( $this->doc_matrix()['PHP']['min'] . '-', $match[1] ?? null );
	}

	public function test_php_floor_is_exercised_by_the_floor_and_mixed_php_floor_legs(): void {
		$legs = $this->ci_legs();
		$min  = $this->doc_matrix()['PHP']['min'];

		$this->assertSame( $min, $legs['floor']['php'] ?? null );
		$this->assertSame( $min, $legs['mixed-php-floor']['php'] ?? null );
	}

	public function test_php_floor_is_exercised_by_the_unit_matrix(): void {
		$this->assertContains( $this->doc_matrix()['PHP']['min'], $this->ci_unit_php_versions() );
	}

	public function test_php_floor_matches_claude_md(): void {
		$source = $this->claude_md_source();

		$this->assertStringContainsString( 'docs/COMPATIBILITY.md', $source );
		$this->assertStringContainsString( 'CompatibilityMatrixTest', $source );
		$this->assertStringNotContainsString( 'PHP ' . $this->doc_matrix()['PHP']['min'], $source );
	}

	public function test_php_ceiling_does_not_affect_the_declared_floor(): void {
		$doc = $this->doc_matrix()['PHP'];

		// The floor-determining fields must track `min`; none of them may
		// have silently picked up the ceiling instead. Meaningful only
		// because min and tested genuinely differ today.
		$this->assertNotSame( $doc['min'], $doc['tested'] );

		preg_match( '/^>=\s*(\d+\.\d+)$/', $this->composer_json()['require']['php'] ?? '', $match );

		$this->assertSame( $doc['min'], $match[1] ?? null );
		$this->assertNotSame( $doc['tested'], $match[1] ?? null );
	}

	// ---------------------------------------------------------------------
	// WordPress floor.
	// ---------------------------------------------------------------------

	public function test_wordpress_floor_matches_the_plugin_header(): void {
		preg_match( '/Requires at least:\s*(\S+)/', $this->header_source(), $match );

		$this->assertSame( $this->doc_matrix()['WordPress']['min'], $match[1] ?? null );
	}

	public function test_wordpress_floor_matches_phpcs_minimum_wp_version(): void {
		preg_match( '/minimum_wp_version"\s+value="([^"]+)"/', $this->phpcs_source(), $match );

		$this->assertSame( $this->doc_matrix()['WordPress']['min'], $match[1] ?? null );
	}

	public function test_wordpress_floor_is_exercised_by_the_floor_and_mixed_wp_floor_legs(): void {
		$legs = $this->ci_legs();
		$min  = $this->doc_matrix()['WordPress']['min'];

		$this->assertSame( $min . '.*', $legs['floor']['wp_phpunit'] ?? null );
		$this->assertSame( $min . '.*', $legs['mixed-wp-floor']['wp_phpunit'] ?? null );
	}

	public function test_wordpress_floor_matches_claude_md(): void {
		$source = $this->claude_md_source();

		$this->assertStringContainsString( 'docs/COMPATIBILITY.md', $source );
		$this->assertStringNotContainsString(
			'WordPress ' . $this->doc_matrix()['WordPress']['min'],
			$source
		);
	}

	public function test_wordpress_floor_pin_is_distinct_from_the_bare_policy_floor(): void {
		$doc = $this->doc_matrix()['WordPress'];
		$pin = $this->ci_legs()['floor']['wp_phpunit'] ?? '';

		// Three distinct shapes for the same fact: the bare policy floor
		// ("6.5"), the composer constraint that pins it ("6.5.*"), and the
		// observed patch it actually resolved to ("6.5.8"). None may collapse
		// into another.
		$this->assertSame( '6.5', $doc['min'] );
		$this->assertSame( '6.5.*', $pin );
		$this->assertStringContainsString( '6.5.8', $this->doc_source() );
	}

	/**
	 * `composer.json`'s `require-dev` entry for `wp-phpunit/wp-phpunit` is a
	 * second, independent place a WordPress version constraint lives (the
	 * dependency's own resolution range, distinct from `phpcs.xml.dist`'s
	 * declared floor or the CI legs' explicit pin). Each `||`-separated
	 * branch is a caret constraint (`^X.Y`); this extracts each branch's
	 * floor.
	 *
	 * @return array<int, string>
	 *
	 * @throws RuntimeException If a branch is not a bare `^X.Y` caret constraint.
	 */
	private function wp_phpunit_branch_floors(): array {
		$constraint = (string) ( $this->composer_json()['require-dev']['wp-phpunit/wp-phpunit'] ?? '' );
		$floors     = array();

		foreach ( array_map( 'trim', explode( '||', $constraint ) ) as $branch ) {
			if ( 1 !== preg_match( '/^\^(\d+\.\d+)$/', $branch, $match ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer diagnostic, never echoed to a browser.
				throw new RuntimeException( "Malformed wp-phpunit constraint branch: '{$branch}'." );
			}

			$floors[] = $match[1];
		}

		return $floors;
	}

	public function test_wp_phpunit_constraint_includes_a_branch_at_the_documented_wordpress_floor(): void {
		$this->assertContains( $this->doc_matrix()['WordPress']['min'], $this->wp_phpunit_branch_floors() );
	}

	public function test_wp_phpunit_constraint_lowest_branch_is_not_above_the_documented_floor(): void {
		$floors  = $this->wp_phpunit_branch_floors();
		$doc_min = $this->doc_matrix()['WordPress']['min'];

		$lowest = null;
		foreach ( $floors as $floor ) {
			if ( null === $lowest || version_compare( $floor, $lowest, '<' ) ) {
				$lowest = $floor;
			}
		}

		$this->assertNotNull( $lowest, 'wp-phpunit constraint has no parseable branch.' );
		$this->assertTrue(
			version_compare( $lowest, $doc_min, '<=' ),
			"wp-phpunit's lowest branch ('{$lowest}') must not exceed the documented WordPress floor ('{$doc_min}')."
		);
	}

	public function test_wp_phpunit_upper_branch_is_not_conflated_with_the_tested_coordinate(): void {
		// The constraint's upper branch ('^7.0') is a dependency-resolution
		// range, not the exact patch this milestone observed in CI
		// ('7.0.2') — a resolution floor and an observed patch are different
		// kinds of claims, and this must never assert them equal.
		$floors = $this->wp_phpunit_branch_floors();

		$upper = null;
		foreach ( $floors as $floor ) {
			if ( null === $upper || version_compare( $floor, $upper, '>' ) ) {
				$upper = $floor;
			}
		}

		$this->assertNotNull( $upper, 'wp-phpunit constraint has no parseable branch.' );
		$this->assertNotSame( $this->doc_matrix()['WordPress']['tested'], $upper );
	}

	// ---------------------------------------------------------------------
	// WooCommerce floor.
	// ---------------------------------------------------------------------

	public function test_woocommerce_floor_matches_the_plugin_header(): void {
		preg_match( '/WC requires at least:\s*(\S+)/', $this->header_source(), $match );

		$this->assertSame( $this->doc_matrix()['WooCommerce']['min'], $match[1] ?? null );
	}

	public function test_woocommerce_floor_is_exercised_by_the_floor_leg(): void {
		$wc  = $this->ci_legs()['floor']['wc'] ?? '';
		$min = $this->doc_matrix()['WooCommerce']['min'];

		$this->assertStringStartsWith( $min . '.', $wc, "Floor leg's wc ('{$wc}') must be a patch of the declared floor ('{$min}')." );
	}

	public function test_woocommerce_floor_matches_claude_md(): void {
		$source = $this->claude_md_source();

		$this->assertStringContainsString( 'docs/COMPATIBILITY.md', $source );
		$this->assertStringNotContainsString(
			'WooCommerce ' . $this->doc_matrix()['WooCommerce']['min'],
			$source
		);
	}

	public function test_woocommerce_floor_patch_coordinate_is_distinct_from_the_policy_floor(): void {
		$min = $this->doc_matrix()['WooCommerce']['min'];
		$wc  = $this->ci_legs()['floor']['wc'] ?? '';

		// "8.2" (policy) and "8.2.5" (the exact patch CI exercises) are
		// deliberately different strings expressing the same floor at
		// different precision — never collapse them to equality.
		$this->assertNotSame( $min, $wc );
		$this->assertStringStartsWith( $min . '.', $wc );
	}

	// ---------------------------------------------------------------------
	// WooCommerce tested / current, and the ceiling distinction.
	// ---------------------------------------------------------------------

	public function test_woocommerce_tested_matches_the_plugin_header_at_minor_precision(): void {
		preg_match( '/WC tested up to:\s*(\S+)/', $this->header_source(), $match );
		$header_value = $match[1] ?? '';

		preg_match( '/^(\d+\.\d+)/', $this->doc_matrix()['WooCommerce']['tested'], $doc_minor );

		$this->assertSame( $doc_minor[1] ?? null, $header_value );
	}

	public function test_woocommerce_tested_matches_the_pinned_current_leg(): void {
		$this->assertSame(
			$this->doc_matrix()['WooCommerce']['tested'],
			$this->ci_legs()['current']['wc'] ?? null
		);
	}

	public function test_woocommerce_ceiling_is_not_conflated_with_tested_up_to(): void {
		$ceiling_wc = $this->ci_legs()['ceiling']['wc'] ?? null;
		$tested     = $this->doc_matrix()['WooCommerce']['tested'];

		// The ceiling leg floats on the literal token "latest" — never a
		// version — and the parsed "tested up to" value must never contain
		// it (the parser's ambiguity check already forbids a second version
		// token there; this asserts the specific floating token by name).
		$this->assertSame( 'latest', $ceiling_wc );
		$this->assertStringNotContainsString( 'latest', $tested );
		$this->assertNotSame( $ceiling_wc, $tested );
	}

	// ---------------------------------------------------------------------
	// Built-in detector slugs ↔ docs/COMPATIBILITY.md § Known incompatible.
	// ---------------------------------------------------------------------

	/**
	 * @return array<int, string>
	 *
	 * @throws RuntimeException If the incompatible block is missing or malformed.
	 */
	private function incompatible_slugs_from_doc(): array {
		$source = $this->doc_source();

		if ( 1 !== preg_match( '/<!-- umc:incompatible:start -->(.*?)<!-- umc:incompatible:end -->/s', $source, $block ) ) {
			throw new RuntimeException( 'Missing umc:incompatible fenced block in docs/COMPATIBILITY.md.' );
		}

		$slugs = array();

		foreach ( preg_split( '/\R/', $block[1] ) as $line ) {
			if ( 1 !== preg_match( '/^\|\s*[^|]+\s*\|\s*`([a-z0-9_-]{2,32})`\s*\|/', $line, $match ) ) {
				continue;
			}

			$slug = $match[1];

			if ( in_array( $slug, $slugs, true ) ) {
				throw new RuntimeException( 'Duplicate incompatible slug row in docs/COMPATIBILITY.md.' );
			}

			$slugs[] = $slug;
		}

		if ( array() === $slugs ) {
			throw new RuntimeException( 'No incompatible slugs parsed from docs/COMPATIBILITY.md.' );
		}

		return $slugs;
	}

	public function test_every_incompatible_doc_slug_exists_in_the_manifest(): void {
		$manifest = array_keys( \UMC\Diagnostics\DetectorManifest::manifest() );

		foreach ( $this->incompatible_slugs_from_doc() as $slug ) {
			$this->assertContains(
				$slug,
				$manifest,
				"docs/COMPATIBILITY.md lists incompatible slug '{$slug}' but DetectorManifest has no matching id."
			);
		}
	}

	public function test_every_manifest_detector_is_listed_as_incompatible(): void {
		$doc_slugs = $this->incompatible_slugs_from_doc();

		foreach ( array_keys( \UMC\Diagnostics\DetectorManifest::manifest() ) as $slug ) {
			$this->assertContains(
				$slug,
				$doc_slugs,
				"DetectorManifest id '{$slug}' is missing from docs/COMPATIBILITY.md § Known incompatible."
			);
		}
	}
}
