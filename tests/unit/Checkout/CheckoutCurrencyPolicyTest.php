<?php
/**
 * Unit tests for checkout currency policy fallback eligibility.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Checkout;

use PHPUnit\Framework\TestCase;
use UMC\Checkout\CheckoutCurrencyPolicy;
use UMC\Checkout\CheckoutSettings;
use UMC\Checkout\CheckoutTransitionState;
use UMC\Integration\GatewayCurrencyEvaluation;

/**
 * Exercises all ten strict fallback eligibility conditions from the M11 plan.
 */
final class CheckoutCurrencyPolicyTest extends TestCase {

	private CheckoutCurrencyPolicy $policy;

	protected function setUp(): void {
		parent::setUp();

		$this->policy = new CheckoutCurrencyPolicy();
	}

	public function test_all_eligibility_conditions_met_allows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->eligible_evaluation()
		);

		$this->assertTrue( $decision->should_fallback() );
		$this->assertSame( 'SEK', $decision->effective_currency() );
	}

	public function test_condition_one_store_mode_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_STORE, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->eligible_evaluation()
		);

		$this->assertFalse( $decision->should_fallback() );
		$this->assertSame( 'EUR', $decision->effective_currency() );
		$this->assertSame( CheckoutTransitionState::REASON_STORE_CURRENCY, $decision->transition_reason() );
	}

	public function test_condition_two_free_checkout_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			false,
			false,
			$this->eligible_evaluation()
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_three_shopper_equals_store_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'EUR',
			'EUR',
			true,
			false,
			$this->eligible_evaluation()
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_four_fallback_already_attempted_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			true,
			$this->eligible_evaluation()
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_five_empty_pre_umc_set_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array(),
				array(),
				array(),
				array(),
				array(),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_six_unknown_support_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array( 'cheque' ),
				array( 'cheque' ),
				array(),
				array( 'cheque' ),
				array( 'cheque' ),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_seven_retained_gateways_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array( 'cheque' ),
				array( 'cheque' ),
				array(),
				array(),
				array( 'cheque' ),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_eight_not_all_pre_umc_explicitly_removed_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array( 'bacs', 'cheque' ),
				array(),
				array( 'bacs' ),
				array(),
				array(),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_nine_non_empty_after_umc_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array( 'bacs' ),
				array( 'bacs' ),
				array(),
				array(),
				array( 'bacs' ),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_condition_ten_umc_caused_empty_false_disallows_fallback(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_SELECTED, true ),
			'SEK',
			'EUR',
			true,
			false,
			$this->evaluation_with(
				array( 'bacs' ),
				array(),
				array( 'bacs' ),
				array(),
				array(),
				false
			)
		);

		$this->assertFalse( $decision->should_fallback() );
	}

	public function test_decide_pass_two_returns_store_currency_with_fallback_reason(): void {
		$decision = $this->policy->decide_pass_two( 'SEK', 'EUR' );

		$this->assertSame( 'EUR', $decision->effective_currency() );
		$this->assertSame( CheckoutTransitionState::REASON_UNSUPPORTED_SELECTED, $decision->transition_reason() );
		$this->assertFalse( $decision->should_fallback() );
		$this->assertTrue( $decision->fallback_occurred() );
	}

	public function test_store_mode_without_currency_change_has_no_transition_reason(): void {
		$decision = $this->policy->decide_pass_one(
			new CheckoutSettings( CheckoutSettings::MODE_STORE, true ),
			'EUR',
			'EUR',
			true,
			false,
			$this->eligible_evaluation()
		);

		$this->assertSame( 'EUR', $decision->effective_currency() );
		$this->assertSame( '', $decision->transition_reason() );
	}

	/**
	 * Builds an evaluation that satisfies every fallback eligibility condition.
	 */
	private function eligible_evaluation(): GatewayCurrencyEvaluation {
		return $this->evaluation_with(
			array( 'bacs' ),
			array(),
			array( 'bacs' ),
			array(),
			array(),
			true
		);
	}

	/**
	 * @param list<string> $before
	 * @param list<string> $retained
	 * @param list<string> $removed
	 * @param list<string> $unknown
	 * @param list<string> $after
	 */
	private function evaluation_with(
		array $before,
		array $retained,
		array $removed,
		array $unknown,
		array $after,
		bool $umc_caused_empty
	): GatewayCurrencyEvaluation {
		return new GatewayCurrencyEvaluation(
			'SEK',
			$before,
			$retained,
			$removed,
			$unknown,
			$after,
			2,
			$umc_caused_empty
		);
	}
}
