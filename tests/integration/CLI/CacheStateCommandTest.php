<?php
/**
 * Integration tests for the wp umc cache-state CLI.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Integration\CLI;

use UMC\CacheState\CacheStateService;
use UMC\CacheState\CacheStateStore;
use UMC\CLI\CacheStateCommand;
use UMC\Currency;
use UMC\CurrencyRegistry;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;
use UMC\Tests\Support\WpCliExitException;
use WP_CLI;
use WP_CLI\Utils;
use WP_UnitTestCase;

/**
 * @covers \UMC\CLI\CacheStateCommand
 */
final class CacheStateCommandTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		WP_CLI::reset();
		Utils::reset();
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION );
		delete_option( CacheStateStore::OPTION );

		parent::tear_down();
	}

	private function service(): CacheStateService {
		$settings = new Settings( array( 'geo' => array( 'enabled' => false ) ) );
		$registry = new CurrencyRegistry( $settings, new Currency( 'EUR', 2 ) );

		return new CacheStateService(
			$registry,
			new GeoDetectionSettingsRepository( $settings ),
			$settings,
			new CacheStateStore()
		);
	}

	private function command( CacheStateService $service ): CacheStateCommand {
		return new CacheStateCommand( $service );
	}

	public function test_status_json_output_parses_and_matches_report_exactly(): void {
		$service = $this->service();
		$command = $this->command( $service );

		ob_start();
		$command->status( array(), array( 'format' => 'json' ) );
		$output = (string) ob_get_clean();

		$decoded = json_decode( trim( $output ), true );
		$this->assertIsArray( $decoded );
		$this->assertSame( $service->report()->to_array(), $decoded );
	}

	public function test_status_table_format_renders_field_value_rows(): void {
		$service = $this->service();

		ob_start();
		$this->command( $service )->status();
		ob_get_clean();

		$this->assertNotEmpty( Utils::$last_items );
		$fields = array_column( Utils::$last_items, 'field' );
		$this->assertContains( 'state_hash', $fields );
		$this->assertContains( 'reconciliation_required', $fields );
	}

	public function test_status_always_succeeds_regardless_of_reconciliation_required(): void {
		$service = $this->service();

		// Never acknowledged: reconciliation_required is true.
		$this->assertTrue( $service->report()->reconciliation_required() );

		ob_start();
		$this->command( $service )->status( array(), array( 'format' => 'json' ) );
		ob_get_clean();

		// No exception thrown means WP_CLI::error()/halt() were never called.
		$this->assertSame( array(), WP_CLI::$warning_messages );
	}

	public function test_acknowledge_of_current_hash_persists_and_enrolls(): void {
		$service = $this->service();
		$hash    = $service->report()->state_hash();

		$this->command( $service )->acknowledge( array( $hash ) );

		$this->assertNotEmpty( WP_CLI::$success_messages );
		$this->assertTrue( ( new CacheStateStore() )->is_enrolled() );
		$this->assertSame( $hash, ( new CacheStateStore() )->acknowledged_hash() );
	}

	public function test_acknowledge_of_stale_hash_throws_and_leaves_option_untouched(): void {
		$service = $this->service();

		$this->expectException( WpCliExitException::class );

		try {
			$this->command( $service )->acknowledge( array( '0000000000000000' ) );
		} finally {
			$this->assertFalse( get_option( CacheStateStore::OPTION, false ) );
		}
	}

	public function test_acknowledge_leaves_umc_settings_byte_identical(): void {
		$before = ( new Settings( array( 'geo' => array( 'enabled' => false ) ) ) );
		$before->save( $before->get() );
		$snapshot = get_option( Settings::OPTION );

		$service = $this->service();
		$hash    = $service->report()->state_hash();
		$this->command( $service )->acknowledge( array( $hash ) );

		$this->assertSame( $snapshot, get_option( Settings::OPTION ) );
	}

	public function test_no_check_subcommand_is_registered(): void {
		$this->assertFalse( method_exists( CacheStateCommand::class, 'check' ) );
	}
}
