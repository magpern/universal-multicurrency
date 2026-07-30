<?php
/**
 * Normalized Geo Detection settings value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Immutable, sanitized Geo Detection configuration subtree.
 */
final class GeoDetectionSettings {

	public const MODE_FIRST_VISIT = 'first_visit';

	public const MODE_SESSION = 'session';

	public const MODE_UNTIL_MANUAL = 'until_manual';

	public const PRECEDENCE_BILLING = 'billing';

	public const PRECEDENCE_SHIPPING = 'shipping';

	/**
	 * Whether geo detection is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Detection persistence mode.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Technical fallback currency code.
	 *
	 * @var string
	 */
	private string $fallback_currency;

	/**
	 * Whether WooCommerce geolocation fallback is allowed.
	 *
	 * @var bool
	 */
	private bool $allow_wc_geolocation_fallback;

	/**
	 * Ordered geographic routing rules.
	 *
	 * @var list<GeoRoutingRule>
	 */
	private array $rules;

	/**
	 * Whether checkout locks currency on entry.
	 *
	 * @var bool
	 */
	private bool $lock_on_entry;

	/**
	 * Whether billing country changes trigger re-evaluation.
	 *
	 * @var bool
	 */
	private bool $reevaluate_on_billing_change;

	/**
	 * Whether shipping country changes trigger re-evaluation.
	 *
	 * @var bool
	 */
	private bool $reevaluate_on_shipping_change;

	/**
	 * Checkout billing vs shipping country precedence.
	 *
	 * @var string
	 */
	private string $country_precedence;

	/**
	 * Builds settings from a sanitized array.
	 *
	 * @param array<string, mixed> $data Sanitized geo subtree.
	 */
	public static function from_array( array $data ): self {
		$rules = array();

		if ( isset( $data['rules'] ) && is_array( $data['rules'] ) ) {
			foreach ( $data['rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$rule = GeoRoutingRule::from_array( $row );

				if ( null !== $rule ) {
					$rules[] = $rule;
				}
			}
		}

		$checkout = is_array( $data['checkout'] ?? null ) ? $data['checkout'] : array();

		return new self(
			! empty( $data['enabled'] ),
			self::sanitize_mode( $data['mode'] ?? self::MODE_FIRST_VISIT ),
			self::sanitize_currency_code( $data['fallback_currency'] ?? '' ),
			! isset( $data['allow_wc_geolocation_fallback'] ) || (bool) $data['allow_wc_geolocation_fallback'],
			$rules,
			! isset( $checkout['lock_on_entry'] ) || (bool) $checkout['lock_on_entry'],
			! empty( $checkout['reevaluate_on_billing_change'] ),
			! empty( $checkout['reevaluate_on_shipping_change'] ),
			self::sanitize_precedence( $checkout['country_precedence'] ?? self::PRECEDENCE_BILLING )
		);
	}

	/**
	 * Default geo settings array for persistence.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_array(): array {
		return array(
			'enabled'                       => false,
			'mode'                          => self::MODE_FIRST_VISIT,
			'fallback_currency'             => '',
			'allow_wc_geolocation_fallback' => true,
			'rules'                         => array(),
			'checkout'                      => array(
				'lock_on_entry'                 => true,
				'reevaluate_on_billing_change'  => false,
				'reevaluate_on_shipping_change' => false,
				'country_precedence'            => self::PRECEDENCE_BILLING,
			),
		);
	}

	/**
	 * Forgiving sanitizer for persisted settings (drops invalid rules).
	 *
	 * @param mixed $raw Raw geo subtree.
	 * @return array<string, mixed>
	 */
	public static function sanitize_raw( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::default_array();
		}

		return self::from_array(
			array(
				'enabled'                       => $raw['enabled'] ?? false,
				'mode'                          => $raw['mode'] ?? self::MODE_FIRST_VISIT,
				'fallback_currency'             => $raw['fallback_currency'] ?? '',
				'allow_wc_geolocation_fallback' => $raw['allow_wc_geolocation_fallback'] ?? true,
				'rules'                         => $raw['rules'] ?? array(),
				'checkout'                      => $raw['checkout'] ?? array(),
			)
		)->to_array();
	}

