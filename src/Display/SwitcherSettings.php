<?php
/**
 * Normalized Display switcher settings value object.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

/**
 * Immutable, sanitized Display switcher configuration (settings schema 6).
 *
 * Presentation is layered: base CSS → preset class → theme/size/shape →
 * sparse structured overrides → responsive bag → Custom CSS (ADR-0022).
 * Schema-5 stores are read through the legacy `appearance.*` and flat
 * `content.*` aliases until the 5 → 6 migration rewrites them.
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

	public const PRESET_DEFAULT = 'default';

	public const PRESET_MINIMAL = 'minimal';

	public const PRESET_PILL = 'pill';

	public const PRESET_COMPACT = 'compact';

	public const PRESET_BORDERLESS = 'borderless';

	public const PRESET_FLOATING = 'floating';

	public const MOTION_NONE = 'none';

	public const MOTION_SUBTLE = 'subtle';

	public const ELEMENT_CODE = 'code';

	public const ELEMENT_SYMBOL = 'symbol';

	public const ELEMENT_NAME = 'name';

	/**
	 * Allowed theme values.
	 *
	 * @var array<int, string>
	 */
	public const THEMES = array( self::THEME_AUTOMATIC, self::THEME_LIGHT, self::THEME_DARK );

	/**
	 * Allowed size values.
	 *
	 * @var array<int, string>
	 */
	public const SIZES = array( self::SIZE_COMPACT, self::SIZE_STANDARD, self::SIZE_LARGE );

	/**
	 * Allowed shape values.
	 *
	 * @var array<int, string>
	 */
	public const SHAPES = array( self::SHAPE_SLIGHT, self::SHAPE_ROUNDED, self::SHAPE_PILL );

	/**
	 * Allowed preset identifiers.
	 *
	 * @var array<int, string>
	 */
	public const PRESETS = array(
		self::PRESET_DEFAULT,
		self::PRESET_MINIMAL,
		self::PRESET_PILL,
		self::PRESET_COMPACT,
		self::PRESET_BORDERLESS,
		self::PRESET_FLOATING,
	);

	/**
	 * Allowed motion levels.
	 *
	 * @var array<int, string>
	 */
	public const MOTIONS = array( self::MOTION_NONE, self::MOTION_SUBTLE );

	/**
	 * Canonical label element sequence used to repair merchant ordering.
	 *
	 * @var array<int, string>
	 */
	public const ELEMENT_SEQUENCE = array( self::ELEMENT_CODE, self::ELEMENT_SYMBOL, self::ELEMENT_NAME );

	/**
	 * Allowlisted structured override tokens.
	 *
	 * Each entry maps a stored key to its value type and public CSS custom
	 * property. Unknown keys and invalid values are dropped, keeping the
	 * persisted override map sparse.
	 *
	 * @var array<string, array{type: string, property: string}>
	 */
	private const OVERRIDE_TOKENS = array(
		'surface'        => array(
			'type'     => 'color',
			'property' => '--umc-switcher-surface',
		),
		'text'           => array(
			'type'     => 'color',
			'property' => '--umc-switcher-text',
		),
		'border'         => array(
			'type'     => 'color',
			'property' => '--umc-switcher-border',
		),
		'hover'          => array(
			'type'     => 'color',
			'property' => '--umc-switcher-hover',
		),
		'selected_bg'    => array(
			'type'     => 'color',
			'property' => '--umc-switcher-selected-bg',
		),
		'focus_ring'     => array(
			'type'     => 'color',
			'property' => '--umc-switcher-focus-ring',
		),
		'radius'         => array(
			'type'     => 'dimension',
			'property' => '--umc-switcher-radius',
		),
		'control_height' => array(
			'type'     => 'dimension',
			'property' => '--umc-switcher-control-height',
		),
		'spacing'        => array(
			'type'     => 'dimension',
			'property' => '--umc-switcher-spacing',
		),
		'font_weight'    => array(
			'type'     => 'font_weight',
			'property' => '--umc-switcher-font-weight',
		),
	);

	/**
	 * Transition durations emitted per motion level.
	 *
	 * @var array<string, string>
	 */
	private const MOTION_DURATIONS = array(
		self::MOTION_NONE   => '0ms',
		self::MOTION_SUBTLE => '150ms',
	);

	private const DIMENSION_MIN = 0;

	private const DIMENSION_MAX = 500;

	private const FONT_WEIGHT_MIN = 400;

	private const FONT_WEIGHT_MAX = 700;

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
	 * Content composition for trigger and menu.
	 *
	 * @var array<string, mixed>
	 */
	private array $content;

	/**
	 * Design tokens: preset, theme, size, shape, overrides, motion.
	 *
	 * @var array<string, mixed>
	 */
	private array $design;

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
	 * Small responsive override bag.
	 *
	 * @var array<string, bool>
	 */
	private array $responsive;

	/**
	 * Validated advanced Custom CSS.
	 *
	 * @var string
	 */
	private string $custom_css;

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
		$merged   = array_replace_recursive( $defaults, $raw );

		$placement = self::sanitize_enum(
			self::read_string( $raw, 'placement', self::PLACEMENT_MANUAL ),
			array( self::PLACEMENT_MANUAL, self::PLACEMENT_FLOATING_SIDE, self::PLACEMENT_STICKY_FOOTER ),
			self::PLACEMENT_MANUAL
		);

		$style = self::sanitize_enum(
			self::read_string( $raw, 'style', self::STYLE_DROPDOWN ),
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

		return new self(
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
			self::sanitize_content( $raw, $defaults['content'] ),
			self::sanitize_design( $raw, $defaults['design'] ),
			array(
				'remember_selection' => ! isset( $merged['behavior']['remember_selection'] ) || (bool) $merged['behavior']['remember_selection'],
				'active_first'       => ! isset( $merged['behavior']['active_first'] ) || (bool) $merged['behavior']['active_first'],
			),
			array(
				'desktop' => ! isset( $merged['visibility']['desktop'] ) || (bool) $merged['visibility']['desktop'],
				'mobile'  => ! isset( $merged['visibility']['mobile'] ) || (bool) $merged['visibility']['mobile'],
			),
			self::sanitize_responsive( $raw['responsive'] ?? null ),
			SwitcherCustomCss::sanitize( $raw['custom_css'] ?? '' ),
			$style_coerced
		);
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
				'trigger'      => array(
					'show_code'   => true,
					'show_symbol' => true,
					'show_name'   => false,
					'order'       => array( self::ELEMENT_CODE, self::ELEMENT_SYMBOL ),
				),
				'menu'         => array(
					'show_code'   => true,
					'show_symbol' => true,
					'show_name'   => false,
					'order'       => array( self::ELEMENT_CODE, self::ELEMENT_SYMBOL ),
				),
				'show_chevron' => false,
			),
			'design'     => array(
				'preset'    => self::PRESET_DEFAULT,
				'theme'     => self::THEME_AUTOMATIC,
				'size'      => self::SIZE_STANDARD,
				'shape'     => self::SHAPE_ROUNDED,
				'overrides' => array(),
				'motion'    => self::MOTION_SUBTLE,
			),
			'behavior'   => array(
				'remember_selection' => true,
				'active_first'       => true,
			),
			'visibility' => array(
				'desktop' => true,
				'mobile'  => true,
			),
			'responsive' => array(
				'hide_name_on_mobile' => false,
				'compact_on_mobile'   => false,
			),
			'custom_css' => '',
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

		if ( ! is_string( $value ) ) {
			return false;
		}

		$normalized = strtolower( trim( $value ) );

		if ( '' === $normalized ) {
			return false;
		}

		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Creates normalized settings from validated constructor arguments.
	 *
	 * @param bool                 $enabled       Whether enabled.
	 * @param string               $placement     Placement mode.
	 * @param string               $style         Presentation style.
	 * @param array<string, mixed> $position      Position settings.
	 * @param array<string, mixed> $content       Content composition.
	 * @param array<string, mixed> $design        Design tokens.
	 * @param array<string, bool>  $behavior      Behavior toggles.
	 * @param array<string, bool>  $visibility    Visibility toggles.
	 * @param array<string, bool>  $responsive    Responsive overrides.
	 * @param string               $custom_css    Validated Custom CSS.
	 * @param bool                 $style_coerced Whether style was coerced.
	 */
	private function __construct(
		bool $enabled,
		string $placement,
		string $style,
		array $position,
		array $content,
		array $design,
		array $behavior,
		array $visibility,
		array $responsive,
		string $custom_css,
		bool $style_coerced
	) {
		$this->enabled       = $enabled;
		$this->placement     = $placement;
		$this->style         = $style;
		$this->position      = $position;
		$this->content       = $content;
		$this->design        = $design;
		$this->behavior      = $behavior;
		$this->visibility    = $visibility;
		$this->responsive    = $responsive;
		$this->custom_css    = $custom_css;
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
	 * Full content composition (trigger, menu, chevron).
	 *
	 * @return array<string, mixed>
	 */
	public function content(): array {
		return $this->content;
	}

	/**
	 * Trigger label composition.
	 *
	 * @return array{show_code: bool, show_symbol: bool, show_name: bool, order: array<int, string>}
	 */
	public function trigger_content(): array {
		return $this->content['trigger'];
	}

	/**
	 * Menu label composition.
	 *
	 * @return array{show_code: bool, show_symbol: bool, show_name: bool, order: array<int, string>}
	 */
	public function menu_content(): array {
		return $this->content['menu'];
	}

	/**
	 * Whether the trigger renders a chevron affordance.
	 */
	public function show_chevron(): bool {
		return (bool) $this->content['show_chevron'];
	}

	/**
	 * Design tokens.
	 *
	 * @return array<string, mixed>
	 */
	public function design(): array {
		return $this->design;
	}

	/**
	 * Selected preset identifier.
	 */
	public function preset(): string {
		return (string) $this->design['preset'];
	}

	/**
	 * Motion level.
	 */
	public function motion(): string {
		return (string) $this->design['motion'];
	}

	/**
	 * Sparse structured overrides.
	 *
	 * @return array<string, int|string>
	 */
	public function overrides(): array {
		return $this->design['overrides'];
	}

	/**
	 * Theme, size, and shape tokens.
	 *
	 * Retained as a read alias for schema-5 call sites; the persisted shape is
	 * `design.theme|size|shape`.
	 *
	 * @return array<string, string>
	 */
	public function appearance(): array {
		return array(
			'theme' => (string) $this->design['theme'],
			'size'  => (string) $this->design['size'],
			'shape' => (string) $this->design['shape'],
		);
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
	 * Responsive override bag.
	 *
	 * @return array<string, bool>
	 */
	public function responsive(): array {
		return $this->responsive;
	}

	/**
	 * Validated advanced Custom CSS.
	 */
	public function custom_css(): string {
		return $this->custom_css;
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
			'design'     => $this->design,
			'behavior'   => $this->behavior,
			'visibility' => $this->visibility,
			'responsive' => $this->responsive,
			'custom_css' => $this->custom_css,
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

		$classes[] = 'umc-switcher--preset-' . $this->design['preset'];
		$classes[] = 'umc-switcher--theme-' . $this->design['theme'];
		$classes[] = 'umc-switcher--size-' . $this->design['size'];
		$classes[] = 'umc-switcher--shape-' . $this->design['shape'];

		if ( $this->responsive['hide_name_on_mobile'] ) {
			$classes[] = 'umc-switcher--hide-name-on-mobile';
		}

		if ( $this->responsive['compact_on_mobile'] ) {
			$classes[] = 'umc-switcher--compact-on-mobile';
		}

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
	 * Inline CSS custom properties for positioning, motion, and overrides.
	 *
	 * @return array<string, string>
	 */
	public function css_variables(): array {
		$variables = array(
			'--umc-edge-offset'                  => (string) $this->position['edge_offset'] . 'px',
			'--umc-vertical-offset'              => (string) $this->position['vertical_offset'] . 'px',
			'--umc-bottom-offset'                => (string) $this->position['bottom_offset'] . 'px',
			'--umc-switcher-z-index'             => '9990',
			'--umc-switcher-transition-duration' => self::MOTION_DURATIONS[ $this->design['motion'] ] ?? self::MOTION_DURATIONS[ self::MOTION_SUBTLE ],
		);

		foreach ( $this->design['overrides'] as $key => $value ) {
			$token = self::OVERRIDE_TOKENS[ $key ] ?? null;

			if ( null === $token ) {
				continue;
			}

			$variables[ $token['property'] ] = self::format_override( $token['type'], $value );
		}

		return $variables;
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
	 * Normalizes the content subtree, reading the legacy flat alias when needed.
	 *
	 * @param array<string, mixed> $raw      Raw display settings.
	 * @param array<string, mixed> $defaults Default content subtree.
	 * @return array<string, mixed>
	 */
	private static function sanitize_content( array $raw, array $defaults ): array {
		$content = is_array( $raw['content'] ?? null ) ? $raw['content'] : array();

		if ( array_key_exists( 'trigger', $content ) || array_key_exists( 'menu', $content ) ) {
			$trigger = self::sanitize_content_group( $content['trigger'] ?? null, $defaults['trigger'] );
			$menu    = self::sanitize_content_group( $content['menu'] ?? null, $defaults['menu'] );
		} else {
			$legacy  = self::sanitize_content_group( $content, $defaults['menu'] );
			$menu    = $legacy;
			$trigger = self::sanitize_content_group(
				array(
					'show_code'   => $legacy['show_code'],
					'show_symbol' => $legacy['show_symbol'],
					'show_name'   => false,
				),
				$defaults['trigger']
			);
		}

		return array(
			'trigger'      => $trigger,
			'menu'         => $menu,
			'show_chevron' => self::is_truthy( $content['show_chevron'] ?? false ),
		);
	}

	/**
	 * Normalizes one content group and repairs its element ordering.
	 *
	 * @param mixed                $raw      Raw group values.
	 * @param array<string, mixed> $defaults Default group values.
	 * @return array{show_code: bool, show_symbol: bool, show_name: bool, order: array<int, string>}
	 */
	private static function sanitize_content_group( mixed $raw, array $defaults ): array {
		$source = is_array( $raw ) ? $raw : array();

		$visible = array();

		foreach ( self::ELEMENT_SEQUENCE as $element ) {
			$key = 'show_' . $element;

			$enabled = array_key_exists( $key, $source )
				? self::is_truthy( $source[ $key ] )
				: (bool) ( $defaults[ $key ] ?? false );

			if ( $enabled ) {
				$visible[] = $element;
			}
		}

		if ( array() === $visible ) {
			$visible[] = self::ELEMENT_CODE;
		}

		return array(
			'show_code'   => in_array( self::ELEMENT_CODE, $visible, true ),
			'show_symbol' => in_array( self::ELEMENT_SYMBOL, $visible, true ),
			'show_name'   => in_array( self::ELEMENT_NAME, $visible, true ),
			'order'       => self::sanitize_order( $source['order'] ?? null, $visible ),
		);
	}

	/**
	 * Orders visible label elements, appending anything the merchant omitted.
	 *
	 * @param mixed              $raw     Raw merchant ordering.
	 * @param array<int, string> $visible Visible element ids.
	 * @return array<int, string>
	 */
	private static function sanitize_order( mixed $raw, array $visible ): array {
		$order = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $element ) {
				if ( ! is_string( $element ) ) {
					continue;
				}

				$element = strtolower( trim( $element ) );

				if ( ! in_array( $element, $visible, true ) || in_array( $element, $order, true ) ) {
					continue;
				}

				$order[] = $element;
			}
		}

		foreach ( self::ELEMENT_SEQUENCE as $element ) {
			if ( in_array( $element, $visible, true ) && ! in_array( $element, $order, true ) ) {
				$order[] = $element;
			}
		}

		return $order;
	}

	/**
	 * Normalizes the design subtree, reading the legacy appearance alias.
	 *
	 * @param array<string, mixed> $raw      Raw display settings.
	 * @param array<string, mixed> $defaults Default design subtree.
	 * @return array<string, mixed>
	 */
	private static function sanitize_design( array $raw, array $defaults ): array {
		$design     = is_array( $raw['design'] ?? null ) ? $raw['design'] : array();
		$appearance = is_array( $raw['appearance'] ?? null ) ? $raw['appearance'] : array();

		return array(
			'preset'    => self::sanitize_enum(
				self::read_string( $design, 'preset', (string) $defaults['preset'] ),
				self::PRESETS,
				self::PRESET_DEFAULT
			),
			'theme'     => self::sanitize_enum(
				self::read_aliased_string( $design, $appearance, 'theme', (string) $defaults['theme'] ),
				self::THEMES,
				self::THEME_AUTOMATIC
			),
			'size'      => self::sanitize_enum(
				self::read_aliased_string( $design, $appearance, 'size', (string) $defaults['size'] ),
				self::SIZES,
				self::SIZE_STANDARD
			),
			'shape'     => self::sanitize_enum(
				self::read_aliased_string( $design, $appearance, 'shape', (string) $defaults['shape'] ),
				self::SHAPES,
				self::SHAPE_ROUNDED
			),
			'overrides' => self::sanitize_overrides( $design['overrides'] ?? null ),
			'motion'    => self::sanitize_enum(
				self::read_string( $design, 'motion', (string) $defaults['motion'] ),
				self::MOTIONS,
				self::MOTION_SUBTLE
			),
		);
	}

	/**
	 * Filters structured overrides down to the allowlisted token map.
	 *
	 * @param mixed $raw Raw override map.
	 * @return array<string, int|string>
	 */
	private static function sanitize_overrides( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( self::OVERRIDE_TOKENS as $key => $token ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}

			$value = self::sanitize_override_value( $token['type'], $raw[ $key ] );

			if ( null === $value ) {
				continue;
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Validates one override value for its token type.
	 *
	 * @param string $type  Token value type.
	 * @param mixed  $value Raw value.
	 * @return int|string|null Normalized value, or null when unacceptable.
	 */
	private static function sanitize_override_value( string $type, mixed $value ): int|string|null {
		if ( 'color' === $type ) {
			return self::sanitize_color( $value );
		}

		if ( ! is_int( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', trim( $value ) ) ) ) {
			return null;
		}

		$number = (int) ( is_string( $value ) ? trim( $value ) : $value );

		if ( 'font_weight' === $type ) {
			return ( $number >= self::FONT_WEIGHT_MIN && $number <= self::FONT_WEIGHT_MAX ) ? $number : null;
		}

		return ( $number >= self::DIMENSION_MIN && $number <= self::DIMENSION_MAX ) ? $number : null;
	}

	/**
	 * Validates a hex or rgb()/rgba() color literal.
	 *
	 * @param mixed $value Raw color value.
	 */
	private static function sanitize_color( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$color = strtolower( trim( $value ) );

		if ( 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color ) ) {
			return $color;
		}

		if ( 1 === preg_match( '/^rgba?\(\s*[0-9%.\s,\/]+\)$/', $color ) ) {
			return $color;
		}

		return null;
	}

	/**
	 * Formats one override value as a CSS custom property value.
	 *
	 * @param string     $type  Token value type.
	 * @param int|string $value Normalized value.
	 */
	private static function format_override( string $type, int|string $value ): string {
		if ( 'dimension' === $type ) {
			return (string) $value . 'px';
		}

		return (string) $value;
	}

	/**
	 * Normalizes the responsive override bag.
	 *
	 * @param mixed $raw Raw responsive settings.
	 * @return array<string, bool>
	 */
	private static function sanitize_responsive( mixed $raw ): array {
		$source = is_array( $raw ) ? $raw : array();

		return array(
			'hide_name_on_mobile' => self::is_truthy( $source['hide_name_on_mobile'] ?? false ),
			'compact_on_mobile'   => self::is_truthy( $source['compact_on_mobile'] ?? false ),
		);
	}

	/**
	 * Reads a scalar string from a raw array with a fallback.
	 *
	 * @param array<string, mixed> $source   Raw source array.
	 * @param string               $key      Key to read.
	 * @param string               $fallback Fallback value.
	 */
	private static function read_string( array $source, string $key, string $fallback ): string {
		$value = $source[ $key ] ?? null;

		return is_string( $value ) ? $value : $fallback;
	}

	/**
	 * Reads a design field, falling back to the legacy appearance alias.
	 *
	 * @param array<string, mixed> $design     Raw design subtree.
	 * @param array<string, mixed> $appearance Raw legacy appearance subtree.
	 * @param string               $key        Field name.
	 * @param string               $fallback   Default value.
	 */
	private static function read_aliased_string( array $design, array $appearance, string $key, string $fallback ): string {
		if ( is_string( $design[ $key ] ?? null ) ) {
			return $design[ $key ];
		}

		return self::read_string( $appearance, $key, $fallback );
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
