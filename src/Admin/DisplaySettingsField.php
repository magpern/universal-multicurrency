<?php
/**
 * Display switcher settings field for the Multicurrency admin tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Display\SwitcherCustomCss;
use UMC\Display\SwitcherRenderer;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcode;
use UMC\Display\SwitcherShortcodeScanner;
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
	 * Presentation markup helper.
	 *
	 * @var DisplayControlRenderer
	 */
	private DisplayControlRenderer $controls;

	/**
	 * Whether style was coerced during the last {@see parse_post()} call.
	 *
	 * @var bool
	 */
	private bool $show_coercion_notice = false;

	/**
	 * Binds the Display settings field to settings and preview services.
	 *
	 * @param Settings                    $settings            Merchant settings store.
	 * @param SwitcherViewModelFactory    $factory             Preview view-model factory.
	 * @param SwitcherRenderer            $renderer            Shared switcher renderer.
	 * @param SwitcherSettingsRepository  $settings_repository Display settings repository.
	 * @param DisplayControlRenderer|null $controls           Optional presentation helper.
	 */
	public function __construct(
		Settings $settings,
		SwitcherViewModelFactory $factory,
		SwitcherRenderer $renderer,
		SwitcherSettingsRepository $settings_repository,
		?DisplayControlRenderer $controls = null
	) {
		$this->settings            = $settings;
		$this->factory             = $factory;
		$this->renderer            = $renderer;
		$this->settings_repository = $settings_repository;
		$this->controls            = $controls ?? new DisplayControlRenderer();
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
		$raw       = $this->settings->get()['display'] ?? array();
		$settings  = SwitcherSettings::from_array( is_array( $raw ) ? $raw : array() );
		$model     = $this->factory->create_for_admin_preview( $settings );
		$preview   = $this->renderer->render( $model );
		$placement = $settings->placement();

		?>
		<tr valign="top">
			<td class="forminp umc-settings umc-display-settings" colspan="2">
				<div class="umc-display-layout">
					<div class="umc-display-configurator">
						<?php $this->render_enable_row( $settings ); ?>
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
							<h3 class="umc-display-preview__title"><?php esc_html_e( 'Live preview', 'universal-multicurrency' ); ?></h3>
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
								<div class="umc-display-preview-disabled" data-umc-preview-disabled-overlay<?php echo $settings->is_enabled() ? ' hidden' : ''; ?>>
									<p><?php esc_html_e( 'Switcher is currently disabled', 'universal-multicurrency' ); ?></p>
								</div>
							</div>
						</div>
						<p class="description umc-display-preview__hint"><?php esc_html_e( 'Preview simulates a storefront viewport. Currency links do not change the customer selection.', 'universal-multicurrency' ); ?></p>
					</div>
				</div>
				<input type="hidden" data-umc-display-placement="<?php echo esc_attr( $placement ); ?>" />
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values normalized and sanitized via SwitcherSettings.
		$raw = isset( $_POST['umc_display'] ) ? wp_unslash( $_POST['umc_display'] ) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$stored = $this->stored_display_raw();
		$raw    = $this->normalize_post_raw( $raw );

		$active_placement = isset( $raw['placement'] ) && is_string( $raw['placement'] )
			? $raw['placement']
			: (string) ( $stored['placement'] ?? SwitcherSettings::PLACEMENT_MANUAL );

		$merged               = array_replace_recursive( $stored, $raw );
		$merged['position']   = $this->merge_position_preserving_inactive(
			is_array( $stored['position'] ?? null ) ? $stored['position'] : SwitcherSettings::default_array()['position'],
			is_array( $raw['position'] ?? null ) ? $raw['position'] : array(),
			$active_placement
		);
		$merged['placement']  = $active_placement;
		$merged['content']    = $this->merge_content(
			is_array( $stored['content'] ?? null ) ? $stored['content'] : array(),
			is_array( $raw['content'] ?? null ) ? $raw['content'] : array()
		);
		$merged['custom_css'] = SwitcherCustomCss::resolve_for_save(
			$raw['custom_css'] ?? null,
			$stored['custom_css'] ?? '',
			SwitcherCustomCss::can_edit()
		);

		if ( ! SwitcherSettings::visibility_valid_for_save( $merged ) ) {
			return null;
		}

		$settings = SwitcherSettings::from_array( $merged );

		$this->show_coercion_notice = $settings->was_style_coerced();

		return array(
			'display'              => SwitcherSettings::sanitize_raw( $merged ),
			'show_coercion_notice' => $this->show_coercion_notice,
		);
	}

	/**
	 * Outputs presentation markup from {@see DisplayControlRenderer}.
	 *
	 * @param string $markup Escaped HTML fragment.
	 */
	private function echo_control_markup( string $markup ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in DisplayControlRenderer.
		echo $markup;
	}

	/**
	 * Renders the prominent enable row with status badge.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_enable_row( SwitcherSettings $settings ): void {
		$enabled = $settings->is_enabled();
		?>
		<div class="umc-display-enable-row">
			<div class="umc-display-enable-row__control">
				<?php
				$this->echo_control_markup(
					$this->controls->toggle_row(
						'umc_display[enabled]',
						$enabled,
						__( 'Enable storefront switcher', 'universal-multicurrency' ),
						'',
						array( 'data-umc-display-field' => 'enabled' )
					)
				);
				?>
			</div>
			<span class="umc-display-enable-row__status<?php echo esc_attr( $enabled ? ' is-on' : ' is-off' ); ?>" data-umc-display-status>
				<?php echo esc_html( $enabled ? __( 'On', 'universal-multicurrency' ) : __( 'Off', 'universal-multicurrency' ) ); ?>
			</span>
		</div>
		<?php
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
			$this->echo_control_markup(
				$this->controls->callout(
					'warning',
					__( 'Automatic placement is enabled and a currency switcher shortcode was detected on an important page or menu link. Both may appear on the storefront.', 'universal-multicurrency' )
				)
			);
			return;
		}

		$this->echo_control_markup(
			$this->controls->callout(
				'info',
				__( 'Automatic placement injects the switcher on every storefront page. Use manual placement with a shortcode when you need precise control.', 'universal-multicurrency' )
			)
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
		$manual_hidden  = SwitcherSettings::PLACEMENT_MANUAL !== $placement ? ' umc-display-panel--hidden' : '';

		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Switcher', 'universal-multicurrency' ); ?></h3>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Placement', 'universal-multicurrency' ); ?></legend>
				<div class="umc-display-choice-cards" data-umc-placement-cards>
					<?php
					$placements = array(
						SwitcherSettings::PLACEMENT_MANUAL => array(
							'label'       => __( 'Manual placement', 'universal-multicurrency' ),
							'description' => __( 'Place the switcher with a shortcode.', 'universal-multicurrency' ),
							'diagram'     => $this->controls->diagram_placement_manual(),
						),
						SwitcherSettings::PLACEMENT_FLOATING_SIDE => array(
							'label'       => __( 'Floating side', 'universal-multicurrency' ),
							'description' => __( 'Fixed to the left or right edge.', 'universal-multicurrency' ),
							'diagram'     => $this->controls->diagram_placement_floating_side(),
						),
						SwitcherSettings::PLACEMENT_STICKY_FOOTER => array(
							'label'       => __( 'Floating bottom', 'universal-multicurrency' ),
							'description' => __( 'Fixed above the bottom edge.', 'universal-multicurrency' ),
							'diagram'     => $this->controls->diagram_placement_floating_bottom(),
						),
					);

					foreach ( $placements as $value => $meta ) {
						$this->echo_control_markup(
							$this->controls->choice_card(
								'umc_display[placement]',
								$value,
								$placement === $value,
								$meta['label'],
								$meta['description'],
								$meta['diagram'],
								array( 'data-umc-display-field' => 'placement' )
							)
						);
					}
					?>
				</div>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Style', 'universal-multicurrency' ); ?></legend>
				<div class="umc-display-choice-cards" data-umc-style-cards>
					<?php
					$this->echo_control_markup(
						$this->controls->choice_card(
							'umc_display[style]',
							SwitcherSettings::STYLE_DROPDOWN,
							SwitcherSettings::STYLE_DROPDOWN === $style,
							__( 'Dropdown', 'universal-multicurrency' ),
							__( 'Compact trigger with a menu.', 'universal-multicurrency' ),
							$this->controls->diagram_style_dropdown(),
							array( 'data-umc-display-field' => 'style' )
						)
					);

					$horizontal_attrs = array( 'data-umc-display-field' => 'style' );
					if ( $auto_placement ) {
						$horizontal_attrs['disabled'] = 'disabled';
					}

					$this->echo_control_markup(
						$this->controls->choice_card(
							'umc_display[style]',
							SwitcherSettings::STYLE_HORIZONTAL_LIST,
							SwitcherSettings::STYLE_HORIZONTAL_LIST === $style,
							__( 'Horizontal list', 'universal-multicurrency' ),
							__( 'Inline currency links.', 'universal-multicurrency' ),
							$this->controls->diagram_style_horizontal_list(),
							$horizontal_attrs
						)
					);
					?>
				</div>
				<p class="description umc-display-style-note" data-umc-style-note><?php esc_html_e( 'Horizontal list is available with manual placement only. Automatic placements always use the dropdown style.', 'universal-multicurrency' ); ?></p>
			</fieldset>
			<div class="umc-display-shortcode-panel<?php echo esc_attr( $manual_hidden ); ?>" data-umc-manual-panel>
				<h4 class="umc-display-shortcode-panel__title"><?php esc_html_e( 'Manual shortcode', 'universal-multicurrency' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Add this shortcode to a page, post, or widget area.', 'universal-multicurrency' ); ?></p>
				<div class="umc-display-shortcode-panel__row">
					<code class="umc-display-shortcode-panel__code" data-umc-shortcode-text>[<?php echo esc_html( SwitcherShortcode::TAG_PRIMARY ); ?>]</code>
					<button type="button" class="button umc-display-shortcode-panel__copy" data-umc-copy-shortcode aria-label="<?php esc_attr_e( 'Copy shortcode', 'universal-multicurrency' ); ?>">
						<?php esc_html_e( 'Copy', 'universal-multicurrency' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Position card with dual placement panels.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_position_card( SwitcherSettings $settings ): void {
		$placement     = $settings->placement();
		$position      = $settings->position();
		$hidden        = SwitcherSettings::PLACEMENT_MANUAL === $placement ? ' umc-display-card--hidden' : '';
		$side_active   = SwitcherSettings::PLACEMENT_FLOATING_SIDE === $placement;
		$bottom_active = SwitcherSettings::PLACEMENT_STICKY_FOOTER === $placement;

		?>
		<div class="umc-display-card umc-display-card--position<?php echo esc_attr( $hidden ); ?>" data-umc-position-card>
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Position', 'universal-multicurrency' ); ?></h3>
			<div class="umc-display-position-panels">
				<div class="umc-display-position-panel<?php echo $side_active ? '' : ' umc-display-panel--hidden'; ?>" data-umc-position-panel="floating_side">
					<?php $this->render_side_field( $position, ! $side_active ); ?>
					<fieldset class="umc-display-fieldset">
						<legend><?php esc_html_e( 'Vertical position', 'universal-multicurrency' ); ?></legend>
						<?php
						$this->echo_control_markup(
							$this->controls->segmented_control(
								'umc_display[position][vertical_alignment]',
								array(
									SwitcherSettings::ALIGN_TOP    => __( 'Top', 'universal-multicurrency' ),
									SwitcherSettings::ALIGN_MIDDLE => __( 'Middle', 'universal-multicurrency' ),
									SwitcherSettings::ALIGN_BOTTOM => __( 'Bottom', 'universal-multicurrency' ),
								),
								(string) $position['vertical_alignment'],
								array( 'data-umc-display-field' => 'vertical_alignment' ),
								! $side_active ? array(
									SwitcherSettings::ALIGN_TOP    => array( 'disabled' => 'disabled' ),
									SwitcherSettings::ALIGN_MIDDLE => array( 'disabled' => 'disabled' ),
									SwitcherSettings::ALIGN_BOTTOM => array( 'disabled' => 'disabled' ),
								) : array()
							)
						);
						?>
					</fieldset>
					<label class="umc-display-field">
						<span><?php esc_html_e( 'Vertical offset (px)', 'universal-multicurrency' ); ?></span>
						<input type="number" name="umc_display[position][vertical_offset]" value="<?php echo esc_attr( (string) $position['vertical_offset'] ); ?>" data-umc-display-field="vertical_offset"<?php disabled( ! $side_active ); ?> />
					</label>
					<label class="umc-display-field">
						<span><?php esc_html_e( 'Edge offset (px)', 'universal-multicurrency' ); ?></span>
						<input type="number" min="0" max="200" name="umc_display[position][edge_offset]" value="<?php echo esc_attr( (string) $position['edge_offset'] ); ?>" data-umc-display-field="edge_offset"<?php disabled( ! $side_active ); ?> />
					</label>
				</div>
				<div class="umc-display-position-panel<?php echo $bottom_active ? '' : ' umc-display-panel--hidden'; ?>" data-umc-position-panel="sticky_footer">
					<?php $this->render_side_field( $position, ! $bottom_active ); ?>
					<label class="umc-display-field">
						<span><?php esc_html_e( 'Edge offset (px)', 'universal-multicurrency' ); ?></span>
						<input type="number" min="0" max="200" name="umc_display[position][edge_offset]" value="<?php echo esc_attr( (string) $position['edge_offset'] ); ?>" data-umc-display-field="edge_offset"<?php disabled( ! $bottom_active ); ?> />
					</label>
					<label class="umc-display-field">
						<span><?php esc_html_e( 'Bottom offset (px)', 'universal-multicurrency' ); ?></span>
						<input type="number" min="0" max="500" name="umc_display[position][bottom_offset]" value="<?php echo esc_attr( (string) $position['bottom_offset'] ); ?>" data-umc-display-field="bottom_offset"<?php disabled( ! $bottom_active ); ?> />
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the side selector inside one position panel.
	 *
	 * @param array<string, int|string> $position Position settings.
	 * @param bool                      $disabled Whether the panel is inactive.
	 */
	private function render_side_field( array $position, bool $disabled ): void {
		$options = array(
			SwitcherSettings::SIDE_LEFT  => __( 'Left', 'universal-multicurrency' ),
			SwitcherSettings::SIDE_RIGHT => __( 'Right', 'universal-multicurrency' ),
		);
		?>
		<fieldset class="umc-display-fieldset">
			<legend><?php esc_html_e( 'Side', 'universal-multicurrency' ); ?></legend>
			<?php
			$this->echo_control_markup(
				$this->controls->segmented_control(
					'umc_display[position][side]',
					$options,
					(string) $position['side'],
					array( 'data-umc-display-field' => 'side' ),
					$disabled ? array_fill_keys( array_keys( $options ), array( 'disabled' => 'disabled' ) ) : array()
				)
			);
			?>
		</fieldset>
		<?php
	}

	/**
	 * Renders the Content visibility and ordering card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_content_card( SwitcherSettings $settings ): void {
		$content = $settings->menu_content();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Content', 'universal-multicurrency' ); ?></h3>
			<?php
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[content][show_code]', $content['show_code'], __( 'Show currency code', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'content_show_code' ) ) );
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[content][show_symbol]', $content['show_symbol'], __( 'Show currency symbol', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'content_show_symbol' ) ) );
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[content][show_name]', $content['show_name'], __( 'Show currency name', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'content_show_name' ) ) );
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[behavior][active_first]', $settings->active_first(), __( 'Show selected currency first', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'active_first' ) ) );
			?>
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
		$themes     = array(
			SwitcherSettings::THEME_AUTOMATIC => __( 'Automatic', 'universal-multicurrency' ),
			SwitcherSettings::THEME_LIGHT     => __( 'Light', 'universal-multicurrency' ),
			SwitcherSettings::THEME_DARK      => __( 'Dark', 'universal-multicurrency' ),
		);
		$sizes      = array(
			SwitcherSettings::SIZE_COMPACT  => __( 'Compact', 'universal-multicurrency' ),
			SwitcherSettings::SIZE_STANDARD => __( 'Standard', 'universal-multicurrency' ),
			SwitcherSettings::SIZE_LARGE    => __( 'Large', 'universal-multicurrency' ),
		);
		$shapes     = array(
			SwitcherSettings::SHAPE_SLIGHT  => __( 'Slight', 'universal-multicurrency' ),
			SwitcherSettings::SHAPE_ROUNDED => __( 'Rounded', 'universal-multicurrency' ),
			SwitcherSettings::SHAPE_PILL    => __( 'Pill', 'universal-multicurrency' ),
		);
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Appearance', 'universal-multicurrency' ); ?></h3>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Theme', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[appearance][theme]',
						$themes,
						$appearance['theme'],
						array( 'data-umc-display-field' => 'theme' )
					)
				);
				?>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Size', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[appearance][size]',
						$sizes,
						$appearance['size'],
						array( 'data-umc-display-field' => 'size' )
					)
				);
				?>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Shape', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[appearance][shape]',
						$shapes,
						$appearance['shape'],
						array( 'data-umc-display-field' => 'shape' )
					)
				);
				?>
			</fieldset>
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
			<?php
			$this->echo_control_markup(
				$this->controls->toggle_row(
					'umc_display[behavior][remember_selection]',
					$settings->remember_selection(),
					__( 'Remember selected currency between visits', 'universal-multicurrency' ),
					__( 'When disabled, the selection applies to the current browsing session only.', 'universal-multicurrency' ),
					array( 'data-umc-display-field' => 'remember_selection' )
				)
			);
			?>
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
			<?php
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[visibility][desktop]', $visibility['desktop'], __( 'Show on desktop', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'visibility_desktop' ) ) );
			$this->echo_control_markup( $this->controls->toggle_row( 'umc_display[visibility][mobile]', $visibility['mobile'], __( 'Show on mobile', 'universal-multicurrency' ), '', array( 'data-umc-display-field' => 'visibility_mobile' ) ) );
			?>
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
				<div class="umc-display-preview-mock__hero">
					<span class="umc-display-preview-mock__heading"></span>
				</div>
				<div class="umc-display-preview-mock__grid">
					<div class="umc-display-preview-mock__product">
						<span class="umc-display-preview-mock__tile"></span>
						<span class="umc-display-preview-mock__name"></span>
						<span class="umc-display-preview-mock__price"></span>
					</div>
					<div class="umc-display-preview-mock__product">
						<span class="umc-display-preview-mock__tile"></span>
						<span class="umc-display-preview-mock__name"></span>
						<span class="umc-display-preview-mock__price"></span>
					</div>
					<div class="umc-display-preview-mock__product">
						<span class="umc-display-preview-mock__tile"></span>
						<span class="umc-display-preview-mock__name"></span>
						<span class="umc-display-preview-mock__price"></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns stored display settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function stored_display_raw(): array {
		$stored = $this->settings->get()['display'] ?? array();

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_replace_recursive( SwitcherSettings::default_array(), $stored );
	}

	/**
	 * Merges active-panel position POST values over stored settings.
	 *
	 * @param array<string, mixed> $stored   Stored position settings.
	 * @param array<string, mixed> $posted   Posted position settings.
	 * @param string               $placement Active placement mode.
	 * @return array<string, mixed>
	 */
	private function merge_position_preserving_inactive( array $stored, array $posted, string $placement ): array {
		$merged = $stored;

		if ( SwitcherSettings::PLACEMENT_MANUAL === $placement ) {
			return $merged;
		}

		if ( SwitcherSettings::PLACEMENT_FLOATING_SIDE === $placement ) {
			foreach ( array( 'side', 'vertical_alignment', 'vertical_offset', 'edge_offset' ) as $key ) {
				if ( array_key_exists( $key, $posted ) ) {
					$merged[ $key ] = $posted[ $key ];
				}
			}

			return $merged;
		}

		if ( SwitcherSettings::PLACEMENT_STICKY_FOOTER === $placement ) {
			foreach ( array( 'side', 'edge_offset', 'bottom_offset' ) as $key ) {
				if ( array_key_exists( $key, $posted ) ) {
					$merged[ $key ] = $posted[ $key ];
				}
			}
		}

		return $merged;
	}

	/**
	 * Normalizes checkbox-style POST values before settings parsing.
	 *
	 * @param array<string, mixed> $raw Raw POST display payload.
	 * @return array<string, mixed>
	 */
	private function normalize_post_raw( array $raw ): array {
		$raw['enabled'] = ! empty( $raw['enabled'] );

		foreach ( array( 'content', 'behavior', 'visibility', 'responsive' ) as $group ) {
			if ( ! isset( $raw[ $group ] ) || ! is_array( $raw[ $group ] ) ) {
				continue;
			}

			foreach ( $raw[ $group ] as $key => $value ) {
				if ( is_array( $value ) ) {
					continue;
				}

				$raw[ $group ][ $key ] = ! empty( $value );
			}
		}

		return $raw;
	}

	/**
	 * Merges posted content toggles over the stored content composition.
	 *
	 * The Display screen still submits the flat schema-5 toggles. They drive
	 * both contexts the same way the 5 → 6 migration does: code and symbol
	 * apply to trigger and menu, the currency name applies to the menu only,
	 * and the trigger's own name setting is preserved.
	 *
	 * @param array<string, mixed> $stored Stored content composition.
	 * @param array<string, mixed> $posted Posted content payload.
	 * @return array<string, mixed>
	 */
	private function merge_content( array $stored, array $posted ): array {
		if ( array() === $posted ) {
			return $stored;
		}

		$stored_trigger = is_array( $stored['trigger'] ?? null ) ? $stored['trigger'] : array();
		$stored_menu    = is_array( $stored['menu'] ?? null ) ? $stored['menu'] : array();

		if ( array_key_exists( 'trigger', $posted ) || array_key_exists( 'menu', $posted ) ) {
			return array(
				'trigger'      => is_array( $posted['trigger'] ?? null ) ? $posted['trigger'] : $stored_trigger,
				'menu'         => is_array( $posted['menu'] ?? null ) ? $posted['menu'] : $stored_menu,
				'show_chevron' => array_key_exists( 'show_chevron', $posted )
					? ! empty( $posted['show_chevron'] )
					: ! empty( $stored['show_chevron'] ),
			);
		}

		$show_code   = ! empty( $posted['show_code'] );
		$show_symbol = ! empty( $posted['show_symbol'] );

		return array(
			'trigger'      => array(
				'show_code'   => $show_code,
				'show_symbol' => $show_symbol,
				'show_name'   => ! empty( $stored_trigger['show_name'] ),
				'order'       => is_array( $stored_trigger['order'] ?? null ) ? $stored_trigger['order'] : array(),
			),
			'menu'         => array(
				'show_code'   => $show_code,
				'show_symbol' => $show_symbol,
				'show_name'   => ! empty( $posted['show_name'] ),
				'order'       => is_array( $stored_menu['order'] ?? null ) ? $stored_menu['order'] : array(),
			),
			'show_chevron' => ! empty( $stored['show_chevron'] ),
		);
	}

	/**
	 * Best-effort scan for switcher shortcodes on key storefront surfaces.
	 */
	private function detect_duplicate_shortcode_warning(): bool {
		return ( new SwitcherShortcodeScanner() )->has_shortcode_on_key_surface();
	}
}
