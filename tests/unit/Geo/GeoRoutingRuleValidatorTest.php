<?php
/**
 * Unit tests for geo routing rule save-time validation.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;
use UMC\Geo\GeoRoutingRule;
use UMC\Geo\GeoRoutingRuleValidator;

/**
 * Exercises duplicate detection, other-rule placement, and shadow warnings.
 */
final class GeoRoutingRuleValidatorTest extends TestCase {

	/**
	 * @var array<int, string>
	 */
	private const SELECTABLE = array( 'EUR', 'PLN', 'USD' );

	/**
	 * @var GeoRoutingRuleValidator
	 */
	private GeoRoutingRuleValidator $validator;

	protected function setUp(): void {
		parent::setUp();

		$this->validator = new GeoRoutingRuleValidator();
	}

	public function test_duplicate_country_rule_is_blocked(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'PLN', 'rule_00000001' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'EUR', 'rule_00000002' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertFalse( $result->is_valid() );
		$this->assertStringContainsString( 'Duplicate country rule for PL at position 2.', $result->errors()[0] );
	}

	public function test_duplicate_region_rule_is_blocked(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_00000003' ),
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'USD', 'rule_00000004' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertFalse( $result->is_valid() );
		$this->assertStringContainsString( 'Duplicate region rule for eu at position 2.', $result->errors()[0] );
	}

	public function test_multiple_other_rules_are_blocked(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'USD', 'rule_00000005' ),
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'EUR', 'rule_00000006' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'Only one Other countries rule is allowed.', $result->errors() );
	}

	public function test_rule_after_other_is_blocked(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'USD', 'rule_00000007' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'PLN', 'rule_00000008' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertFalse( $result->is_valid() );
		$this->assertContains( 'The Other countries rule must be the last rule.', $result->errors() );
	}

	public function test_poland_country_rule_after_eu_region_emits_shadow_warning(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_00000009' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'PL', 'PLN', 'rule_0000000a' ),
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'USD', 'rule_0000000b' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertTrue( $result->is_valid() );
		$this->assertStringContainsString(
			'The PL rule will never be reached because an earlier eu rule already matches PL.',
			$result->warnings()[0]
		);
	}

	public function test_valid_rule_set_has_no_errors(): void {
		$rules = array(
			$this->rule( GeoRoutingRule::TYPE_REGION, 'eu', 'EUR', 'rule_0000000c' ),
			$this->rule( GeoRoutingRule::TYPE_COUNTRY, 'US', 'USD', 'rule_0000000d' ),
			$this->rule( GeoRoutingRule::TYPE_OTHER, '', 'USD', 'rule_0000000e' ),
		);

		$result = $this->validator->validate( $rules, self::SELECTABLE, true );

		$this->assertTrue( $result->is_valid() );
		$this->assertSame( array(), $result->errors() );
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
