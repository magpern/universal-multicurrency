<?php
/**
 * Unit tests for RateResolver.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;
use UMC\Rates\RateResolver;
use UMC\Settings;

/**
 * Effective-rate derivation tests.
 */
final class RateResolverTest extends TestCase {

	public function test_manual_mode_passthrough(): void {
		$this->assertSame( '11.50', RateResolver::effective_rate( Settings::RATE_MODE_MANUAL, '11.50', '', '0' ) );
		$this->assertNull( RateResolver::effective_rate( Settings::RATE_MODE_MANUAL, '', '11.50', '0' ) );
	}

	public function test_automatic_mode_applies_adjustment(): void {
		$this->assertSame( '11', RateResolver::effective_rate( Settings::RATE_MODE_AUTOMATIC, '', '10', '10' ) );
		$this->assertSame( '9.5', RateResolver::effective_rate( Settings::RATE_MODE_AUTOMATIC, '', '10', '-5' ) );
	}

	public function test_automatic_without_provider_rate_returns_null(): void {
		$this->assertNull( RateResolver::effective_rate( Settings::RATE_MODE_AUTOMATIC, '9', '', '0' ) );
	}

	public function test_never_returns_zero_or_negative(): void {
		$this->assertNull( RateResolver::effective_rate( Settings::RATE_MODE_AUTOMATIC, '', '1', '-100' ) );
	}
}
