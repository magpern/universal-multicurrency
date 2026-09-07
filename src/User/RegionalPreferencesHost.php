<?php
/**
 * Regional Preferences section host (UMC fallback host).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\User;

/**
 * Opens the shared shell when a higher-priority host has not done so.
 */
final class RegionalPreferencesHost {

	public const HOST_PRIORITY = 20;

	/**
	 * Creates the fallback host.
	 *
	 * @param PreferredCurrency $preference Preference service.
	 */
	public function __construct( private PreferredCurrency $preference ) {
	}

	/**
	 * Registers WordPress profile and WooCommerce account hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render_profile' ), self::HOST_PRIORITY );
		add_action( 'edit_user_profile', array( $this, 'render_profile' ), self::HOST_PRIORITY );
		add_action( 'personal_options_update', array( $this, 'save_profile' ), self::HOST_PRIORITY );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ), self::HOST_PRIORITY );
		add_action( 'woocommerce_edit_account_form', array( $this, 'render_account' ), self::HOST_PRIORITY );
		add_action( 'woocommerce_save_account_details', array( $this, 'save_account' ), self::HOST_PRIORITY );
	}

	/**
	 * Renders the profile surface.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render_profile( $user ): void {
		$user_id = isset( $user->ID ) ? (int) $user->ID : 0;
		$this->render_section(
			RegionalPreferencesComposition::SURFACE_PROFILE,
			$user_id,
			$this->can_edit_user( $user_id )
		);
	}

	/**
	 * Saves the profile surface.
	 *
	 * @param int $user_id User id.
	 */
	public function save_profile( int $user_id ): void {
		$this->save_section(
			RegionalPreferencesComposition::SURFACE_PROFILE,
			$user_id,
			$this->can_edit_user( $user_id )
		);
	}

	/**
	 * Renders the current shopper's account surface.
	 */
	public function render_account(): void {
		$user_id = (int) get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		if ( did_action( RegionalPreferencesComposition::ACTION_RENDERED ) > 0 ) {
			return;
		}

		echo '<fieldset class="umc-regional-preferences">';
		$this->render_section( RegionalPreferencesComposition::SURFACE_ACCOUNT, $user_id, true, false );
		echo '</fieldset>';
	}

	/**
	 * Saves the current shopper's account surface.
	 *
	 * @param int $user_id User id.
	 */
	public function save_account( int $user_id ): void {
		if ( $user_id <= 0 || (int) get_current_user_id() !== $user_id ) {
			return;
		}

		$this->save_section( RegionalPreferencesComposition::SURFACE_ACCOUNT, $user_id, true );
	}

	/**
	 * Opens one section and dispatches provider rendering.
	 *
	 * @param string $surface    profile|account.
	 * @param int    $user_id    User id.
	 * @param bool   $can_edit   Whether the viewer can edit.
	 * @param bool   $table_wrap Whether profile table wrapping is required.
	 */
	private function render_section( string $surface, int $user_id, bool $can_edit, bool $table_wrap = true ): void {
		if ( $user_id <= 0 || did_action( RegionalPreferencesComposition::ACTION_RENDERED ) > 0 ) {
			return;
		}

		/**
		 * Marks the Regional Preferences shell as rendered.
		 *
		 * @since 1.3.0
		 */
		do_action( RegionalPreferencesComposition::ACTION_RENDERED );
		$context = RegionalPreferencesComposition::context( $surface, $user_id, $can_edit );

		if ( $table_wrap ) {
			echo '<h2>' . esc_html__( 'Regional preferences', 'universal-multicurrency' ) . '</h2>';
			echo '<table class="form-table" role="presentation"><tbody>';
		} else {
			echo '<legend>' . esc_html__( 'Regional preferences', 'universal-multicurrency' ) . '</legend>';
			echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
			echo '<table class="form-table" role="presentation"><tbody>';
		}

		/**
		 * Filters provider-claimed Regional Preferences slots.
		 *
		 * @since 1.3.0
		 *
		 * @param array<int, string> $slots Claimed slots.
		 */
		$claimed = apply_filters( RegionalPreferencesComposition::FILTER_CLAIMED_SLOTS, array() );

		/**
		 * Renders fields supplied by Regional Preferences providers.
		 *
		 * @since 1.3.0
		 *
		 * @param array{surface: string, user_id: int, can_edit: bool} $context Composition context.
		 */
		do_action( RegionalPreferencesComposition::ACTION_RENDER, $context );

		if ( ! in_array( RegionalPreferencesComposition::SLOT_LANGUAGE, $claimed, true ) ) {
			$this->render_language_fallback();
		}

		if ( ! in_array( RegionalPreferencesComposition::SLOT_CURRENCY, $claimed, true ) ) {
			$this->render_currency_fallback( $user_id );
		}

		echo '</tbody></table>';

		if ( ! $table_wrap ) {
			echo '</p>';
		}
	}

	/**
	 * Dispatches one provider save attempt.
	 *
	 * @param string $surface  profile|account.
	 * @param int    $user_id  User id.
	 * @param bool   $can_edit Whether the viewer can edit.
	 */
	private function save_section( string $surface, int $user_id, bool $can_edit ): void {
		if ( $user_id <= 0 || ! $can_edit || did_action( RegionalPreferencesComposition::ACTION_SAVE_STARTED ) > 0 ) {
			return;
		}

		/**
		 * Marks Regional Preferences saving as started.
		 *
		 * @since 1.3.0
		 */
		do_action( RegionalPreferencesComposition::ACTION_SAVE_STARTED );

		/**
		 * Saves fields supplied by Regional Preferences providers.
		 *
		 * @since 1.3.0
		 *
		 * @param array{surface: string, user_id: int, can_edit: bool} $context Composition context.
		 */
		do_action(
			RegionalPreferencesComposition::ACTION_SAVE,
			RegionalPreferencesComposition::context( $surface, $user_id, $can_edit )
		);
	}

	/**
	 * Renders the read-only site-language fallback.
	 */
	private function render_language_fallback(): void {
		echo '<tr class="um-regional-preference-language-fallback"><th>';
		echo esc_html__( 'Language', 'universal-multicurrency' );
		echo '</th><td>';
		printf(
			'<input type="text" value="%s" class="regular-text" disabled="disabled" />',
			esc_attr( SiteDefaultLanguage::label() )
		);
		echo '<p class="description">';
		echo esc_html__( 'Multilingual support is not currently available.', 'universal-multicurrency' );
		echo '</p></td></tr>';
	}

	/**
	 * Renders the read-only store-currency fallback.
	 *
	 * @param int $user_id User id.
	 */
	private function render_currency_fallback( int $user_id ): void {
		$state = $this->preference->get_state( $user_id );
		$label = is_array( $state ) ? (string) $state['label'] : __( 'Store default (unavailable)', 'universal-multicurrency' );

		echo '<tr class="um-regional-preference-currency-fallback"><th>';
		echo esc_html__( 'Currency', 'universal-multicurrency' );
		echo '</th><td>';
		printf(
			'<input type="text" value="%s" class="regular-text" disabled="disabled" />',
			esc_attr( $label )
		);
		echo '<p class="description">';
		echo esc_html__( 'Multicurrency support is not currently available.', 'universal-multicurrency' );
		echo '</p></td></tr>';
	}

	/**
	 * Whether the viewer can edit the target user.
	 *
	 * @param int $user_id User id.
	 */
	private function can_edit_user( int $user_id ): bool {
		$current = (int) get_current_user_id();

		if ( $current > 0 && $current === $user_id ) {
			return true;
		}

		return current_user_can( 'edit_user', $user_id );
	}
}
