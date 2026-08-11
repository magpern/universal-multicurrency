<?php
/**
 * Unit tests for CurrencyDecisionExplainer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Decision;

use PHPUnit\Framework\TestCase;
use UMC\CurrencyResolver;
use UMC\CurrencySwitcher;
use UMC\Decision\CurrencyDecisionExplainer;
use UMC\Decision\DecisionExplanationInput;
use UMC\Decision\ExplanationStage;
use UMC\Geo\GeoCurrencyDecisionService;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Settings;

/**
 * @covers \UMC\Decision\CurrencyDecisionExplainer
 */
final class CurrencyDecisionExplainerTest extends TestCase {

	public function test_display_currency_matches_geo_simulate_when_checkout_omitted(): void {
		$explainer = $this->explainer(
			array(
				'enabled' => true,
				'rules'   => array(
					array(
						'id'       => 'rule_00000001',
						'type'     => 'country',
						'value'    => 'SE',
						'currency' => 'SEK',
					),
				),
			)
		);

		$input = new DecisionExplanationInput(
			null,
			null,
			null,
			'EUR',
			array( 'SEK', 'USD' ),
			false,
			null,
			false,
			true,
			'SE'
		);

		$explanation = $explainer->explain( $input );
		$simulated   = $this->geo_service(
			array(
				'enabled' => true,
				'rules'   => array(
					array(
						'id'       => 'rule_00000001',
						'type'     => 'country',
						'value'    => 'SE',
						'currency' => 'SEK',
					),
				),
			)
		)->simulate(
			array(
				'country_code'  => 'SE',
				'selectable'    => array( 'SEK', 'USD' ),
				'base_currency' => 'EUR',
			)
		);

		$this->assertSame( $simulated['final_currency'], $explanation->display_currency() );
		$this->assertSame( 'SEK', $explanation->display_currency() );
	}

	public function test_manual_explicit_wins_and_geo_is_skipped(): void {
		$explanation = $this->explainer(
			array(
				'enabled' => true,
				'rules'   => array(
					array(
						'id'       => 'rule_00000001',
						'type'     => 'country',
						'value'    => 'SE',
						'currency' => 'SEK',
					),
				),
			)
		)->explain(
			new DecisionExplanationInput(
				'USD',
				null,
				null,
				'EUR',
				array( 'SEK', 'USD' ),
				true,
				CurrencySwitcher::ORIGIN_CUSTOMER,
				false,
				true,
				'SE'
			)
		);

		$this->assertSame( 'USD', $explanation->display_currency() );
		$this->assertSame( 'explicit', $explanation->shopper_resolution()->winning_source() );

		$geo_stage = $this->stage( $explanation->stages(), 'visitor_location' );
		$this->assertSame( ExplanationStage::STATUS_SKIPPED, $geo_stage->status() );
		$this->assertFalse( $geo_stage->payload()['won'] );
		$this->assertFalse( $geo_stage->payload()['participated'] );
	}

	public function test_session_origin_is_exposed_without_changing_winning_source(): void {
		$explanation = $this->explainer( array( 'enabled' => false ) )->explain(
			new DecisionExplanationInput(
				null,
				'SEK',
				null,
				'EUR',
				array( 'SEK' ),
				false,
				CurrencySwitcher::ORIGIN_VISITOR_LOCATION
			)
		);

		$this->assertSame( 'session', $explanation->shopper_resolution()->winning_source() );
		$this->assertSame( CurrencySwitcher::ORIGIN_VISITOR_LOCATION, $explanation->currency_origin() );
		$this->assertSame( 'SEK', $explanation->display_currency() );
	}

	public function test_checkout_unsupported_gateway_transitions_to_store_currency(): void {
		$explanation = $this->explainer( array( 'enabled' => false ) )->explain(
			new DecisionExplanationInput(
				null,
				'SEK',
				null,
				'EUR',
				array( 'SEK' ),
				false,
				CurrencySwitcher::ORIGIN_CUSTOMER,
				false,
				false,
				'',
				false,
				true,
				'selected',
				true,
				true,
				false
			)
		);

		$this->assertSame( 'SEK', $explanation->display_currency() );
		$this->assertSame( 'EUR', $explanation->checkout_currency() );

		$checkout = $this->stage( $explanation->stages(), 'checkout_policy' );
		$this->assertTrue( $checkout->payload()['transition_required'] );
		$this->assertTrue( $checkout->payload()['fallback_occurred'] );
	}

	/**
	 * @param array<string, mixed> $geo Geo settings.
	 */
	private function explainer( array $geo ): CurrencyDecisionExplainer {
		return new CurrencyDecisionExplainer( new CurrencyResolver(), $this->geo_service( $geo ) );
	}

	/**
	 * @param array<string, mixed> $geo Geo settings.
	 */
	private function geo_service( array $geo ): GeoCurrencyDecisionService {
		$settings = new Settings(
			array(
				'currencies' => array(),
				'geo'        => $geo,
			)
		);

		return new GeoCurrencyDecisionService( new GeoDetectionSettingsRepository( $settings ) );
	}

	/**
	 * @param array<int, \UMC\Decision\ExplanationStage> $stages Stages.
	 */
	private function stage( array $stages, string $id ): ExplanationStage {
		foreach ( $stages as $stage ) {
			if ( $id === $stage->id() ) {
				return $stage;
			}
		}

		$this->fail( 'Missing stage ' . $id );
	}
}
