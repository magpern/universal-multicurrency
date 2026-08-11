<?php
/**
 * Decision Inspector settings field renderer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\Geo\GeoPanelRegistry;
use UMC\Checkout\CheckoutSettings;
use UMC\Currency;
use UMC\CurrencySwitcher;
use UMC\Decision\CurrencyDecisionExplanation;
use UMC\Decision\ExplanationStage;
use UMC\Geo\GeoDetectionSettingsRepository;
use UMC\Geo\UgcIntegrationStatus;
use UMC\Settings;

/**
 * Renders the Decision Inspector admin section.
 */
final class DecisionInspectorSettingsField {

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Design-system renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $ui;

	/**
	 * Constructs the field.
	 *
	 * @param Settings $settings Settings.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->ui       = new AdminComponentRenderer();
	}

	/**
	 * Renders the section.
	 */
	public function render(): void {
		$values      = $this->values_from_request();
		$service     = new DecisionInspectorService( $this->settings, $this->base );
		$explanation = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only flag.
		$inspected = isset( $_GET['umc_inspected'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['umc_inspected'] ) ) : '';
		if ( '1' === $inspected ) {
			$explanation = $service->explain_from_array( $values );
		}

		$action   = admin_url( 'admin-post.php' );
		$geo      = ( new GeoDetectionSettingsRepository( $this->settings ) )->get();
		$checkout = $this->settings->get()['checkout'] ?? CheckoutSettings::default_array();
		$ugc      = new UgcIntegrationStatus();
		$origin   = (string) ( $values['currency_origin'] ?? '' );
		$mode     = (string) ( $values['checkout_mode'] ?? ( is_array( $checkout ) ? ( $checkout['mode'] ?? 'selected' ) : 'selected' ) );

		$html  = '<div class="umc-decision-inspector">';
		$html .= $this->ui->page_intro(
			__( 'Decision Inspector', 'universal-multicurrency' ),
			__( 'Explain why a shopper would use a given currency, including Visitor Location and checkout policy when relevant. Simulation is side-effect-free and is not saved.', 'universal-multicurrency' )
		);
		$html .= $this->ui->quick_actions_panel(
			__( 'Related tools', 'universal-multicurrency' ),
			array(
				array(
					'label' => __( 'Currency Routing', 'universal-multicurrency' ),
					'url'   => admin_url( 'admin.php?page=wc-settings&tab=umc&section=geo_detection&geo_panel=' . GeoPanelRegistry::PANEL_DETECTION ),
				),
				array(
					'label' => __( 'Currency Simulation', 'universal-multicurrency' ),
					'url'   => admin_url( 'admin.php?page=wc-settings&tab=umc&section=geo_detection&geo_panel=' . GeoPanelRegistry::PANEL_SANDBOX ),
				),
				array(
					'label' => __( 'Compatibility', 'universal-multicurrency' ),
					'url'   => admin_url( 'admin.php?page=wc-settings&tab=umc&section=' . SettingsPage::SECTION_COMPATIBILITY ),
				),
				array(
					'label' => __( 'Universal Geo Context', 'universal-multicurrency' ),
					'url'   => $ugc->is_available() ? $ugc->detection_url() : admin_url( 'plugins.php' ),
				),
			)
		);

		$html .= '<form method="post" action="' . esc_url( $action ) . '" class="umc-decision-inspector__form">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( DecisionInspectorController::ACTION ) . '" />';
		$html .= wp_nonce_field( DecisionInspectorController::ACTION, '_wpnonce', true, false );
		$html .= $this->ui->settings_card_open(
			__( 'Simulation inputs', 'universal-multicurrency' ),
			__( 'Provide deterministic context. No shopper cookies or sessions are modified.', 'universal-multicurrency' )
		);
		$html .= $this->ui->input_row(
			'umc_decision_inspector[country_code]',
			__( 'Country', 'universal-multicurrency' ),
			(string) ( $values['country_code'] ?? '' ),
			__( 'ISO alpha-2 country code for Visitor Location evaluation.', 'universal-multicurrency' ),
			array(
				'maxlength'   => '2',
				'placeholder' => 'SE',
			)
		);
		$html .= $this->ui->input_row(
			'umc_decision_inspector[explicit_currency]',
			__( 'Customer selection (explicit)', 'universal-multicurrency' ),
			(string) ( $values['explicit_currency'] ?? '' ),
			'',
			array( 'maxlength' => '3' )
		);
		$html .= $this->ui->input_row(
			'umc_decision_inspector[session_currency]',
			__( 'Saved customer currency (session)', 'universal-multicurrency' ),
			(string) ( $values['session_currency'] ?? '' ),
			'',
			array( 'maxlength' => '3' )
		);
		$html .= $this->ui->input_row(
			'umc_decision_inspector[cookie_currency]',
			__( 'Remembered currency (cookie)', 'universal-multicurrency' ),
			(string) ( $values['cookie_currency'] ?? '' ),
			'',
			array( 'maxlength' => '3' )
		);
		$html .= $this->ui->select_row(
			'umc_decision_inspector[currency_origin]',
			__( 'Session currency origin', 'universal-multicurrency' ),
			__( 'Explanatory metadata only. Never changes which currency wins.', 'universal-multicurrency' ),
			$this->select_html(
				'umc_decision_inspector[currency_origin]',
				array(
					''                                => __( 'Unknown / not set', 'universal-multicurrency' ),
					CurrencySwitcher::ORIGIN_CUSTOMER => __( 'Customer selection', 'universal-multicurrency' ),
					CurrencySwitcher::ORIGIN_VISITOR_LOCATION => __( 'Visitor Location', 'universal-multicurrency' ),
				),
				$origin
			)
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[manual_selection]',
			! empty( $values['manual_selection'] ),
			__( 'Manual selection flag', 'universal-multicurrency' ),
			__( 'Simulates the until_manual Visitor Location suppression flag.', 'universal-multicurrency' )
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[geo_enabled]',
			array_key_exists( 'geo_enabled', $values ) ? ! empty( $values['geo_enabled'] ) : $geo->is_enabled(),
			__( 'Visitor Location enabled', 'universal-multicurrency' )
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[checkout_locked]',
			! empty( $values['checkout_locked'] ),
			__( 'Checkout locked for Visitor Location', 'universal-multicurrency' )
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[include_checkout]',
			! empty( $values['include_checkout'] ),
			__( 'Include checkout policy', 'universal-multicurrency' )
		);
		$html .= $this->ui->select_row(
			'umc_decision_inspector[checkout_mode]',
			__( 'Checkout mode', 'universal-multicurrency' ),
			'',
			$this->select_html(
				'umc_decision_inspector[checkout_mode]',
				array(
					'selected' => __( 'Selected currency', 'universal-multicurrency' ),
					'store'    => __( 'Store currency', 'universal-multicurrency' ),
				),
				$mode
			)
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[gateway_supports_display]',
			! array_key_exists( 'gateway_supports_display', $values ) || ! empty( $values['gateway_supports_display'] ),
			__( 'Payment gateway supports display currency', 'universal-multicurrency' )
		);
		$html .= $this->ui->toggle_row(
			'umc_decision_inspector[show_notice]',
			! array_key_exists( 'show_notice', $values ) || ! empty( $values['show_notice'] ),
			__( 'Customer notice enabled', 'universal-multicurrency' )
		);
		$html .= $this->ui->settings_card_footer(
			'<button type="submit" class="button button-primary">' . esc_html__( 'Explain decision', 'universal-multicurrency' ) . '</button>'
		);
		$html .= $this->ui->settings_card_close();
		$html .= '</form>';

		if ( $explanation instanceof CurrencyDecisionExplanation ) {
			$html .= $this->render_explanation( $explanation );
		} else {
			$html .= $this->ui->empty_state(
				'dashicons-search',
				__( 'No explanation yet', 'universal-multicurrency' ),
				__( 'Enter simulation inputs and choose Explain decision.', 'universal-multicurrency' )
			);
		}

		$html .= '</div>';

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped via AdminComponentRenderer and esc_* helpers.
	}

