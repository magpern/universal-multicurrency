<?php
/**
 * Structural guard: persisted-key inventory cannot drift from implementation or docs.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use UMC\Admin\Geo\GeoSandboxRecentStore;
use UMC\Admin\GeoSandboxController;
use UMC\Cart\CartRecalculation;
use UMC\Checkout\CheckoutTransitionStateRepository;
use UMC\CurrencyContext;
use UMC\CurrencySwitcher;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Diagnostics\NoticeDismissal;
use UMC\Order\OrderSnapshot;
use UMC\Order\RefundSnapshot;
use UMC\PersistedKeys;
use UMC\Rates\RateUpdateState;
use UMC\Settings;
use UMC\StoreApi\CartExtensionData;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * `PersistedKeys` is the code source of truth; `docs/PERSISTED_DATA.md` is the
 * human contract. This test binds them to the owning class constants.
 */
final class PersistedKeysInventoryTest extends TestCase {

	use SourceGuardTrait;

	private function root(): string {
		return dirname( __DIR__, 2 );
	}

	private function doc_source(): string {
		return (string) file_get_contents( $this->root() . '/docs/PERSISTED_DATA.md' );
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @throws RuntimeException If the machine-readable block is missing or invalid.
	 */
	private function documented_inventory(): array {
		$source = $this->doc_source();

		if ( ! preg_match( '/```umc:persisted-inventory\s*\n(\{.*?\})\s*\n```/s', $source, $matches ) ) {
			throw new RuntimeException( 'Missing umc:persisted-inventory fenced block in docs/PERSISTED_DATA.md.' );
		}

		$decoded = json_decode( $matches[1], true );

		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Invalid JSON in docs/PERSISTED_DATA.md umc:persisted-inventory block.' );
		}

		return $decoded;
	}

	/**
	 * @param array<int, string> $keys String keys to sort.
	 *
	 * @return array<int, string>
	 */
	private function sorted( array $keys ): array {
		$sorted = $keys;
		sort( $sorted, SORT_STRING );

		return $sorted;
	}

	/**
	 * @return list<string>
	 */
	private function class_string_constants( string $class_name, string $prefix ): array {
		$reflection = new ReflectionClass( $class_name );
		$values     = array();

		foreach ( $reflection->getConstants() as $name => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( ! str_starts_with( (string) $name, $prefix ) ) {
				continue;
			}

			$values[] = $value;
		}

		return $this->sorted( $values );
	}

	public function test_documented_inventory_matches_persisted_keys_class(): void {
		$documented = $this->documented_inventory();
		$expected   = PersistedKeys::inventory();

		$this->assertSame( $expected, $documented );
	}

	public function test_option_keys_match_persisted_inventory(): void {
		$this->assertSame(
			array( Settings::OPTION, RateUpdateState::OPTION ),
			PersistedKeys::option_keys()
		);
	}

	public function test_order_meta_keys_match_order_snapshot_constants(): void {
		$this->assertSame(
			$this->class_string_constants( OrderSnapshot::class, 'META_' ),
			$this->sorted( PersistedKeys::order_meta_keys() )
		);
	}

	public function test_refund_meta_keys_match_refund_snapshot_constants(): void {
		$this->assertSame(
			$this->class_string_constants( RefundSnapshot::class, 'META_' ),
			$this->sorted( PersistedKeys::refund_meta_keys() )
		);
	}

	public function test_user_meta_keys_match_notice_dismissal_constant(): void {
		$this->assertSame(
			array(
				NoticeDismissal::META_KEY,
				GeoSandboxController::RESULT_META,
				GeoSandboxRecentStore::META_KEY,
			),
			PersistedKeys::user_meta_keys()
		);
	}

	public function test_session_keys_match_currency_context_and_cart_recalculation(): void {
		$this->assertSame(
			array(
				CurrencyContext::SESSION_KEY,
				CartRecalculation::SESSION_KEY,
				CheckoutTransitionStateRepository::SESSION_KEY,
				CheckoutTransitionStateRepository::SESSION_NOTICE_KEY,
				CurrencySwitcher::SESSION_MANUAL_SELECTION,
				CurrencySwitcher::SESSION_CURRENCY_ORIGIN,
				GeoCurrencyDecisionService::SESSION_GEO_APPLIED,
				GeoCurrencyDecisionService::SESSION_GEO_SESSION_DONE,
				'umc_geo_prev_billing_country',
				'umc_geo_prev_shipping_country',
			),
			PersistedKeys::session_keys()
		);
	}

	public function test_cookie_names_match_currency_context_constant(): void {
		$this->assertSame(
			array( CurrencyContext::COOKIE_NAME ),
			PersistedKeys::cookie_names()
		);
	}

	public function test_store_api_extension_namespace_is_runtime_only(): void {
		$this->assertSame(
			array( CartExtensionData::NAMESPACE_KEY ),
			PersistedKeys::store_api_extension_namespaces()
		);
	}

	public function test_runtime_src_does_not_use_transients_or_object_cache(): void {
		$allowed   = PersistedKeys::transient_writer_basenames();
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			$basename = basename( $file );
			if ( in_array( $basename, $allowed, true ) ) {
				continue;
			}

			$source = (string) file_get_contents( $file );

			if ( 1 === preg_match( '/\b(set_transient|get_transient|delete_transient|wp_cache_set|wp_cache_get|wp_cache_delete|wp_cache_add)\s*\(/', $source ) ) {
				$offenders[] = $basename;
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Runtime src/ must not write transients or object-cache entries outside PersistedKeys::transient_writer_basenames().'
		);
	}

	public function test_architecture_doc_points_at_persisted_data_inventory(): void {
		$source = (string) file_get_contents( $this->root() . '/docs/ARCHITECTURE.md' );

		$this->assertStringContainsString( 'docs/PERSISTED_DATA.md', $source );
	}
}
