<?php
/**
 * Single geographic routing rule.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Geo;

/**
 * Immutable routing rule: country, region preset, or other-countries catch-all.
 */
final class GeoRoutingRule {

	public const TYPE_COUNTRY = 'country';

	public const TYPE_REGION = 'region';

	public const TYPE_OTHER = 'other';

	public const MAX_RULES = 250;

	public const ID_PATTERN = '/^rule_[a-f0-9]{8}$/';

	/**
	 * Stable rule identifier.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Match type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Country code or region id; empty for other.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Assigned currency code.
	 *
	 * @var string
	 */
	private string $currency;

	/**
	 * Builds a rule from a sanitized array row.
	 *
	 * @param array<string, mixed> $row Sanitized rule row.
	 */
	public static function from_array( array $row ): ?self {
		$id = isset( $row['id'] ) ? strtolower( trim( (string) $row['id'] ) ) : '';

		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			return null;
		}

		$type = isset( $row['type'] ) ? strtolower( trim( (string) $row['type'] ) ) : '';

		if ( ! in_array( $type, array( self::TYPE_COUNTRY, self::TYPE_REGION, self::TYPE_OTHER ), true ) ) {
			return null;
		}

		$value = isset( $row['value'] ) ? strtoupper( trim( (string) $row['value'] ) ) : '';

		if ( self::TYPE_COUNTRY === $type ) {
			if ( 1 !== preg_match( '/^[A-Z]{2}$/', $value ) ) {
				return null;
			}
		} elseif ( self::TYPE_REGION === $type ) {
			$value = strtolower( $value );

			if ( ! GeoRegionRegistry::is_valid_region( $value ) ) {
				return null;
			}
		} else {
			$value = '';
		}

		$currency = isset( $row['currency'] ) ? strtoupper( trim( (string) $row['currency'] ) ) : '';

		if ( 1 !== preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			return null;
		}

		return new self( $id, $type, $value, $currency );
	}

	/**
	 * Generates a new stable rule id.
	 */
	public static function generate_id(): string {
		return 'rule_' . bin2hex( random_bytes( 4 ) );
	}

	/**
	 * Constructs a routing rule.
	 *
	 * @param string $id       Rule id.
	 * @param string $type     Rule type.
	 * @param string $value    Country or region value.
	 * @param string $currency Currency code.
	 */
	private function __construct( string $id, string $type, string $value, string $currency ) {
		$this->id       = $id;
		$this->type     = $type;
		$this->value    = $value;
		$this->currency = $currency;
	}

	/**
	 * Stable rule identifier.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Rule match type.
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Country code, region id, or empty for catch-all.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Assigned currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Serializes the rule for persistence.
	 *
	 * @return array<string, string>
	 */
	public function to_array(): array {
		return array(
			'id'       => $this->id,
			'type'     => $this->type,
			'value'    => self::TYPE_REGION === $this->type ? $this->value : ( self::TYPE_COUNTRY === $this->type ? $this->value : '' ),
			'currency' => $this->currency,
		);
	}
}