	/**
	 * Renders an explanation timeline and summary.
	 *
	 * @param CurrencyDecisionExplanation $explanation Explanation.
	 */
	public function render_explanation( CurrencyDecisionExplanation $explanation ): string {
		$html     = $this->ui->settings_card_open(
			__( 'Explanation', 'universal-multicurrency' ),
			__( 'Structured stages from the same decision services used at runtime.', 'universal-multicurrency' )
		);
		$html    .= $this->ui->statistics_grid_open();
		$html    .= $this->ui->statistics_card(
			__( 'Display currency', 'universal-multicurrency' ),
			$explanation->display_currency()
		);
		$checkout = $explanation->checkout_currency();
		$html    .= $this->ui->statistics_card(
			__( 'Checkout currency', 'universal-multicurrency' ),
			is_string( $checkout ) ? $checkout : '—'
		);
		$html    .= $this->ui->statistics_card(
			__( 'Session origin', 'universal-multicurrency' ),
			$this->origin_label( $explanation->currency_origin() )
		);
		$html    .= $this->ui->statistics_card(
			__( 'Resolver source', 'universal-multicurrency' ),
			$this->source_label( $explanation->shopper_resolution()->winning_source() )
		);
		$html    .= $this->ui->statistics_grid_close();

		$timeline = array();
		foreach ( $explanation->stages() as $stage ) {
			$timeline[] = array(
				'id'      => $stage->id(),
				'title'   => $this->stage_title( $stage->id() ),
				'status'  => $stage->status(),
				'label'   => $this->status_label( $stage->status() ),
				'summary' => $this->stage_summary( $stage ),
			);
		}

		$html .= $this->ui->decision_timeline( __( 'Decision timeline', 'universal-multicurrency' ), $timeline );
		$html .= $this->ui->settings_card_close();

		return $html;
	}

