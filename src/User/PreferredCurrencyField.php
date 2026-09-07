<?php
/**
 * Currency field provider for Regional Preferences.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\User;

use UMC\CurrencySwitcher;
use WP_Error;

/**
 * Owns currency slot rendering and saving.
 */
final class PreferredCurrencyField {

	public const FIELD        = 'umc_preferred_currency';
	public const NONCE        = 'umc_preferred_currency_nonce';
	public const NONCE_ACTION = 'umc_preferred_currency_save';

	/**
	 * Creates the currency field provider.
	 *
	 * @param PreferredCurrency $preference Preference service.
	 * @param CurrencySwitcher  $switcher   Shopper persistence service.
	 */
	public function __construct(
		private PreferredCurrency $preference,
		private CurrencySwitcher $switcher
	) {
	}

	/**
	 * Registers composition hooks.
	 */
	public function register(): void {
		add_action( RegionalPreferencesComposition::ACTION_RENDER, array( $this, 'render' ), 10, 1 );
		add_action( RegionalPreferencesComposition::ACTION_SAVE, array( $this, 'save' ), 10, 1 );
		add_filter( RegionalPreferencesComposition::FILTER_CLAIMED_SLOTS, array( $this, 'claim_slot' ), 10, 1 );
	}

	/**
	 * Claims the shared currency slot.
	 *
	 * @param array<int, string> $slots Claimed slots.
	 * @return array<int, string>
	 */
	public function claim_slot( array $slots ): array {
		$slots[] = RegionalPreferencesComposition::SLOT_CURRENCY;
		return array_values( array_unique( $slots ) );
	}

	/**
	 * Renders the preferred-currency field.
	 *
	 * @param array{surface: string, user_id: int, can_edit: bool} $context Composition context.
	 */
	public function render( array $context ): void {
		$user_id = (int) ( $context['user_id'] ?? 0 );
		$state   = $this->preference->get_state( $user_id );

		if ( null === $state ) {
			return;
		}

		$editable = ! empty( $context['can_edit'] ) && ! empty( $state['editable'] );
		$surface  = (string) ( $context['surface'] ?? RegionalPreferencesComposition::SURFACE_PROFILE );

		echo '<tr class="umc-regional-preference-currency"><th><label for="' . esc_attr( self::FIELD ) . '">';
		echo esc_html__( 'Currency', 'universal-multicurrency' );
		echo '</label></th><td>';

		if ( $editable ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE );
			echo '<select name="' . esc_attr( self::FIELD ) . '" id="' . esc_attr( self::FIELD ) . '">';
			echo '<option value="">' . esc_html__( 'Use store default', 'universal-multicurrency' ) . '</option>';
			$selected = PreferredCurrency::SOURCE_STORED === ( $state['source'] ?? '' )
				&& is_string( $state['stored'] ?? null )
				? (string) $state['stored']
				: '';

			foreach ( $state['options'] as $code => $label ) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( (string) $code ),
					selected( $selected, (string) $code, false ),
					esc_html( (string) $label )
				);
			}
			echo '</select>';
		} else {
			printf(
				'<input type="text" id="%s" value="%s" class="regular-text" disabled="disabled" />',
				esc_attr( self::FIELD ),
				esc_attr( (string) $state['label'] )
			);

			if ( ! empty( $state['unavailable_message'] ) ) {
				echo '<p class="description">' . esc_html( (string) $state['unavailable_message'] ) . '</p>';
			}
		}

		if ( RegionalPreferencesComposition::SURFACE_ACCOUNT === $surface ) {
			echo '<span class="description">';
			echo esc_html__( 'Preferred currency for your account.', 'universal-multicurrency' );
			echo '</span>';
		}

		echo '</td></tr>';
	}

	/**
	 * Processes the preferred-currency POST.
	 *
	 * @param array{surface: string, user_id: int, can_edit: bool} $context Composition context.
	 */
	public function save( array $context ): void {
		if ( empty( $context['can_edit'] ) ) {
			return;
		}

		$user_id = (int) ( $context['user_id'] ?? 0 );

		if ( $user_id <= 0 ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$code   = strtoupper( sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) ) );
		$result = $this->preference->set( $user_id, '' === $code ? null : $code );

		if ( $result instanceof WP_Error || '' === $code || (int) get_current_user_id() !== $user_id ) {
			return;
		}

		$this->switcher->persist( $code, true, CurrencySwitcher::ORIGIN_USER_PREFERENCE );
	}
}
