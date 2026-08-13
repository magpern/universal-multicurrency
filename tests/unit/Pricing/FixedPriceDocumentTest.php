<?php
/**
 * Unit tests for fixed-price documents.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Pricing;

use PHPUnit\Framework\TestCase;
use UMC\Pricing\FixedPriceDocument;
use UMC\Pricing\FixedPriceValidator;

/**
 * @covers \UMC\Pricing\FixedPriceDocument
 * @covers \UMC\Pricing\FixedPriceValidator
 */
final class FixedPriceDocumentTest extends TestCase {

	public function test_from_storage_excludes_base_currency(): void {
		$raw = wp_json_encode(
			array(
				'schema_version' => 1,
				'currencies'     => array(
					'EUR' => array( 'regular' => '99' ),
					'SEK' => array( 'regular' => '1100' ),
				),
			)
		);

		$document = FixedPriceDocument::from_storage( $raw, 'EUR' );

		$this->assertNull( $document->get_currency( 'EUR' ) );
		$this->assertSame( '1100', $document->get_currency( 'SEK' )?->regular() );
	}

	public function test_normalize_price_rejects_non_numeric_strings(): void {
		$this->assertSame( '', FixedPriceValidator::normalize_price( 'foo' ) );
		$this->assertSame( '', FixedPriceValidator::normalize_price( '12.34.56' ) );
	}

	public function test_to_storage_json_omits_empty_document(): void {
		$this->assertSame( '', FixedPriceDocument::empty()->to_storage_json() );
	}

	public function test_fingerprint_changes_when_currency_row_changes(): void {
		$first  = FixedPriceDocument::from_array(
			array( 'SEK' => array( 'regular' => '1100' ) ),
			'EUR'
		);
		$second = FixedPriceDocument::from_array(
			array( 'SEK' => array( 'regular' => '1200' ) ),
			'EUR'
		);

		$this->assertNotSame( $first->fingerprint(), $second->fingerprint() );
	}

	public function test_sale_must_not_exceed_regular_in_validator(): void {
		$this->assertFalse( FixedPriceValidator::sale_less_than_regular( '100', '120' ) );
		$this->assertTrue( FixedPriceValidator::sale_less_than_regular( '100', '80' ) );
	}
}
