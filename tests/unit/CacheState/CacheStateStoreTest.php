<?php
/**
 * Unit tests for the cache-state acknowledgement store.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\CacheState;

use PHPUnit\Framework\TestCase;
use UMC\CacheState\CacheStateStore;

/**
 * Tests the cache-state acknowledgement store.
 */
final class CacheStateStoreTest extends TestCase {

	public function test_defaults_shape(): void {
		$this->assertSame(
			array(
				'schema_version'    => CacheStateStore::SCHEMA_VERSION,
				'acknowledged_hash' => '',
				'acknowledged_at'   => 0,
			),
			CacheStateStore::defaults()
		);
	}

	public function test_sanitize_of_absent_value_falls_back_to_defaults(): void {
		$this->assertSame( CacheStateStore::defaults(), CacheStateStore::sanitize( false ) );
	}

	public function test_sanitize_of_malformed_value_falls_back_to_defaults(): void {
		$this->assertSame( CacheStateStore::defaults(), CacheStateStore::sanitize( 'not-an-array' ) );
	}

	public function test_sanitize_rejects_a_non_hex_acknowledged_hash(): void {
		$clean = CacheStateStore::sanitize(
			array(
				'acknowledged_hash' => 'not a valid hash at all',
				'acknowledged_at'   => 1700000000,
			)
		);

		$this->assertSame( '', $clean['acknowledged_hash'] );
	}

	public function test_sanitize_accepts_a_valid_hex_hash(): void {
		$clean = CacheStateStore::sanitize(
			array(
				'acknowledged_hash' => 'a1b2c3d4e5f60718',
				'acknowledged_at'   => 1700000000,
			)
		);

		$this->assertSame( 'a1b2c3d4e5f60718', $clean['acknowledged_hash'] );
		$this->assertSame( 1700000000, $clean['acknowledged_at'] );
	}

	public function test_sanitize_is_stable_under_re_sanitization(): void {
		$once  = CacheStateStore::sanitize(
			array(
				'acknowledged_hash' => 'a1b2c3d4e5f60718',
				'acknowledged_at'   => 5,
			)
		);
		$twice = CacheStateStore::sanitize( $once );

		$this->assertSame( $once, $twice );
	}

	public function test_is_enrolled_is_false_on_defaults(): void {
		$store = new CacheStateStore();

		$this->assertFalse( $store->is_enrolled() );
		$this->assertSame( '', $store->acknowledged_hash() );
	}

	public function test_record_sets_hash_and_timestamp_immediately_in_memory(): void {
		$store = new CacheStateStore();

		$store->record( 'a1b2c3d4e5f60718', 1700000000 );

		$this->assertTrue( $store->is_enrolled() );
		$this->assertSame( 'a1b2c3d4e5f60718', $store->acknowledged_hash() );
		$this->assertSame( 1700000000, $store->acknowledged_at() );
	}
}
