<?php
/**
 * Normalized Display switcher settings value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Immutable, sanitized Display switcher configuration.
 */
final class SwitcherSettings {

	public const PLACEMENT_MANUAL = 'manual';

	public const PLACEMENT_FLOATING_SIDE = 'floating_side';

	public const PLACEMENT_STICKY_FOOTER = 'sticky_footer';

	public const STYLE_DROPDOWN = 'dropdown';

	public const STYLE_HORIZONTAL_LIST = 'horizontal_list';

	public const SIDE_LEFT = 'left';

	public const SIDE_RIGHT = 'right';

	public const ALIGN_TOP = 'top';

	public const ALIGN_MIDDLE = 'middle';

	public const ALIGN_BOTTOM = 'bottom';

	public const THEME_AUTOMATIC = 'automatic';

	public const THEME_LIGHT = 'light';

	public const THEME_DARK = 'dark';

	public const SIZE_COMPACT = 'compact';

	public const SIZE_STANDARD = 'standard';

	public const SIZE_LARGE = 'large';

	public const SHAPE_SLIGHT = 'slight';

	public const SHAPE_ROUNDED = 'rounded';

	public const SHAPE_PILL = 'pill';

	private const EDGE_OFFSET_MIN = 0;

	private const EDGE_OFFSET_MAX = 200;

	private const VERTICAL_OFFSET_MIDDLE_MIN = -300;

	private const VERTICAL_OFFSET_MIDDLE_MAX = 300;

	private const VERTICAL_OFFSET_EDGE_MIN = 0;

	private const VERTICAL_OFFSET_EDGE_MAX = 500;

	private const BOTTOM_OFFSET_MIN = 0;

	private const BOTTOM_OFFSET_MAX = 500;

	/**
	 * Whether the storefront switcher is enabled.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Placement mode.
	 *
	 * @var string
	 */
	private string $placement;

	/**
	 * Presentation style.
	 *
	 * @var string
	 */
	private string $style;

	/**
	 * Position settings.
	 *
	 * @var array<string, int|string>
	 */
	private array $position;

	/**
	 * Content visibility toggles.
	 *
	 * @var array<string, bool>
	 */
	private array $content;

	/**
	 * Appearance tokens.
	 *
	 * @var array<string, string>
	 */
	private array $appearance;

	/**
	 * Behavior toggles.
	 *
	 * @var array<string, bool>
	 */
	private array $behavior;

	/**
	 * Device visibility toggles.
	 *
	 * @var array<string, bool>
	 */
	private array $visibility;

	/**
	 * Whether style was coerced during normalization.
	 *
	 * @var bool
	 */
	private bool $style_coerced;

