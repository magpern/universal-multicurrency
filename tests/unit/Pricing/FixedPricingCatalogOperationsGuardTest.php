<?php
/**
 * M24 WP5: architecture guards for fixed-price catalog operations.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use UMC\Tests\Support\SourceGuardTrait;

/**
 * Executable architecture invariants for ADR-0029, enforced without loading
 * WordPress — the same static-source-scan discipline
 * {@see \UMC\Tests\Integration\StorefrontGuardTest} and
 * {@see \UMC\Tests\Unit\SecuritySourceGuardTest} already use.
 */
final class FixedPricingCatalogOperationsGuardTest extends TestCase {

	use SourceGuardTrait;

	/**
	 * Files permitted to reference the raw `_umc_fixed_prices` meta key.
	 * Everything else must go through FixedPriceRepository.
	 *
	 * @var array<int, string>
	 */
	private const FIXED_PRICE_META_KEY_FILES = array(
		'FixedPriceDocument.php',
		'FixedPriceRepository.php',
		'FixedProductPricingCheck.php',
	);

	/**
	 * M24 orchestration/presentation files that must stay decoupled from the
	 * storefront price-resolution seam.
	 *
	 * @var array<int, string>
	 */
	private const M24_FILES = array(
		'src/Pricing/FixedPriceCatalogOperationsService.php',
		'src/Pricing/FixedPriceCoverageReport.php',
		'src/Pricing/FixedPriceCatalogQuery.php',
		'src/Pricing/FixedPriceOperationResult.php',
		'src/CLI/PricesCommand.php',
		'src/Admin/FixedPricingSettingsField.php',
		'src/Admin/FixedPricingOperationController.php',
		'src/Admin/FixedPriceCoverageColumn.php',
	);

	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * @return array<int, string>
	 */
	private function m24_files(): array {
		return array_map(
			fn( string $relative ): string => $this->root() . '/' . $relative,
			self::M24_FILES
		);
	}

	/**
	 * No file outside the established fixed-price persistence boundary may
	 * reference the raw meta key — every write/read must go through
	 * FixedPriceRepository, keeping M24 to a single write path (never a
	 * direct update_post_meta()/delete_post_meta() bypass).
	 */
	public function test_only_the_repository_and_established_files_reference_the_meta_key(): void {
		$offenders = array();

		foreach ( $this->umc_source_files() as $file ) {
			if ( in_array( basename( $file ), self::FIXED_PRICE_META_KEY_FILES, true ) ) {
				continue;
			}

			if ( 1 === preg_match( '/_umc_fixed_prices\b/', (string) file_get_contents( $file ) ) ) {
				$offenders[] = basename( $file );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Only FixedPriceRepository (and its established callers) may reference the raw ' .
			'_umc_fixed_prices meta key; every other file must go through the repository.'
		);
	}

	/**
	 * M24's orchestration/presentation layer must never couple to the
	 * storefront pricing-resolution seam — it is an admin/CLI catalog
	 * operation, not a second product-price resolver.
	 */
	public function test_m24_files_have_no_storefront_price_resolution_coupling(): void {
		$this->assert_pattern_absent_from(
			$this->m24_files(),
			'/\bPriceHooks\b/',
			'M24 files must never couple to PriceHooks — this is an admin/CLI catalog ' .
			'operation, not a storefront price-resolution path.'
		);

		$this->assert_pattern_absent_from(
			$this->m24_files(),
			'/\bProductPriceResolutionService\b/',
			'M24 files must never couple to ProductPriceResolutionService — seeding writes ' .
			'authored fixed prices, it does not resolve or intercept storefront prices.'
		);
	}

	/**
	 * M24 introduces no live external HTTP anywhere — seeding uses the
	 * already-resolved in-process rate via the Converter seam, never a new
	 * network call.
	 */
	public function test_m24_files_perform_no_http(): void {
		$this->assert_pattern_absent_from(
			$this->m24_files(),
			'/\b(wp_remote_get|wp_remote_post|wp_remote_request|curl_exec|curl_init|fsockopen|stream_socket_client)\s*\(/',
			'M24 must never perform live external HTTP — seeding only reads an already-resolved rate.'
		);
	}

	/**
	 * M24 must never write to the WordPress Products-list bulk-action
	 * dropdown (ADR-0029 explicitly rejects this UX in favor of the
	 * dedicated screen).
	 */
	public function test_m24_files_register_no_products_list_bulk_actions(): void {
		$this->assert_pattern_absent_from(
			$this->m24_files(),
			'/bulk_actions-edit-product|handle_bulk_actions-edit-product/',
			'M24 must not register WordPress Products-list bulk-action dropdown entries — ' .
			'the dedicated Fixed Pricing screen is the sanctioned catalog-operations surface.'
		);
	}
}
