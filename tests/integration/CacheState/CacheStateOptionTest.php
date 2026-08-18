<?php
/**
 * Integration tests for the umc_cache_state option round-trip.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CacheState;

use UMC\CacheState\CacheStateStore;
use WP_UnitTestCase;

/**
 * Exercises CacheStateStore against the real options table.
 */
final class CacheStateOptionTest extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( CacheStateStore::OPTION );

		parent::tear_down();
	}

	public function test_absent_option_yields_defaults(): void {
		delete_option( CacheStateStore::OPTION );

		$store = new CacheStateStore();

		$this->assertFalse( $store->is_enrolled() );
		$this->assertSame( '', $store->acknowledged_hash() );
		$this->assertSame( 0, $store->acknowledged_at() );
	}

	public function test_record_persists_to_the_real_option(): void {
		$store = new CacheStateStore();
		$store->record( 'a1b2c3d4e5f60718', 1_700_000_000 );

		$raw = get_option( CacheStateStore::OPTION, false );
		$this->assertIsArray( $raw );
		$this->assertSame( 'a1b2c3d4e5f60718', $raw['acknowledged_hash'] );
		$this->assertSame( 1_700_000_000, $raw['acknowledged_at'] );

		$reloaded = new CacheStateStore();
		$this->assertTrue( $reloaded->is_enrolled() );
		$this->assertSame( 'a1b2c3d4e5f60718', $reloaded->acknowledged_hash() );
	}

	public function test_re_save_is_stable(): void {
		$store = new CacheStateStore();
		$store->record( 'a1b2c3d4e5f60718', 1_700_000_000 );
		$store->record( 'a1b2c3d4e5f60718', 1_700_000_000 );

		$this->assertSame( 'a1b2c3d4e5f60718', ( new CacheStateStore() )->acknowledged_hash() );
	}

	public function test_option_is_registered_with_autoload_disabled(): void {
		( new CacheStateStore() )->record( 'a1b2c3d4e5f60718', time() );

		$alloptions = wp_load_alloptions( true );

		$this->assertArrayNotHasKey(
			CacheStateStore::OPTION,
			$alloptions,
			'umc_cache_state must not be registered with autoload enabled.'
		);
	}
}
