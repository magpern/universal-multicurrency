<?php
/**
 * Unit tests for NoticeDismissal storage rules.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\NoticeDismissal;

/**
 * Covers expiry, cap, and fingerprint validation without WordPress bootstrap.
 */
final class NoticeDismissalTest extends TestCase {

	public function test_sanitize_storage_drops_expired_entries(): void {
		$now = 1_700_000_000;

		$stored = array(
			'aaaaaaaaaaaaaaaa' => $now - NoticeDismissal::EXPIRY_SECONDS - 1,
			'bbbbbbbbbbbbbbbb' => $now - 10,
		);

		$this->assertSame(
			array( 'bbbbbbbbbbbbbbbb' => $now - 10 ),
			NoticeDismissal::sanitize_storage( $stored, $now )
		);
	}

	public function test_sanitize_storage_caps_at_twenty_entries(): void {
		$now    = 1_700_000_000;
		$stored = array();

		for ( $i = 0; $i < 25; $i++ ) {
			$stored[ sprintf( '%016x', $i ) ] = $now - ( 25 - $i );
		}

		$clean = NoticeDismissal::sanitize_storage( $stored, $now );

		$this->assertCount( NoticeDismissal::MAX_ENTRIES, $clean );
		$this->assertSame( $now - 1, $clean[ sprintf( '%016x', 24 ) ] );
		$this->assertArrayNotHasKey( sprintf( '%016x', 0 ), $clean );
	}

	public function test_with_dismissal_records_and_re_sanitizes(): void {
		$now = 1_700_000_000;

		$result = NoticeDismissal::with_dismissal( array(), 'abcabcabcabcabca', $now );

		$this->assertSame( array( 'abcabcabcabcabca' => $now ), $result );
	}

	public function test_is_valid_fingerprint_accepts_sixteen_hex_chars_only(): void {
		$this->assertTrue( NoticeDismissal::is_valid_fingerprint( '0123456789abcdef' ) );
		$this->assertFalse( NoticeDismissal::is_valid_fingerprint( '0123456789abcde' ) );
		$this->assertFalse( NoticeDismissal::is_valid_fingerprint( '0123456789abcdefg' ) );
		$this->assertFalse( NoticeDismissal::is_valid_fingerprint( 'ZZZZZZZZZZZZZZZZ' ) );
	}
}
