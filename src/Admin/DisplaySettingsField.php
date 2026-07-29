<?php
/**
 * Display switcher settings field for the Multicurrency admin tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherViewModelFactory;
use UMC\Settings;

/**
 * Renders Display configuration cards with a live preview shell.
 */
final class DisplaySettingsField {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Preview view-model factory.
	 *
	 * @var SwitcherViewModelFactory
	 */
	private SwitcherViewModelFactory $factory;

	/**
	 * Shared switcher renderer.
	 *
	 * @var SwitcherRenderer
	 */
	private SwitcherRenderer $renderer;

	/**
	 * Display settings repository.
	 *
	 * @var SwitcherSettingsRepository
	 */
	private SwitcherSettingsRepository $settings_repository;

	/**
	 * Whether style was coerced during the last {@see parse_post()} call.
	 *
	 * @var bool
	 */
	private bool $show_coercion_notice = false;

	/**
	 * Binds the Display settings field to settings and preview services.
	 *
	 * @param Settings                   $settings            Merchant settings store.
	 * @param SwitcherViewModelFactory   $factory             Preview view-model factory.
	 * @param SwitcherRenderer           $renderer            Shared switcher renderer.
	 * @param SwitcherSettingsRepository $settings_repository Display settings repository.
	 */
	public function __construct(
		Settings $settings,
		SwitcherViewModelFactory $factory,
		SwitcherRenderer $renderer,
		SwitcherSettingsRepository $settings_repository
	) {
		$this->settings            = $settings;
		$this->factory             = $factory;
		$this->renderer            = $renderer;
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Whether the last parse attempt coerced horizontal list to dropdown.
	 */
	public function show_coercion_notice(): bool {
		return $this->show_coercion_notice;
	}

	/**
	 * Renders the Display settings workspace.
	 */
	public function render(): void {
		$raw      = $this->settings->get()['display'] ?? array();
		$settings = SwitcherSettings::from_array( is_array( $raw ) ? $raw : array() );
		$model    = $this->factory->create_for_admin_preview( $settings );
		$preview  = $this->renderer->render( $model );

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-display-settings" colspan="2">
				<div class="umc-display-layout">
					<div class="umc-display-main">
						<?php $this->render_notices( $settings ); ?>
						<?php $this->render_switcher_card( $settings ); ?>
						<?php $this->render_position_card( $settings ); ?>
						<?php $this->render_content_card( $settings ); ?>
						<?php $this->render_appearance_card( $settings ); ?>
						<?php $this->render_behavior_card( $settings ); ?>
						<?php $this->render_visibility_card( $settings ); ?>
					</div>
					<div class="umc-display-preview">
						<div class="umc-display-preview__header">
							<h3 class="umc-display-preview__title"><?php esc_html_e( 'Preview', 'universal-multicurrency' ); ?></h3>
							<div class="umc-display-preview__viewport" role="group" aria-label="<?php esc_attr_e( 'Preview viewport', 'universal-multicurrency' ); ?>">
								<button type="button" class="button umc-display-preview__viewport-btn is-active" data-umc-preview-viewport="desktop"><?php esc_html_e( 'Desktop', 'universal-multicurrency' ); ?></button>
								<button type="button" class="button umc-display-preview__viewport-btn" data-umc-preview-viewport="mobile"><?php esc_html_e( 'Mobile', 'universal-multicurrency' ); ?></button>
							</div>
						</div>
						<div class="umc-display-preview-frame" data-umc-preview-frame>
							<div class="umc-display-preview-frame__canvas<?php echo esc_attr( $this->preview_canvas_class( $settings ) ); ?>" data-umc-preview-canvas>
								<?php $this->render_preview_mock_screen(); ?>
								<div class="umc-display-preview-switcher" data-umc-preview-switcher-host>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer returns fully escaped HTML.
									echo $preview;
									?>
								</div>
							</div>
						</div>
						<p class="description umc-display-preview__hint"><?php esc_html_e( 'Preview simulates a storefront viewport. Currency links do not change the customer selection.', 'universal-multicurrency' ); ?></p>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses Display settings from the current POST payload.
	 *
	 * @return array{display: array<string, mixed>, show_coercion_notice: bool}|null Null when visibility is invalid.
	 */
	public function parse_post(): ?array {
		$this->show_coercion_notice = false;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values normalized and sanitized via SwitcherSettings::from_array.
		$raw = isset( $_POST['umc_display'] ) ? wp_unslash( $_POST['umc_display'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$raw = $this->normalize_post_raw( $raw );

		if ( ! SwitcherSettings::visibility_valid_for_save( $raw ) ) {
			return null;
		}

		$settings = SwitcherSettings::from_array( $raw );

		$this->show_coercion_notice = $settings->was_style_coerced();

		return array(
			'display'              => SwitcherSettings::sanitize_raw( $raw ),
			'show_coercion_notice' => $this->show_coercion_notice,
		);
	}

	/**
	 * Renders contextual notices for automatic placement configuration.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_notices( SwitcherSettings $settings ): void {
		if ( ! $settings->should_render_automatic() ) {
			return;
		}

		if ( $this->detect_duplicate_shortcode_warning() ) {
			$this->notice(
				'warning',
				__( 'Automatic placement is enabled and a currency switcher shortcode was detected on an important page or menu link. Both may appear on the storefront.', 'universal-multicurrency' )
			);
			return;
		}

		$this->notice(
			'info',
			__( 'Automatic placement injects the switcher on every storefront page. Use manual placement with a shortcode when you need precise control.', 'universal-multicurrency' )
		);
	}

	/**
	 * Prints one inline admin notice for the Display workspace.
	 *
	 * @param string $type    Notice type: info, warning.
	 * @param string $message Notice message.
	 */
	private function notice( string $type, string $message ): void {
		printf(
			'<div class="notice notice-%1$s inline umc-display-notice"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Renders the Switcher enablement, placement, and style card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_switcher_card( SwitcherSettings $settings ): void {
		$placement      = $settings->placement();
		$style          = $settings->style();
		$auto_placement = SwitcherSettings::PLACEMENT_MANUAL !== $placement;

		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Switcher', 'universal-multicurrency' ); ?></h3>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[enabled]" value="0" />
				<input type="checkbox" name="umc_display[enabled]" value="1" <?php checked( $settings->is_enabled() ); ?> data-umc-display-field="enabled" />
				<span><?php esc_html_e( 'Enable storefront switcher', 'universal-multicurrency' ); ?></span>
			</label>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Placement', 'universal-multicurrency' ); ?></legend>
				<div class="umc-display-radio-cards">
					<?php
					foreach (
						array(
							SwitcherSettings::PLACEMENT_MANUAL        => __( 'Manual placement', 'universal-multicurrency' ),
							SwitcherSettings::PLACEMENT_FLOATING_SIDE => __( 'Floating side', 'universal-multicurrency' ),
							SwitcherSettings::PLACEMENT_STICKY_FOOTER => __( 'Floating bottom', 'universal-multicurrency' ),
						) as $value => $label
					) {
						$this->radio_card(
							'umc_display[placement]',
							$value,
							$label,
							$placement === $value,
							array( 'data-umc-display-field' => 'placement' )
						);
					}
					?>
				</div>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Style', 'universal-multicurrency' ); ?></legend>
				<div class="umc-display-radio-cards">
					<?php
					$this->radio_card(
						'umc_display[style]',
						SwitcherSettings::STYLE_DROPDOWN,
						__( 'Dropdown', 'universal-multicurrency' ),
						SwitcherSettings::STYLE_DROPDOWN === $style,
						array( 'data-umc-display-field' => 'style' )
					);
					$this->radio_card(
						'umc_display[style]',
						SwitcherSettings::STYLE_HORIZONTAL_LIST,
						__( 'Horizontal list', 'universal-multicurrency' ),
						SwitcherSettings::STYLE_HORIZONTAL_LIST === $style,
						array(
							'data-umc-display-field' => 'style',
							'disabled'               => $auto_placement ? 'disabled' : null,
						)
					);
					?>
				</div>
				<p class="description"><?php esc_html_e( 'Horizontal list is available with manual placement only. Automatic placements always use the dropdown style.', 'universal-multicurrency' ); ?></p>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * Renders the Position card for automatic placements.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_position_card( SwitcherSettings $settings ): void {
		$placement = $settings->placement();
		$position  = $settings->position();
		$hidden    = SwitcherSettings::PLACEMENT_MANUAL === $placement ? ' umc-display-card--hidden' : '';

		?>
		<div class="umc-display-card umc-display-card--position<?php echo esc_attr( $hidden ); ?>" data-umc-position-card>
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Position', 'universal-multicurrency' ); ?></h3>
			<?php if ( SwitcherSettings::PLACEMENT_FLOATING_SIDE === $placement ) : ?>
				<?php $this->render_side_field( $position ); ?>
				<label class="umc-display-field">
					<span><?php esc_html_e( 'Vertical position', 'universal-multicurrency' ); ?></span>
					<select name="umc_display[position][vertical_alignment]" data-umc-display-field="vertical_alignment">
						<?php foreach ( array( SwitcherSettings::ALIGN_TOP, SwitcherSettings::ALIGN_MIDDLE, SwitcherSettings::ALIGN_BOTTOM ) as $align ) : ?>
							<option value="<?php echo esc_attr( $align ); ?>" <?php selected( $position['vertical_alignment'], $align ); ?>><?php echo esc_html( ucfirst( $align ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="umc-display-field">
					<span><?php esc_html_e( 'Vertical offset (px)', 'universal-multicurrency' ); ?></span>
					<input type="number" name="umc_display[position][vertical_offset]" value="<?php echo esc_attr( (string) $position['vertical_offset'] ); ?>" data-umc-display-field="vertical_offset" />
				</label>
				<label class="umc-display-field">
					<span><?php esc_html_e( 'Edge offset (px)', 'universal-multicurrency' ); ?></span>
					<input type="number" min="0" max="200" name="umc_display[position][edge_offset]" value="<?php echo esc_attr( (string) $position['edge_offset'] ); ?>" data-umc-display-field="edge_offset" />
				</label>
			<?php else : ?>
				<?php $this->render_side_field( $position ); ?>
				<label class="umc-display-field">
					<span><?php esc_html_e( 'Edge offset (px)', 'universal-multicurrency' ); ?></span>
					<input type="number" min="0" max="200" name="umc_display[position][edge_offset]" value="<?php echo esc_attr( (string) $position['edge_offset'] ); ?>" data-umc-display-field="edge_offset" />
				</label>
				<label class="umc-display-field">
					<span><?php esc_html_e( 'Bottom offset (px)', 'universal-multicurrency' ); ?></span>
					<input type="number" min="0" max="500" name="umc_display[position][bottom_offset]" value="<?php echo esc_attr( (string) $position['bottom_offset'] ); ?>" data-umc-display-field="bottom_offset" />
				</label>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the side selector shared by automatic placements.
	 *
	 * @param array<string, int|string> $position Position settings.
	 */
	private function render_side_field( array $position ): void {
		?>
		<label class="umc-display-field">
			<span><?php esc_html_e( 'Side', 'universal-multicurrency' ); ?></span>
			<select name="umc_display[position][side]" data-umc-display-field="side">
				<option value="<?php echo esc_attr( SwitcherSettings::SIDE_LEFT ); ?>" <?php selected( $position['side'], SwitcherSettings::SIDE_LEFT ); ?>><?php esc_html_e( 'Left', 'universal-multicurrency' ); ?></option>
				<option value="<?php echo esc_attr( SwitcherSettings::SIDE_RIGHT ); ?>" <?php selected( $position['side'], SwitcherSettings::SIDE_RIGHT ); ?>><?php esc_html_e( 'Right', 'universal-multicurrency' ); ?></option>
			</select>
		</label>
		<?php
	}

	/**
	 * Renders the Content visibility and ordering card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_content_card( SwitcherSettings $settings ): void {
		$content = $settings->content();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Content', 'universal-multicurrency' ); ?></h3>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[content][show_code]" value="0" />
				<input type="checkbox" name="umc_display[content][show_code]" value="1" <?php checked( $content['show_code'] ); ?> data-umc-display-field="content_show_code" />
				<span><?php esc_html_e( 'Show currency code', 'universal-multicurrency' ); ?></span>
			</label>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[content][show_symbol]" value="0" />
				<input type="checkbox" name="umc_display[content][show_symbol]" value="1" <?php checked( $content['show_symbol'] ); ?> data-umc-display-field="content_show_symbol" />
				<span><?php esc_html_e( 'Show currency symbol', 'universal-multicurrency' ); ?></span>
			</label>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[content][show_name]" value="0" />
				<input type="checkbox" name="umc_display[content][show_name]" value="1" <?php checked( $content['show_name'] ); ?> data-umc-display-field="content_show_name" />
				<span><?php esc_html_e( 'Show currency name', 'universal-multicurrency' ); ?></span>
			</label>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[behavior][active_first]" value="0" />
				<input type="checkbox" name="umc_display[behavior][active_first]" value="1" <?php checked( $settings->active_first() ); ?> data-umc-display-field="active_first" />
				<span><?php esc_html_e( 'Show selected currency first', 'universal-multicurrency' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Renders the Appearance theme, size, and shape card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_appearance_card( SwitcherSettings $settings ): void {
		$appearance = $settings->appearance();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Appearance', 'universal-multicurrency' ); ?></h3>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Theme', 'universal-multicurrency' ); ?></span>
				<select name="umc_display[appearance][theme]" data-umc-display-field="theme">
					<?php foreach ( array( SwitcherSettings::THEME_AUTOMATIC, SwitcherSettings::THEME_LIGHT, SwitcherSettings::THEME_DARK ) as $theme ) : ?>
						<option value="<?php echo esc_attr( $theme ); ?>" <?php selected( $appearance['theme'], $theme ); ?>><?php echo esc_html( ucfirst( $theme ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Size', 'universal-multicurrency' ); ?></span>
				<select name="umc_display[appearance][size]" data-umc-display-field="size">
					<?php foreach ( array( SwitcherSettings::SIZE_COMPACT, SwitcherSettings::SIZE_STANDARD, SwitcherSettings::SIZE_LARGE ) as $size ) : ?>
						<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $appearance['size'], $size ); ?>><?php echo esc_html( ucfirst( $size ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Shape', 'universal-multicurrency' ); ?></span>
				<select name="umc_display[appearance][shape]" data-umc-display-field="shape">
					<option value="<?php echo esc_attr( SwitcherSettings::SHAPE_SLIGHT ); ?>" <?php selected( $appearance['shape'], SwitcherSettings::SHAPE_SLIGHT ); ?>><?php esc_html_e( 'Slightly rounded', 'universal-multicurrency' ); ?></option>
					<option value="<?php echo esc_attr( SwitcherSettings::SHAPE_ROUNDED ); ?>" <?php selected( $appearance['shape'], SwitcherSettings::SHAPE_ROUNDED ); ?>><?php esc_html_e( 'Rounded', 'universal-multicurrency' ); ?></option>
					<option value="<?php echo esc_attr( SwitcherSettings::SHAPE_PILL ); ?>" <?php selected( $appearance['shape'], SwitcherSettings::SHAPE_PILL ); ?>><?php esc_html_e( 'Pill', 'universal-multicurrency' ); ?></option>
				</select>
			</label>
		</div>
		<?php
	}

	/**
	 * Renders the Behavior card for selection persistence.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_behavior_card( SwitcherSettings $settings ): void {
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Behavior', 'universal-multicurrency' ); ?></h3>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[behavior][remember_selection]" value="0" />
				<input type="checkbox" name="umc_display[behavior][remember_selection]" value="1" <?php checked( $settings->remember_selection() ); ?> data-umc-display-field="remember_selection" />
				<span><?php esc_html_e( 'Remember selected currency between visits', 'universal-multicurrency' ); ?></span>
			</label>
			<p class="description"><?php esc_html_e( 'When disabled, the selection applies to the current browsing session only.', 'universal-multicurrency' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the Device visibility card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_visibility_card( SwitcherSettings $settings ): void {
		$visibility = $settings->visibility();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Device visibility', 'universal-multicurrency' ); ?></h3>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[visibility][desktop]" value="0" />
				<input type="checkbox" name="umc_display[visibility][desktop]" value="1" <?php checked( $visibility['desktop'] ); ?> data-umc-display-field="visibility_desktop" />
				<span><?php esc_html_e( 'Show on desktop', 'universal-multicurrency' ); ?></span>
			</label>
			<label class="umc-display-toggle">
				<input type="hidden" name="umc_display[visibility][mobile]" value="0" />
				<input type="checkbox" name="umc_display[visibility][mobile]" value="1" <?php checked( $visibility['mobile'] ); ?> data-umc-display-field="visibility_mobile" />
				<span><?php esc_html_e( 'Show on mobile', 'universal-multicurrency' ); ?></span>
			</label>
			<p class="description"><?php esc_html_e( 'When the switcher is enabled, at least one device option must remain selected.', 'universal-multicurrency' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Returns the preview canvas layout class for one placement mode.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function preview_canvas_class( SwitcherSettings $settings ): string {
		return match ( $settings->placement() ) {
			SwitcherSettings::PLACEMENT_FLOATING_SIDE => ' umc-display-preview-frame__canvas--floating-side',
			SwitcherSettings::PLACEMENT_STICKY_FOOTER => ' umc-display-preview-frame__canvas--floating-bottom',
			default => ' umc-display-preview-frame__canvas--manual',
		};
	}

	/**
	 * Renders decorative page chrome inside the preview viewport.
	 */
	private function render_preview_mock_screen(): void {
		?>
		<div class="umc-display-preview-mock" aria-hidden="true">
			<div class="umc-display-preview-mock__chrome">
				<span class="umc-display-preview-mock__dot"></span>
				<span class="umc-display-preview-mock__dot"></span>
				<span class="umc-display-preview-mock__dot"></span>
				<span class="umc-display-preview-mock__url"><?php esc_html_e( 'yourstore.example/shop', 'universal-multicurrency' ); ?></span>
			</div>
			<div class="umc-display-preview-mock__page">
				<div class="umc-display-preview-mock__header">
					<span class="umc-display-preview-mock__logo"></span>
					<span class="umc-display-preview-mock__nav"></span>
				</div>
				<div class="umc-display-preview-mock__hero"></div>
				<div class="umc-display-preview-mock__grid">
					<span class="umc-display-preview-mock__tile"></span>
					<span class="umc-display-preview-mock__tile"></span>
					<span class="umc-display-preview-mock__tile"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders one selectable radio card control.
	 *
	 * @param string                $name    Input name.
	 * @param string                $value   Input value.
	 * @param string                $label   Visible label.
	 * @param bool                  $checked Whether selected.
	 * @param array<string, string> $attrs   Extra attributes.
	 */
	private function radio_card( string $name, string $value, string $label, bool $checked, array $attrs = array() ): void {
		$attr_html = '';

		foreach ( $attrs as $key => $attr_value ) {
			if ( null === $attr_value ) {
				continue;
			}

			$attr_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $attr_value ) );
		}

		printf(
			'<label class="umc-display-radio-card"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s /><span>%5$s</span></label>',
			esc_attr( $name ),
			esc_attr( $value ),
			checked( $checked, true, false ),
			$attr_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped attributes.
			esc_html( $label )
		);
	}

	/**
	 * Normalizes checkbox-style POST values before settings parsing.
	 *
	 * @param array<string, mixed> $raw Raw POST display payload.
	 * @return array<string, mixed>
	 */
	private function normalize_post_raw( array $raw ): array {
		$raw['enabled'] = ! empty( $raw['enabled'] );

		foreach ( array( 'content', 'behavior', 'visibility' ) as $group ) {
			if ( ! isset( $raw[ $group ] ) || ! is_array( $raw[ $group ] ) ) {
				continue;
			}

			foreach ( $raw[ $group ] as $key => $value ) {
				$raw[ $group ][ $key ] = ! empty( $value );
			}
		}

		return $raw;
	}

	/**
	 * Best-effort scan for switcher shortcodes on key storefront surfaces.
	 */
	private function detect_duplicate_shortcode_warning(): bool {
		$post_ids = array();

		$front_page = (int) get_option( 'page_on_front' );
		if ( $front_page > 0 ) {
			$post_ids[] = $front_page;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_page = (int) wc_get_page_id( 'shop' );
			if ( $shop_page > 0 ) {
				$post_ids[] = $shop_page;
			}
		}

		$menus = wp_get_nav_menus();

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );

			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				if ( isset( $item->object_id ) && 'post_type' === $item->type ) {
					$post_ids[] = (int) $item->object_id;
				}
			}
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if (
				has_shortcode( $post->post_content, SwitcherShortcode::TAG_PRIMARY )
				|| has_shortcode( $post->post_content, SwitcherShortcode::TAG_LEGACY )
			) {
				return true;
			}
		}

		return false;
	}
}
