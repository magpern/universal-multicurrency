<?php
/**
 * Unit tests for first-match geo currency rule evaluation.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoCurrencyRuleEvaluator;
use UMC\Geo\GeoRoutingRule;

/**
 * Exercises ordered rule matching, skip logic, and fallback resolution.
 */
final class GeoCurrencyRuleEvaluatorTest extends TestCase {

	private const BASE = 'EUR';

	/**
	 * @var array<int, string>
	 */
	private const SELECTABLE = array( 'SEK', 'PLN', 'USD', 'CNY' );

	/**
	 * @var GeoCurrencyRuleEvaluator
	 */
	private GeoCurrencyRuleEvaluator $evaluator;

	protected function setUp(): void {
		parent::setUp();

		$this->evaluator = new GeoCurrencyRuleEvaluator();
	}

	public function test_first_match_wins_and_stops_evaluation(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'SE', 'SEK', 'rule_00000001' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'SE', 'USD', 'rule_00000002' ),
		);

		$result = $this->evaluator->evaluate( 'SE', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'SEK', $result->currency() );
		$this->assertSame( 'rule_00000001', $result->matched_rule_id() );
		$this->assertSame( 0, $result->matched_rule_index() );
		$this->assertTrue( $result->trace()[0]['stopped'] );
		$this->assertCount( 1, $result->trace() );
	}

	public function test_sweden_resolves_to_sek(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'SE', 'SEK', 'rule_00000003' ),
		);

		$result = $this->evaluator->evaluate( 'SE', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'SEK', $result->currency() );
		$this->assertSame( GeoRoutingRule::TYPE_COUNTRY, $result->matched_rule_type() );
	}

	public function test_poland_pln_rule_before_eu_wins(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'PLN', 'rule_00000004' ),
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_00000005' ),
		);

		$result = $this->evaluator->evaluate( 'PL', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'PLN', $result->currency() );
		$this->assertSame( 'PL', $result->matched_rule_label() );
	}

	public function test_poland_eur_when_eu_rule_is_first(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_00000006' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'PLN', 'rule_00000007' ),
		);

		$result = $this->evaluator->evaluate( 'PL', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'EUR', $result->currency() );
		$this->assertSame( 'region:eu', $result->matched_rule_label() );
	}

	public function test_germany_matches_eu_region_rule(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_00000008' ),
		);

		$result = $this->evaluator->evaluate( 'DE', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'EUR', $result->currency() );
		$this->assertSame( GeoRoutingRule::TYPE_REGION, $result->matched_rule_type() );
	}

	public function test_china_falls_through_to_other_catch_all(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'US', 'USD', 'rule_00000009' ),
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'CNY', 'rule_0000000a' ),
		);

		$result = $this->evaluator->evaluate( 'CN', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'CNY', $result->currency() );
		$this->assertTrue( $result->catch_all_matched() );
		$this->assertSame( GeoRoutingRule::TYPE_OTHER, $result->matched_rule_type() );
	}

	public function test_unavailable_currency_on_match_is_skipped(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'CN', 'JPY', 'rule_0000000b' ),
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'USD', 'rule_0000000c' ),
		);

		$result = $this->evaluator->evaluate( 'CN', $rules, self::SELECTABLE, '', self::BASE );

		$this->assertSame( 'USD', $result->currency() );
		$this->assertFalse( $result->trace()[0]['matched'] );
		$this->assertSame( 'currency_unavailable', $result->trace()[0]['reason'] );
		$this->assertNotEmpty( $result->warnings() );
	}

	public function test_technical_fallback_used_when_no_rule_matches(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'US', 'USD', 'rule_0000000d' ),
		);

		$result = $this->evaluator->evaluate( 'JP', $rules, self::SELECTABLE, 'SEK', self::BASE );

		$this->assertSame( 'SEK', $result->currency() );
		$this->assertTrue( $result->technical_fallback_used() );
		$this->assertNull( $result->matched_rule_id() );
	}

	public function test_invalid_country_code_uses_technical_fallback(): void {
		$result = $this->evaluator->evaluate(
			'INVALID',
			array(),
			self::SELECTABLE,
			'USD',
			self::BASE
		);

		$this->assertSame( 'USD', $result->currency() );
		$this->assertTrue( $result->technical_fallback_used() );
	}

	public function test_base_returned_when_fallback_unavailable(): void {
		$result = $this->evaluator->evaluate(
			'JP',
			array(),
			self::SELECTABLE,
			'JPY',
			self::BASE
		);

		$this->assertSame( self::BASE, $result->currency() );
		$this->assertTrue( $result->technical_fallback_used() );
	}

	/**
	 * @param string $type     Rule type.
	 * @param string $value    Country or region value.
	 * @param string $currency Currency code.
	 * @param string $id       Stable rule id.
	 */
	private function rule( string $type, string $value, string $currency, string $id ): GeoRoutingRule {
		$rule = GeoRoutingRule::from_array(
			array(
				'id'       => $id,
				'type'     => $type,
				'value'    => $value,
				'currency' => $currency,
			)
		);

		$this->assertNotNull( $rule );

		return $rule;
	}
}