	/**
	 * Builds settings from a raw array.
	 *
	 * @param array<string, mixed> $raw Raw display settings.
	 */
	public static function from_array( array $raw ): self {
		$defaults = self::default_array();
		$merged   = array_replace_recursive( $defaults, is_array( $raw ) ? $raw : array() );

		$placement = self::sanitize_enum(
			(string) ( $merged['placement'] ?? self::PLACEMENT_MANUAL ),
			array( self::PLACEMENT_MANUAL, self::PLACEMENT_FLOATING_SIDE, self::PLACEMENT_STICKY_FOOTER ),
			self::PLACEMENT_MANUAL
		);

		$style = self::sanitize_enum(
			(string) ( $merged['style'] ?? self::STYLE_DROPDOWN ),
			array( self::STYLE_DROPDOWN, self::STYLE_HORIZONTAL_LIST ),
			self::STYLE_DROPDOWN
		);

		$style_coerced = false;

		if ( self::STYLE_HORIZONTAL_LIST === $style && self::PLACEMENT_MANUAL !== $placement ) {
			$style         = self::STYLE_DROPDOWN;
			$style_coerced = true;
		}

		$vertical_alignment = self::sanitize_enum(
			(string) ( $merged['position']['vertical_alignment'] ?? self::ALIGN_MIDDLE ),
			array( self::ALIGN_TOP, self::ALIGN_MIDDLE, self::ALIGN_BOTTOM ),
			self::ALIGN_MIDDLE
		);

		$vertical_offset = self::clamp_vertical_offset(
			self::to_int( $merged['position']['vertical_offset'] ?? 0 ),
			$vertical_alignment
		);

		$content = self::sanitize_content( $merged['content'] ?? array() );

		$instance = new self(
			! empty( $merged['enabled'] ),
			$placement,
			$style,
			array(
				'side'               => self::sanitize_enum(
					(string) ( $merged['position']['side'] ?? self::SIDE_RIGHT ),
					array( self::SIDE_LEFT, self::SIDE_RIGHT ),
					self::SIDE_RIGHT
				),
				'vertical_alignment' => $vertical_alignment,
				'vertical_offset'    => $vertical_offset,
				'edge_offset'        => self::clamp_int(
					self::to_int( $merged['position']['edge_offset'] ?? 16 ),
					self::EDGE_OFFSET_MIN,
					self::EDGE_OFFSET_MAX
				),
				'bottom_offset'      => self::clamp_int(
					self::to_int( $merged['position']['bottom_offset'] ?? 16 ),
					self::BOTTOM_OFFSET_MIN,
					self::BOTTOM_OFFSET_MAX
				),
			),
			$content,
			array(
				'theme' => self::sanitize_enum(
					(string) ( $merged['appearance']['theme'] ?? self::THEME_AUTOMATIC ),
					array( self::THEME_AUTOMATIC, self::THEME_LIGHT, self::THEME_DARK ),
					self::THEME_AUTOMATIC
				),
				'size'  => self::sanitize_enum(
					(string) ( $merged['appearance']['size'] ?? self::SIZE_STANDARD ),
					array( self::SIZE_COMPACT, self::SIZE_STANDARD, self::SIZE_LARGE ),
					self::SIZE_STANDARD
				),
				'shape' => self::sanitize_enum(
					(string) ( $merged['appearance']['shape'] ?? self::SHAPE_ROUNDED ),
					array( self::SHAPE_SLIGHT, self::SHAPE_ROUNDED, self::SHAPE_PILL ),
					self::SHAPE_ROUNDED
				),
			),
			array(
				'remember_selection' => ! isset( $merged['behavior']['remember_selection'] ) || (bool) $merged['behavior']['remember_selection'],
				'active_first'       => ! isset( $merged['behavior']['active_first'] ) || (bool) $merged['behavior']['active_first'],
			),
			array(
				'desktop' => ! isset( $merged['visibility']['desktop'] ) || (bool) $merged['visibility']['desktop'],
				'mobile'  => ! isset( $merged['visibility']['mobile'] ) || (bool) $merged['visibility']['mobile'],
			),
			$style_coerced
		);

		return $instance;
	}

	/**
	 * Default display settings array.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_array(): array {
		return array(
			'enabled'    => false,
			'placement'  => self::PLACEMENT_MANUAL,
			'style'      => self::STYLE_DROPDOWN,
			'position'   => array(
				'side'               => self::SIDE_RIGHT,
				'vertical_alignment' => self::ALIGN_MIDDLE,
				'vertical_offset'    => 0,
				'edge_offset'        => 16,
				'bottom_offset'      => 16,
			),
			'content'    => array(
				'show_code'   => true,
				'show_symbol' => true,
				'show_name'   => false,
			),
			'appearance' => array(
				'theme' => self::THEME_AUTOMATIC,
				'size'  => self::SIZE_STANDARD,
				'shape' => self::SHAPE_ROUNDED,
			),
			'behavior'   => array(
				'remember_selection' => true,
				'active_first'       => true,
			),
			'visibility' => array(
				'desktop' => true,
				'mobile'  => true,
			),
		);
	}

	/**
	 * Sanitizes raw display input for persistence.
	 *
	 * @param mixed $raw Raw display settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize_raw( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return self::default_array();
		}

		return self::from_array( $raw )->to_array();
	}

	/**
	 * Whether visibility settings are valid for a save attempt.
	 *
	 * When the switcher is enabled, at least one device channel must remain on.
	 *
	 * @param array<string, mixed> $raw Raw display settings before normalization.
	 */
	public static function visibility_valid_for_save( array $raw ): bool {
		if ( ! self::is_truthy( $raw['enabled'] ?? false ) ) {
			return true;
		}

		$desktop = self::is_truthy( $raw['visibility']['desktop'] ?? true );
		$mobile  = self::is_truthy( $raw['visibility']['mobile'] ?? true );

		return $desktop || $mobile;
	}

