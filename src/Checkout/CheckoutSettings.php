<?php
/**
 * Normalized checkout policy settings value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Checkout;

/**
 * Immutable, sanitized checkout currency policy configuration.
 */
final class CheckoutSettings {

	public const MODE_SELECTED = 'selected';

	public const MODE_STORE = 'store';

	/**
	 * Checkout currency mode.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Whether transition notices are shown to customers.
	 *
	 * @var bool
	 */
	private bool $show_notice;

	/**
	 * Builds settings from a sanitized array.
	 *
	 * @param array<string, mixed> $data Sanitized checkout subtree.
	 */
	public static function from_array( array $data ): self {
		return new self(
			self::sanitize_mode( $data['mode'] ?? self::MODE_SELECTED ),
			! array_key_exists( 'show_notice', $data ) || (bool) $data['show_notice']
		);
	}

	/**
	 * Default checkout settings array for persistence.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_array(): array {
		return array(
			'mode'        => self::MODE_SELECTED,
			'show_notice' => true,
		);
	}

	/**
	 * Sanitizes raw checkout settings input.
	 *
	 * @param mixed $raw Raw checkout subtree.
	 * @return array<string, mixed>
	 */
	public static function sanitize_raw( mixed $raw ): array {
		$defaults = self::default_array();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		return array(
			'mode'        => self::sanitize_mode( $raw['mode'] ?? $defaults['mode'] ),
			'show_notice' => ! array_key_exists( 'show_notice', $raw ) || (bool) $raw['show_notice'],
		);
	}

	/**
	 * Normalizes a checkout mode string.
	 *
	 * @param mixed $raw Raw mode value.
	 */
	public static function sanitize_mode( mixed $raw ): string {
		$mode = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';

		if ( self::MODE_STORE === $mode ) {
			return self::MODE_STORE;
		}

		return self::MODE_SELECTED;
	}

	/**
	 * Creates checkout settings.
	 *
	 * @param string $mode        Checkout mode.
	 * @param bool   $show_notice Whether to show customer notices.
	 */
	public function __construct( string $mode, bool $show_notice ) {
		$this->mode        = self::sanitize_mode( $mode );
		$this->show_notice = $show_notice;
	}

	/**
	 * The configured checkout currency mode.
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Whether customer transition notices are enabled.
	 */
	public function show_notice(): bool {
		return $this->show_notice;
	}

	/**
	 * Whether checkout keeps the shopper-selected currency.
	 */
	public function is_selected_mode(): bool {
		return self::MODE_SELECTED === $this->mode;
	}

	/**
	 * Whether checkout switches to store currency at entry.
	 */
	public function is_store_mode(): bool {
		return self::MODE_STORE === $this->mode;
	}

	/**
	 * Exports the sanitized array shape.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'mode'        => $this->mode,
			'show_notice' => $this->show_notice,
		);
	}
}