	/**
	 * Builds a select element.
	 *
	 * @param string                $name    Field name.
	 * @param array<string, string> $options Options.
	 * @param string                $current Current value.
	 */
	private function select_html( string $name, array $options, string $current ): string {
		$id           = sanitize_key( str_replace( array( '[', ']' ), array( '-', '' ), $name ) );
		$options_html = '';

		foreach ( $options as $value => $label ) {
			$options_html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $value ),
				selected( $current, (string) $value, false ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<select id="%1$s" name="%2$s">%3$s</select>',
			esc_attr( $id ),
			esc_attr( $name ),
			$options_html
		);
	}

	/**
	 * Reads simulation values from the current request query args.
	 *
	 * @return array<string, mixed>
	 */
	private function values_from_request(): array {
		$get = static function ( string $key ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : '';
		};

		if ( ! isset( $_GET['umc_inspected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array();
		}

		return array(
			'country_code'             => $get( 'umc_di_country' ),
			'explicit_currency'        => $get( 'umc_di_explicit' ),
			'session_currency'         => $get( 'umc_di_session' ),
			'cookie_currency'          => $get( 'umc_di_cookie' ),
			'manual_selection'         => '1' === $get( 'umc_di_manual' ),
			'currency_origin'          => $get( 'umc_di_origin' ),
			'geo_enabled'              => '1' === $get( 'umc_di_geo_enabled' ),
			'checkout_locked'          => '1' === $get( 'umc_di_checkout_locked' ),
			'include_checkout'         => '1' === $get( 'umc_di_include_checkout' ),
			'checkout_mode'            => $get( 'umc_di_checkout_mode' ),
			'show_notice'              => '1' === $get( 'umc_di_show_notice' ),
			'payment_required'         => '1' === $get( 'umc_di_payment_required' ),
			'gateway_supports_display' => '1' === $get( 'umc_di_gateway_supports' ),
			'order_context_active'     => '1' === $get( 'umc_di_order_context' ),
		);
	}

	/**
	 * Merchant label for resolver source.
	 *
	 * @param string $source Source code.
	 */
	private function source_label( string $source ): string {
		switch ( $source ) {
			case 'explicit':
				return __( 'Customer selection', 'universal-multicurrency' );
			case 'session':
				return __( 'Saved customer currency', 'universal-multicurrency' );
			case 'cookie':
				return __( 'Remembered currency', 'universal-multicurrency' );
			case 'base':
				return __( 'Store default', 'universal-multicurrency' );
		}

		return $source;
	}

	/**
	 * Merchant label for provenance origin.
	 *
	 * @param string|null $origin Origin code.
	 */
	private function origin_label( ?string $origin ): string {
		if ( CurrencySwitcher::ORIGIN_CUSTOMER === $origin ) {
			return __( 'Customer selection', 'universal-multicurrency' );
		}

		if ( CurrencySwitcher::ORIGIN_VISITOR_LOCATION === $origin ) {
			return __( 'Visitor Location', 'universal-multicurrency' );
		}

		return __( 'Not set', 'universal-multicurrency' );
	}

	/**
	 * Stage title.
	 *
	 * @param string $id Stage id.
	 */
	private function stage_title( string $id ): string {
		switch ( $id ) {
			case 'order_context':
				return __( 'Order context', 'universal-multicurrency' );
			case 'shopper_selection':
				return __( 'Shopper selection', 'universal-multicurrency' );
			case 'visitor_location':
				return __( 'Visitor Location', 'universal-multicurrency' );
			case 'display_currency':
				return __( 'Display currency', 'universal-multicurrency' );
			case 'checkout_policy':
				return __( 'Checkout policy', 'universal-multicurrency' );
			case 'gateway_compatibility':
				return __( 'Payment gateway', 'universal-multicurrency' );
			case 'customer_notice':
				return __( 'Customer notice', 'universal-multicurrency' );
		}

		return $id;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status code.
	 */
	private function status_label( string $status ): string {
		switch ( $status ) {
			case ExplanationStage::STATUS_WON:
				return __( 'Won', 'universal-multicurrency' );
			case ExplanationStage::STATUS_CONSIDERED:
				return __( 'Considered', 'universal-multicurrency' );
			case ExplanationStage::STATUS_SKIPPED:
				return __( 'Skipped', 'universal-multicurrency' );
			case ExplanationStage::STATUS_BLOCKED:
				return __( 'Blocked', 'universal-multicurrency' );
			case ExplanationStage::STATUS_INFO:
				return __( 'Info', 'universal-multicurrency' );
		}

		return $status;
	}

	/**
	 * Builds a short stage summary from structured payload.
	 *
	 * @param ExplanationStage $stage Stage.
	 */
	private function stage_summary( ExplanationStage $stage ): string {
		$payload = $stage->payload();

		switch ( $stage->id() ) {
			case 'shopper_selection':
				$resolution = is_array( $payload['resolution'] ?? null ) ? $payload['resolution'] : array();
				$currency   = isset( $resolution['currency'] ) ? (string) $resolution['currency'] : '';
				$source     = isset( $resolution['winning_source'] ) ? $this->source_label( (string) $resolution['winning_source'] ) : '';
				return trim( $currency . ' — ' . $source );

			case 'visitor_location':
				$parts = array();
				if ( ! empty( $payload['won'] ) ) {
					$parts[] = __( 'Applied', 'universal-multicurrency' );
				} elseif ( ! empty( $payload['participated'] ) ) {
					$parts[] = __( 'Candidate produced', 'universal-multicurrency' );
				} else {
					$parts[] = __( 'Did not apply', 'universal-multicurrency' );
				}
				if ( ! empty( $payload['candidate'] ) ) {
					$parts[] = (string) $payload['candidate'];
				}
				if ( ! empty( $payload['country_code'] ) ) {
					$parts[] = (string) $payload['country_code'];
				}
				$evaluation = is_array( $payload['evaluation'] ?? null ) ? $payload['evaluation'] : array();
				if ( ! empty( $evaluation['matched_rule_label'] ) ) {
					$parts[] = (string) $evaluation['matched_rule_label'];
				}
				return implode( ' · ', $parts );

			case 'display_currency':
			case 'checkout_policy':
				return (string) ( $payload['currency'] ?? $payload['effective_currency'] ?? '' );

			case 'gateway_compatibility':
				return ! empty( $payload['supports_display'] )
					? __( 'Supports display currency', 'universal-multicurrency' )
					: __( 'Does not support display currency', 'universal-multicurrency' );

			case 'customer_notice':
				return ! empty( $payload['show'] )
					? __( 'Notice would show', 'universal-multicurrency' )
					: __( 'No notice', 'universal-multicurrency' );
		}

		return $stage->reason();
	}
}