	/**
	 * Constructs geo detection settings.
	 *
	 * @param bool                       $enabled                       Whether geo is enabled.
	 * @param string                     $mode                          Detection mode.
	 * @param string                     $fallback_currency             Technical fallback code or ''.
	 * @param bool                       $allow_wc_geolocation_fallback WC geolocation fallback.
	 * @param array<int, GeoRoutingRule> $rules                         Ordered rules.
	 * @param bool                       $lock_on_entry                 Checkout lock.
	 * @param bool                       $reevaluate_on_billing_change  Billing re-eval.
	 * @param bool                       $reevaluate_on_shipping_change Shipping re-eval.
	 * @param string                     $country_precedence            Billing vs shipping.
	 */
	private function __construct(
		bool $enabled,
		string $mode,
		string $fallback_currency,
		bool $allow_wc_geolocation_fallback,
		array $rules,
		bool $lock_on_entry,
		bool $reevaluate_on_billing_change,
		bool $reevaluate_on_shipping_change,
		string $country_precedence
	) {
		$this->enabled                       = $enabled;
		$this->mode                          = $mode;
		$this->fallback_currency             = $fallback_currency;
		$this->allow_wc_geolocation_fallback = $allow_wc_geolocation_fallback;
		$this->rules                         = $rules;
		$this->lock_on_entry                 = $lock_on_entry;
		$this->reevaluate_on_billing_change  = $reevaluate_on_billing_change;
		$this->reevaluate_on_shipping_change = $reevaluate_on_shipping_change;
		$this->country_precedence            = $country_precedence;
	}

	/**
	 * Whether geo detection is enabled.
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * The configured detection mode.
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Technical fallback currency code (empty uses store default).
	 */
	public function fallback_currency(): string {
		return $this->fallback_currency;
	}

	/**
	 * Whether WooCommerce geolocation fallback is allowed.
	 */
	public function allow_wc_geolocation_fallback(): bool {
		return $this->allow_wc_geolocation_fallback;
	}

	/**
	 * Ordered geographic routing rules.
	 *
	 * @return list<GeoRoutingRule>
	 */
	public function rules(): array {
		return $this->rules;
	}

	/**
	 * Whether checkout locks currency on entry.
	 */
	public function lock_on_entry(): bool {
		return $this->lock_on_entry;
	}

	/**
	 * Whether billing country changes trigger re-evaluation.
	 */
	public function reevaluate_on_billing_change(): bool {
		return $this->reevaluate_on_billing_change;
	}

	/**
	 * Whether shipping country changes trigger re-evaluation.
	 */
	public function reevaluate_on_shipping_change(): bool {
		return $this->reevaluate_on_shipping_change;
	}

	/**
	 * Checkout billing vs shipping country precedence.
	 */
	public function country_precedence(): string {
		return $this->country_precedence;
	}

	/**
	 * Serializes settings for persistence.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$rules = array();

		foreach ( $this->rules as $rule ) {
			$rules[] = $rule->to_array();
		}

		return array(
			'enabled'                       => $this->enabled,
			'mode'                          => $this->mode,
			'fallback_currency'             => $this->fallback_currency,
			'allow_wc_geolocation_fallback' => $this->allow_wc_geolocation_fallback,
			'rules'                         => $rules,
			'checkout'                      => array(
				'lock_on_entry'                 => $this->lock_on_entry,
				'reevaluate_on_billing_change'  => $this->reevaluate_on_billing_change,
				'reevaluate_on_shipping_change' => $this->reevaluate_on_shipping_change,
				'country_precedence'            => $this->country_precedence,
			),
		);
	}

	/**
	 * Normalizes a detection mode string.
	 *
	 * @param mixed $raw Raw mode.
	 */
	public static function sanitize_mode( mixed $raw ): string {
		$mode = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		$allowed = array(
			self::MODE_FIRST_VISIT,
			self::MODE_SESSION,
			self::MODE_UNTIL_MANUAL,
		);

		return in_array( $mode, $allowed, true ) ? $mode : self::MODE_FIRST_VISIT;
	}

	/**
	 * Normalizes checkout country precedence.
	 *
	 * @param mixed $raw Raw precedence.
	 */
	public static function sanitize_precedence( mixed $raw ): string {
		$value = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		return self::PRECEDENCE_SHIPPING === $value ? self::PRECEDENCE_SHIPPING : self::PRECEDENCE_BILLING;
	}

	/**
	 * Normalizes a fallback currency code.
	 *
	 * @param mixed $raw Raw currency code.
	 */
	public static function sanitize_currency_code( mixed $raw ): string {
		$code = is_string( $raw ) ? strtoupper( trim( $raw ) ) : '';

		if ( '' === $code ) {
			return '';
		}

		return 1 === preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
	}
}
