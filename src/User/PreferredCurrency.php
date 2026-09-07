<?php
/**
 * Authenticated preferred-currency preference.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\User;

use UMC\CurrencyRegistry;
use UMC\Rates\RateProvider;
use WP_Error;

/**
 * Owns preferred-currency storage and effective UI state.
 *
 * Invalid stored values are retained and fall back to the store base currency.
 */
final class PreferredCurrency {

	public const META_KEY = 'umc_preferred_currency';

	public const SOURCE_STORED           = 'stored';
	public const SOURCE_FALLBACK_DEFAULT = 'fallback_default';
	public const SOURCE_INVALID_FALLBACK = 'invalid_fallback';

	/**
	 * Creates the preference service.
	 *
	 * @param CurrencyRegistry $registry Currency registry.
	 * @param RateProvider     $rates    Exchange-rate provider.
	 */
	public function __construct(
		private CurrencyRegistry $registry,
		private RateProvider $rates
	) {
	}

	/**
	 * Whether UMC can provide a base/selectable currency authority.
	 */
	public function is_available(): bool {
		return '' !== $this->registry->get_base_code();
	}

	/**
	 * Stored valid preferred code, or null.
	 *
	 * @param int $user_id User id.
	 */
	public function get( int $user_id ): ?string {
		if ( $user_id <= 0 || ! $this->can_view( $user_id ) ) {
			return null;
		}

		$raw = $this->raw( $user_id );

		return null !== $raw && $this->is_allowed_code( $raw ) ? $raw : null;
	}

	/**
	 * Effective preference state for UI and public consumers.
	 *
	 * @param int $user_id User id.
	 * @return array{
	 *   available: bool,
	 *   editable: bool,
	 *   stored: ?string,
	 *   effective: string,
	 *   source: string,
	 *   label: string,
	 *   options: array<string, string>,
	 *   unavailable_message: ?string
	 * }|null
	 */
	public function get_state( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}

		$available  = $this->is_available();
		$can_view   = $this->can_view( $user_id );
		$can_edit   = $this->can_edit( $user_id );
		$base       = $this->registry->get_base_code();
		$options    = $this->options();
		$stored_raw = ( $can_view && $available ) ? $this->raw( $user_id ) : null;
		$source     = self::SOURCE_FALLBACK_DEFAULT;
		$effective  = $base;
		$label      = sprintf(
			/* translators: %s: currency label */
			__( '%s (store default)', 'universal-multicurrency' ),
			$this->currency_label( $base )
		);

		if ( null !== $stored_raw ) {
			if ( $this->is_allowed_code( $stored_raw ) ) {
				$source    = self::SOURCE_STORED;
				$effective = $stored_raw;
				$label     = $this->currency_label( $stored_raw );
			} else {
				$source = self::SOURCE_INVALID_FALLBACK;
			}
		}

		$unavailable = $available
			? null
			: __( 'Multicurrency support is not currently available.', 'universal-multicurrency' );

		return array(
			'available'           => $available,
			'editable'            => $available && $can_edit && array() !== $options,
			'stored'              => $can_view ? $stored_raw : null,
			'effective'           => $effective,
			'source'              => $source,
			'label'               => $label,
			'options'             => $options,
			'unavailable_message' => $unavailable,
		);
	}

	/**
	 * Sets or clears the preferred currency.
	 *
	 * @param int         $user_id User id.
	 * @param string|null $code    Currency code, or null/empty to clear.
	 * @return true|WP_Error
	 */
	public function set( int $user_id, ?string $code ) {
		if ( $user_id <= 0 ) {
			return new WP_Error( 'umc_invalid_user', __( 'Invalid user.', 'universal-multicurrency' ) );
		}

		if ( ! $this->can_edit( $user_id ) ) {
			return new WP_Error( 'umc_forbidden', __( 'You are not allowed to edit this preference.', 'universal-multicurrency' ) );
		}

		if ( ! $this->is_available() ) {
			return new WP_Error( 'umc_unavailable', __( 'Multicurrency support is not currently available.', 'universal-multicurrency' ) );
		}

		if ( null === $code || '' === trim( $code ) ) {
			delete_user_meta( $user_id, self::META_KEY );
			return true;
		}

		$normalized = strtoupper( trim( $code ) );

		if ( ! $this->is_allowed_code( $normalized ) ) {
			return new WP_Error( 'umc_invalid_currency', __( 'That currency is not available.', 'universal-multicurrency' ) );
		}

		update_user_meta( $user_id, self::META_KEY, $normalized );
		return true;
	}

	/**
	 * Selectable currencies, including base, keyed by ISO code.
	 *
	 * @return array<string, string>
	 */
	public function options(): array {
		$options = array();

		foreach ( $this->selectable_codes() as $code ) {
			$options[ $code ] = $this->currency_label( $code );
		}

		return $options;
	}

	/**
	 * Raw stored meta, including invalid values.
	 *
	 * @param int $user_id User id.
	 */
	public function raw( int $user_id ): ?string {
		$value = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		return strtoupper( trim( $value ) );
	}

	/**
	 * Builds the selectable-code allow-list.
	 *
	 * @return list<string>
	 */
	private function selectable_codes(): array {
		$base  = $this->registry->get_base_code();
		$codes = array( $base );

		foreach ( $this->registry->get_enabled_currencies() as $currency ) {
			$code = $currency->code();

			if ( $code === $base || $this->rates->has_rate( $base, $code ) ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Whether a code is selectable.
	 *
	 * @param string $code Currency code.
	 */
	private function is_allowed_code( string $code ): bool {
		return in_array( strtoupper( trim( $code ) ), $this->selectable_codes(), true );
	}

	/**
	 * Human-readable currency label.
	 *
	 * @param string $code Currency code.
	 */
	private function currency_label( string $code ): string {
		$names = function_exists( 'get_woocommerce_currencies' )
			? get_woocommerce_currencies()
			: array();
		$name  = isset( $names[ $code ] ) && is_string( $names[ $code ] )
			? $names[ $code ]
			: '';

		return '' !== $name ? sprintf( '%s — %s', $code, $name ) : $code;
	}

	/**
	 * Whether the current viewer may read the preference.
	 *
	 * @param int $user_id User id.
	 */
	private function can_view( int $user_id ): bool {
		$current = (int) get_current_user_id();

		if ( $current > 0 && $current === $user_id ) {
			return true;
		}

		return current_user_can( 'edit_user', $user_id );
	}

	/**
	 * Whether the current viewer may edit the preference.
	 *
	 * @param int $user_id User id.
	 */
	private function can_edit( int $user_id ): bool {
		return $this->can_view( $user_id );
	}
}