	/**
	 * Interprets checkbox and boolean-like values from POST or settings arrays.
	 *
	 * @param mixed $value Raw value.
	 */
	private static function is_truthy( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}

		$normalized = strtolower( trim( (string) $value ) );

		if ( '' === $normalized ) {
			return false;
		}

		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Creates normalized settings from validated constructor arguments.
	 *
	 * @param bool                  $enabled        Whether enabled.
	 * @param string                $placement      Placement mode.
	 * @param string                $style          Presentation style.
	 * @param array<string, mixed>  $position       Position settings.
	 * @param array<string, bool>   $content        Content toggles.
	 * @param array<string, string> $appearance     Appearance tokens.
	 * @param array<string, bool>   $behavior       Behavior toggles.
	 * @param array<string, bool>   $visibility     Visibility toggles.
	 * @param bool                  $style_coerced  Whether style was coerced.
	 */
	private function __construct(
		bool $enabled,
		string $placement,
		string $style,
		array $position,
		array $content,
		array $appearance,
		array $behavior,
		array $visibility,
		bool $style_coerced
	) {
		$this->enabled       = $enabled;
		$this->placement     = $placement;
		$this->style         = $style;
		$this->position      = $position;
		$this->content       = $content;
		$this->appearance    = $appearance;
		$this->behavior      = $behavior;
		$this->visibility    = $visibility;
		$this->style_coerced = $style_coerced;
	}

	/**
	 * Whether the storefront switcher is enabled.
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Placement mode.
	 */
	public function placement(): string {
		return $this->placement;
	}

	/**
	 * Presentation style.
	 */
	public function style(): string {
		return $this->style;
	}

	/**
	 * Whether style was coerced to dropdown for an automatic placement.
	 */
	public function was_style_coerced(): bool {
		return $this->style_coerced;
	}

	/**
	 * Whether automatic placement should render on the storefront.
	 */
	public function should_render_automatic(): bool {
		return $this->enabled
			&& in_array( $this->placement, array( self::PLACEMENT_FLOATING_SIDE, self::PLACEMENT_STICKY_FOOTER ), true );
	}

	/**
	 * Whether the customer selection should be remembered between visits.
	 */
	public function remember_selection(): bool {
		return $this->behavior['remember_selection'];
	}

	/**
	 * Whether the active currency should appear first in lists.
	 */
	public function active_first(): bool {
		return $this->behavior['active_first'];
	}

	/**
	 * Position settings array.
	 *
	 * @return array<string, int|string>
	 */
	public function position(): array {
		return $this->position;
	}

	/**
	 * Content visibility toggles.
	 *
	 * @return array<string, bool>
	 */
	public function content(): array {
		return $this->content;
	}

	/**
	 * Appearance tokens.
	 *
	 * @return array<string, string>
	 */
	public function appearance(): array {
		return $this->appearance;
	}

	/**
	 * Device visibility toggles.
	 *
	 * @return array<string, bool>
	 */
	public function visibility(): array {
		return $this->visibility;
	}

	/**
	 * Exports the normalized settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'enabled'    => $this->enabled,
			'placement'  => $this->placement,
			'style'      => $this->style,
			'position'   => $this->position,
			'content'    => $this->content,
			'appearance' => $this->appearance,
			'behavior'   => $this->behavior,
			'visibility' => $this->visibility,
		);
	}

	/**
	 * CSS modifier classes derived from settings.
	 *
	 * @param bool $preview Whether preview mode is active.
	 * @return array<int, string>
	 */
	public function modifier_classes( bool $preview = false ): array {
		$classes = array(
			'umc-switcher',
			'umc-switcher--' . $this->style_to_class( $this->style ),
			'umc-switcher--' . $this->placement_to_class( $this->placement ),
		);

		if ( in_array( $this->placement, array( self::PLACEMENT_FLOATING_SIDE, self::PLACEMENT_STICKY_FOOTER ), true ) ) {
			$classes[] = 'umc-switcher--side-' . $this->position['side'];
		}

		if ( self::PLACEMENT_FLOATING_SIDE === $this->placement ) {
			$classes[] = 'umc-switcher--align-' . $this->position['vertical_alignment'];
		}

		$classes[] = 'umc-switcher--theme-' . $this->appearance['theme'];
		$classes[] = 'umc-switcher--size-' . $this->appearance['size'];
		$classes[] = 'umc-switcher--shape-' . $this->appearance['shape'];

		if ( ! $this->visibility['desktop'] ) {
			$classes[] = 'umc-switcher--hide-desktop';
		}

		if ( ! $this->visibility['mobile'] ) {
			$classes[] = 'umc-switcher--hide-mobile';
		}

		if ( $preview ) {
			$classes[] = 'umc-switcher--preview';
		}

		return $classes;
	}

