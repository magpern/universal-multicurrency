<?php
/**
 * Unit tests for RateQuote.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\RateQuote;

/**
 * Tests the immutable rate quote value object.
 */
final class RateQuoteTest extends TestCase {

	public function test_codes_are_uppercased(): void {
		$quote = new RateQuote( 'eur', 'sek', '11.50' );

		$this->assertSame( 'EUR', $quote->base_code() );
		$this->assertSame( 'SEK', $quote->target_code() );
		$this->assertSame( '11.50', $quote->rate() );
	}
}