	/**
	 * Inline CSS custom properties for positioning and theming.
	 *
	 * @return array<string, string>
	 */
	public function css_variables(): array {
		return array(
			'--umc-edge-offset'      => (string) $this->position['edge_offset'] . 'px',
			'--umc-vertical-offset'  => (string) $this->position['vertical_offset'] . 'px',
			'--umc-bottom-offset'    => (string) $this->position['bottom_offset'] . 'px',
			'--umc-switcher-z-index' => '9990',
		);
	}

	/**
	 * Maps stored style to CSS class suffix.
	 *
	 * @param string $style Stored style value.
	 */
	private function style_to_class( string $style ): string {
		if ( self::STYLE_HORIZONTAL_LIST === $style ) {
			return 'horizontal-list';
		}

		return $style;
	}

	/**
	 * Maps stored placement to CSS placement class suffix.
	 *
	 * @param string $placement Stored placement value.
	 */
	private function placement_to_class( string $placement ): string {
		if ( self::PLACEMENT_STICKY_FOOTER === $placement ) {
			return 'floating-bottom';
		}

		return str_replace( '_', '-', $placement );
	}

	/**
	 * Normalizes content visibility toggles with a code fallback.
	 *
	 * @param array<string, mixed> $content Raw content toggles.
	 * @return array<string, bool>
	 */
	private static function sanitize_content( array $content ): array {
		$clean = array(
			'show_code'   => ! empty( $content['show_code'] ),
			'show_symbol' => ! empty( $content['show_symbol'] ),
			'show_name'   => ! empty( $content['show_name'] ),
		);

		if ( ! $clean['show_code'] && ! $clean['show_symbol'] && ! $clean['show_name'] ) {
			$clean['show_code'] = true;
		}

		return $clean;
	}

	/**
	 * Coerces a raw value to an integer, defaulting to zero.
	 *
	 * @param mixed $raw Raw value.
	 */
	private static function to_int( mixed $raw ): int {
		if ( ! is_numeric( $raw ) ) {
			return 0;
		}

		return (int) $raw;
	}

	/**
	 * Clamps an integer to an inclusive min/max range.
	 *
	 * @param int $value Raw value.
	 * @param int $min   Minimum inclusive.
	 * @param int $max   Maximum inclusive.
	 */
	private static function clamp_int( int $value, int $min, int $max ): int {
		if ( $value < $min ) {
			return $min;
		}

		if ( $value > $max ) {
			return $max;
		}

		return $value;
	}

	/**
	 * Clamps vertical offset according to alignment-specific bounds.
	 *
	 * @param int    $value     Raw offset.
	 * @param string $alignment Vertical alignment.
	 */
	private static function clamp_vertical_offset( int $value, string $alignment ): int {
		if ( self::ALIGN_MIDDLE === $alignment ) {
			return self::clamp_int( $value, self::VERTICAL_OFFSET_MIDDLE_MIN, self::VERTICAL_OFFSET_MIDDLE_MAX );
		}

		return self::clamp_int( $value, self::VERTICAL_OFFSET_EDGE_MIN, self::VERTICAL_OFFSET_EDGE_MAX );
	}

	/**
	 * Returns the value when allowed, otherwise the fallback.
	 *
	 * @param string             $value    Raw enum value.
	 * @param array<int, string> $allowed  Allowed values.
	 * @param string             $fallback Fallback value.
	 */
	private static function sanitize_enum( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
