<?php
/**
 * Display switcher settings field for the Multicurrency admin tab.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Display\CurrencyPresentationAssetRegistry;
use UMC\Display\CurrencyPresentationResolver;
use UMC\Display\SwitcherCustomCss;
use UMC\Display\SwitcherElementComposer;
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
	 * In-page Display sub-navigation panels, in presentation order.
	 *
	 * @var array<int, string>
	 */
	public const PANELS = array( 'placement', 'content', 'design', 'advanced' );

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
	 * Whether submitted Custom CSS was rejected during the last {@see parse_post()} call.
	 *
	 * @var bool
	 */
	private bool $show_custom_css_rejected_notice = false;

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
	 * Whether the last parse attempt rejected submitted Custom CSS.
	 */
	public function show_custom_css_rejected_notice(): bool {
		return $this->show_custom_css_rejected_notice;
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
						<?php $this->render_subnav(); ?>
						<div class="umc-display-tabpanel" data-umc-display-panel="placement" role="group" aria-label="<?php esc_attr_e( 'Placement settings', 'universal-multicurrency' ); ?>">
							<?php $this->render_switcher_card( $settings ); ?>
							<?php $this->render_position_card( $settings ); ?>
						</div>
						<div class="umc-display-tabpanel" data-umc-display-panel="content" role="group" aria-label="<?php esc_attr_e( 'Content settings', 'universal-multicurrency' ); ?>">
							<?php $this->render_content_card( $settings ); ?>
							<?php $this->render_presentation_card( $settings ); ?>
							<?php $this->render_behavior_card( $settings ); ?>
						</div>
						<div class="umc-display-tabpanel" data-umc-display-panel="design" role="group" aria-label="<?php esc_attr_e( 'Design settings', 'universal-multicurrency' ); ?>">
							<?php $this->render_design_card( $settings ); ?>
							<?php $this->render_responsive_card( $settings ); ?>
							<?php $this->render_visibility_card( $settings ); ?>
						</div>
						<div class="umc-display-tabpanel" data-umc-display-panel="advanced" role="group" aria-label="<?php esc_attr_e( 'Advanced settings', 'universal-multicurrency' ); ?>">
							<?php $this->render_advanced_card( $settings ); ?>
						</div>
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
	 * @return array{display: array<string, mixed>, show_coercion_notice: bool, show_custom_css_rejected_notice: bool}|null Null when visibility is invalid.
	 */
	public function parse_post(): ?array {
		$this->show_coercion_notice            = false;
		$this->show_custom_css_rejected_notice = false;

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

		$merged                 = array_replace_recursive( $stored, $raw );
		$merged['position']     = $this->merge_position_preserving_inactive(
			is_array( $stored['position'] ?? null ) ? $stored['position'] : SwitcherSettings::default_array()['position'],
			is_array( $raw['position'] ?? null ) ? $raw['position'] : array(),
			$active_placement
		);
		$merged['placement']    = $active_placement;
		$merged['content']      = $this->merge_content(
			is_array( $stored['content'] ?? null ) ? $stored['content'] : array(),
			is_array( $raw['content'] ?? null ) ? $raw['content'] : array()
		);
		$merged['presentation'] = $this->merge_presentation(
			is_array( $stored['presentation'] ?? null ) ? $stored['presentation'] : SwitcherSettings::default_array()['presentation'],
			is_array( $raw['presentation'] ?? null ) ? $raw['presentation'] : array()
		);

		$can_edit_css         = SwitcherCustomCss::can_edit();
		$submitted_custom_css = $raw['custom_css'] ?? null;
		$merged['custom_css'] = SwitcherCustomCss::resolve_for_save(
			$submitted_custom_css,
			$stored['custom_css'] ?? '',
			$can_edit_css
		);

		if ( $can_edit_css && is_string( $submitted_custom_css ) ) {
			$normalized = trim( str_replace( array( "\r\n", "\r" ), "\n", $submitted_custom_css ) );
			if ( '' !== $normalized && ! SwitcherCustomCss::is_valid( $normalized ) ) {
				$this->show_custom_css_rejected_notice = true;
			}
		}

		if ( ! SwitcherSettings::visibility_valid_for_save( $merged ) ) {
			return null;
		}

		$settings = SwitcherSettings::from_array( $merged );

		$this->show_coercion_notice = $settings->was_style_coerced();

		return array(
			'display'                         => SwitcherSettings::sanitize_raw( $merged ),
			'show_coercion_notice'            => $this->show_coercion_notice,
			'show_custom_css_rejected_notice' => $this->show_custom_css_rejected_notice,
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
	 * Renders the in-page Display sub-navigation.
	 *
	 * The pill group stays hidden until the settings script activates it, so a
	 * scripting failure leaves every panel readable and savable instead of
	 * offering controls that cannot switch anything.
	 */
	private function render_subnav(): void {
		$labels = array(
			'placement' => __( 'Placement', 'universal-multicurrency' ),
			'content'   => __( 'Content', 'universal-multicurrency' ),
			'design'    => __( 'Design', 'universal-multicurrency' ),
			'advanced'  => __( 'Advanced', 'universal-multicurrency' ),
		);

		?>
		<div class="umc-display-subnav" role="group" aria-label="<?php esc_attr_e( 'Display sections', 'universal-multicurrency' ); ?>" data-umc-display-subnav hidden>
			<?php foreach ( self::PANELS as $index => $panel ) : ?>
				<button
					type="button"
					class="button umc-display-subnav__pill<?php echo 0 === $index ? ' is-active' : ''; ?>"
					data-umc-display-tab="<?php echo esc_attr( $panel ); ?>"
					aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>"
				><?php echo esc_html( $labels[ $panel ] ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php
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
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Content', 'universal-multicurrency' ); ?></h3>
			<?php
			$this->render_content_group(
				'trigger',
				__( 'Closed switcher (trigger)', 'universal-multicurrency' ),
				__( 'What customers see before they open the switcher.', 'universal-multicurrency' ),
				$settings->trigger_content()
			);

			$this->render_content_group(
				'menu',
				__( 'Currency list (menu)', 'universal-multicurrency' ),
				__( 'What each currency option shows in the list.', 'universal-multicurrency' ),
				$settings->menu_content()
			);

			$this->echo_control_markup(
				$this->controls->toggle_row(
					'umc_display[content][show_chevron]',
					$settings->show_chevron(),
					__( 'Show a dropdown arrow on the trigger', 'universal-multicurrency' ),
					__( 'Decorative only; the arrow is hidden from assistive technology.', 'universal-multicurrency' ),
					array( 'data-umc-display-field' => 'show_chevron' )
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Renders one content composition group (trigger or menu).
	 *
	 * @param string               $context     Group key: trigger or menu.
	 * @param string               $legend      Visible group legend.
	 * @param string               $description Supporting description.
	 * @param array<string, mixed> $group       Normalized group values.
	 */
	private function render_content_group( string $context, string $legend, string $description, array $group ): void {
		$order = is_array( $group['order'] ?? null ) ? $group['order'] : array();
		?>
		<fieldset class="umc-display-fieldset">
			<legend><?php echo esc_html( $legend ); ?></legend>
			<p class="description"><?php echo esc_html( $description ); ?></p>
			<?php
			foreach ( $this->content_element_labels() as $element => $label ) {
				$this->echo_control_markup(
					$this->controls->toggle_row(
						sprintf( 'umc_display[content][%s][show_%s]', $context, $element ),
						! empty( $group[ 'show_' . $element ] ),
						$label,
						'',
						array( 'data-umc-display-field' => $context . '_show_' . $element )
					)
				);
			}
			?>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Element order', 'universal-multicurrency' ); ?></span>
				<select name="<?php echo esc_attr( sprintf( 'umc_display[content][%s][order]', $context ) ); ?>" data-umc-display-field="<?php echo esc_attr( $context . '_order' ); ?>">
					<?php foreach ( $this->order_choices() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $this->order_choice_value( $order ) ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="description"><?php esc_html_e( 'Hidden elements are skipped; the order applies to the elements you enabled.', 'universal-multicurrency' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Visible label for each orderable content element.
	 *
	 * @return array<string, string>
	 */
	private function content_element_labels(): array {
		return array(
			SwitcherSettings::ELEMENT_CODE   => __( 'Show currency code', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_SYMBOL => __( 'Show currency symbol', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_NAME   => __( 'Show currency name', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_ICON   => __( 'Show presentation icon', 'universal-multicurrency' ),
		);
	}

	/**
	 * Renders presentation icon settings within the Content panel.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_presentation_card( SwitcherSettings $settings ): void {
		$presentation = $settings->presentation();
		$overrides    = $settings->icon_overrides();
		$regions      = CurrencyPresentationAssetRegistry::region_ids();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Currency presentation icons', 'universal-multicurrency' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Optional bundled flags are visual presentation only. A currency does not always correspond to one country. Built-in defaults are suggestions; you may override them per currency.', 'universal-multicurrency' ); ?></p>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Icon size', 'universal-multicurrency' ); ?></span>
				<select name="umc_display[presentation][icon_size]" data-umc-display-field="icon_size">
					<?php
					foreach (
						array(
							SwitcherSettings::SIZE_COMPACT => __( 'Compact', 'universal-multicurrency' ),
							SwitcherSettings::SIZE_STANDARD => __( 'Standard', 'universal-multicurrency' ),
							SwitcherSettings::SIZE_LARGE   => __( 'Large', 'universal-multicurrency' ),
						) as $value => $label
					) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings->icon_size() ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="umc-display-field">
				<span><?php esc_html_e( 'Icon shape', 'universal-multicurrency' ); ?></span>
				<select name="umc_display[presentation][icon_shape]" data-umc-display-field="icon_shape">
					<?php
					foreach (
						array(
							SwitcherSettings::ICON_SHAPE_NATURAL => __( 'Natural', 'universal-multicurrency' ),
							SwitcherSettings::ICON_SHAPE_SQUARE  => __( 'Square', 'universal-multicurrency' ),
							SwitcherSettings::ICON_SHAPE_CIRCLE  => __( 'Circle', 'universal-multicurrency' ),
						) as $value => $label
					) :
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings->icon_shape() ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<table class="widefat striped umc-display-icon-overrides">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Currency', 'universal-multicurrency' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Presentation region', 'universal-multicurrency' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Default', 'universal-multicurrency' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->presentation_override_rows() as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( $row['code'] ); ?></code></td>
							<td>
								<select name="<?php echo esc_attr( sprintf( 'umc_display[presentation][icon_overrides][%s]', $row['code'] ) ); ?>" data-umc-display-field="<?php echo esc_attr( 'icon_override_' . $row['code'] ); ?>">
									<option value=""><?php esc_html_e( 'Use built-in default', 'universal-multicurrency' ); ?></option>
									<?php foreach ( $regions as $region ) : ?>
										<option value="<?php echo esc_attr( $region ); ?>" <?php selected( $region, $row['selected'] ); ?>><?php echo esc_html( CurrencyPresentationAssetRegistry::region_label( $region ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><?php echo esc_html( $row['default_label'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( array() !== $overrides ) : ?>
				<p class="description"><?php esc_html_e( 'Overrides for disabled currencies remain saved even when they are not listed here.', 'universal-multicurrency' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enabled currency rows for the presentation override table.
	 *
	 * @return array<int, array{code: string, selected: string, default_label: string}>
	 */
	private function presentation_override_rows(): array {
		$settings  = $this->settings_repository->get();
		$overrides = $settings->icon_overrides();
		$rows      = array();

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}

			if ( ! is_string( $code ) ) {
				continue;
			}

			$default_region = CurrencyPresentationResolver::built_in_region_for_currency( $code );
			$default_label  = null === $default_region
				? __( 'None', 'universal-multicurrency' )
				: CurrencyPresentationAssetRegistry::region_label( $default_region );

			$rows[] = array(
				'code'          => strtoupper( $code ),
				'selected'      => $overrides[ strtoupper( $code ) ] ?? '',
				'default_label' => $default_label,
			);
		}

		return $rows;
	}

	/**
	 * Selectable element orderings, keyed by their submitted value.
	 *
	 * @return array<string, string>
	 */
	private function order_choices(): array {
		$choices = array();

		foreach ( $this->permutations( SwitcherElementComposer::ORDERABLE_ELEMENTS ) as $order ) {
			$value = implode( ',', $order );

			$choices[ $value ] = $this->format_order_label( $order );
		}

		return $choices;
	}

	/**
	 * Human-readable label for one element order permutation.
	 *
	 * @param array<int, string> $order Element order.
	 */
	private function format_order_label( array $order ): string {
		$labels = array(
			SwitcherSettings::ELEMENT_CODE   => __( 'Code', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_SYMBOL => __( 'Symbol', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_NAME   => __( 'Name', 'universal-multicurrency' ),
			SwitcherSettings::ELEMENT_ICON   => __( 'Icon', 'universal-multicurrency' ),
		);

		$parts = array();

		foreach ( $order as $element ) {
			$parts[] = $labels[ $element ] ?? $element;
		}

		return implode( ', ', $parts );
	}

	/**
	 * Generates all permutations of one element list.
	 *
	 * @param array<int, string> $items  Remaining items.
	 * @param array<int, string> $prefix Accumulated prefix.
	 * @return array<int, array<int, string>>
	 */
	private function permutations( array $items, array $prefix = array() ): array {
		if ( array() === $items ) {
			return array( $prefix );
		}

		$result = array();

		foreach ( $items as $index => $item ) {
			$remaining = $items;
			unset( $remaining[ $index ] );

			foreach ( $this->permutations( array_values( $remaining ), array_merge( $prefix, array( $item ) ) ) as $permutation ) {
				$result[] = $permutation;
			}
		}

		return $result;
	}

	/**
	 * Maps a stored (visible-only) order list onto a full ordering choice.
	 *
	 * @param array<int, string> $order Stored element order.
	 */
	private function order_choice_value( array $order ): string {
		$complete = array();

		foreach ( $order as $element ) {
			if ( is_string( $element ) && in_array( $element, SwitcherElementComposer::ORDERABLE_ELEMENTS, true ) && ! in_array( $element, $complete, true ) ) {
				$complete[] = $element;
			}
		}

		foreach ( SwitcherElementComposer::ORDERABLE_ELEMENTS as $element ) {
			if ( ! in_array( $element, $complete, true ) ) {
				$complete[] = $element;
			}
		}

		return implode( ',', $complete );
	}

	/**
	 * Renders the Design card: preset, theme, size, shape, overrides, motion.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_design_card( SwitcherSettings $settings ): void {
		$appearance = $settings->appearance();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Design', 'universal-multicurrency' ); ?></h3>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Preset', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[design][preset]',
						array(
							SwitcherSettings::PRESET_DEFAULT => __( 'Default', 'universal-multicurrency' ),
							SwitcherSettings::PRESET_MINIMAL => __( 'Minimal', 'universal-multicurrency' ),
							SwitcherSettings::PRESET_PILL => __( 'Pill', 'universal-multicurrency' ),
							SwitcherSettings::PRESET_COMPACT => __( 'Compact', 'universal-multicurrency' ),
							SwitcherSettings::PRESET_BORDERLESS => __( 'Borderless', 'universal-multicurrency' ),
							SwitcherSettings::PRESET_FLOATING => __( 'Floating', 'universal-multicurrency' ),
						),
						$settings->preset(),
						array( 'data-umc-display-field' => 'preset' )
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'A preset is a starting point. Default changes nothing, and theme, size, shape, and your own overrides always win over the preset.', 'universal-multicurrency' ); ?></p>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Theme', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[design][theme]',
						array(
							SwitcherSettings::THEME_AUTOMATIC => __( 'Automatic', 'universal-multicurrency' ),
							SwitcherSettings::THEME_LIGHT => __( 'Light', 'universal-multicurrency' ),
							SwitcherSettings::THEME_DARK  => __( 'Dark', 'universal-multicurrency' ),
						),
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
						'umc_display[design][size]',
						array(
							SwitcherSettings::SIZE_COMPACT => __( 'Compact', 'universal-multicurrency' ),
							SwitcherSettings::SIZE_STANDARD => __( 'Standard', 'universal-multicurrency' ),
							SwitcherSettings::SIZE_LARGE   => __( 'Large', 'universal-multicurrency' ),
						),
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
						'umc_display[design][shape]',
						array(
							SwitcherSettings::SHAPE_SLIGHT => __( 'Slight', 'universal-multicurrency' ),
							SwitcherSettings::SHAPE_ROUNDED => __( 'Rounded', 'universal-multicurrency' ),
							SwitcherSettings::SHAPE_PILL   => __( 'Pill', 'universal-multicurrency' ),
						),
						$appearance['shape'],
						array( 'data-umc-display-field' => 'shape' )
					)
				);
				?>
			</fieldset>
			<fieldset class="umc-display-fieldset">
				<legend><?php esc_html_e( 'Motion', 'universal-multicurrency' ); ?></legend>
				<?php
				$this->echo_control_markup(
					$this->controls->segmented_control(
						'umc_display[design][motion]',
						array(
							SwitcherSettings::MOTION_SUBTLE => __( 'Subtle', 'universal-multicurrency' ),
							SwitcherSettings::MOTION_NONE => __( 'None', 'universal-multicurrency' ),
						),
						$settings->motion(),
						array( 'data-umc-display-field' => 'motion' )
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'Customers who ask their system to reduce motion never see transitions, whichever option you choose.', 'universal-multicurrency' ); ?></p>
			</fieldset>
			<?php $this->render_override_fields( $settings->overrides() ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the sparse structured override inputs.
	 *
	 * @param array<string, int|string> $overrides Stored override map.
	 */
	private function render_override_fields( array $overrides ): void {
		$colors = array(
			'surface'     => __( 'Background', 'universal-multicurrency' ),
			'text'        => __( 'Text', 'universal-multicurrency' ),
			'border'      => __( 'Border', 'universal-multicurrency' ),
			'hover'       => __( 'Hover background', 'universal-multicurrency' ),
			'selected_bg' => __( 'Selected background', 'universal-multicurrency' ),
			'focus_ring'  => __( 'Focus ring', 'universal-multicurrency' ),
		);

		$numbers = array(
			'radius'         => array( __( 'Corner radius (px)', 'universal-multicurrency' ), 0, 500 ),
			'control_height' => array( __( 'Control height (px)', 'universal-multicurrency' ), 0, 500 ),
			'spacing'        => array( __( 'Spacing (px)', 'universal-multicurrency' ), 0, 500 ),
			'font_weight'    => array( __( 'Font weight', 'universal-multicurrency' ), 400, 700 ),
		);

		?>
		<fieldset class="umc-display-fieldset">
			<legend><?php esc_html_e( 'Color overrides', 'universal-multicurrency' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Leave a field empty to inherit the theme value. Accepts hex (#1f2937) or rgb()/rgba() colors.', 'universal-multicurrency' ); ?></p>
			<div class="umc-display-override-grid">
				<?php foreach ( $colors as $key => $label ) : ?>
					<label class="umc-display-field">
						<span><?php echo esc_html( $label ); ?></span>
						<input
							type="text"
							name="<?php echo esc_attr( sprintf( 'umc_display[design][overrides][%s]', $key ) ); ?>"
							value="<?php echo esc_attr( $this->override_value( $overrides, $key ) ); ?>"
							class="umc-display-color-input"
							spellcheck="false"
							autocomplete="off"
							placeholder="<?php esc_attr_e( 'Inherit', 'universal-multicurrency' ); ?>"
							data-umc-display-field="<?php echo esc_attr( 'override_' . $key ); ?>"
						/>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<fieldset class="umc-display-fieldset">
			<legend><?php esc_html_e( 'Size and spacing overrides', 'universal-multicurrency' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Leave a field empty to inherit the preset, theme, and size values.', 'universal-multicurrency' ); ?></p>
			<div class="umc-display-override-grid">
				<?php foreach ( $numbers as $key => $meta ) : ?>
					<label class="umc-display-field">
						<span><?php echo esc_html( $meta[0] ); ?></span>
						<input
							type="number"
							min="<?php echo esc_attr( (string) $meta[1] ); ?>"
							max="<?php echo esc_attr( (string) $meta[2] ); ?>"
							name="<?php echo esc_attr( sprintf( 'umc_display[design][overrides][%s]', $key ) ); ?>"
							value="<?php echo esc_attr( $this->override_value( $overrides, $key ) ); ?>"
							placeholder="<?php esc_attr_e( 'Inherit', 'universal-multicurrency' ); ?>"
							data-umc-display-field="<?php echo esc_attr( 'override_' . $key ); ?>"
						/>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Reads one override as a form value, treating absence as inherit.
	 *
	 * @param array<string, int|string> $overrides Stored override map.
	 * @param string                    $key       Override key.
	 */
	private function override_value( array $overrides, string $key ): string {
		return array_key_exists( $key, $overrides ) ? (string) $overrides[ $key ] : '';
	}

	/**
	 * Renders the small responsive override bag.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_responsive_card( SwitcherSettings $settings ): void {
		$responsive = $settings->responsive();
		?>
		<div class="umc-display-card">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Mobile adjustments', 'universal-multicurrency' ); ?></h3>
			<?php
			$this->echo_control_markup(
				$this->controls->toggle_row(
					'umc_display[responsive][hide_name_on_mobile]',
					! empty( $responsive['hide_name_on_mobile'] ),
					__( 'Hide the currency name on small screens', 'universal-multicurrency' ),
					'',
					array( 'data-umc-display-field' => 'hide_name_on_mobile' )
				)
			);

			$this->echo_control_markup(
				$this->controls->toggle_row(
					'umc_display[responsive][compact_on_mobile]',
					! empty( $responsive['compact_on_mobile'] ),
					__( 'Use compact spacing on small screens', 'universal-multicurrency' ),
					'',
					array( 'data-umc-display-field' => 'compact_on_mobile' )
				)
			);
			?>
			<p class="description"><?php esc_html_e( 'These adjustments apply on screens narrower than 768px. Check them on a device or a narrow browser window — the admin preview frame is not a real viewport.', 'universal-multicurrency' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the Advanced Custom CSS card.
	 *
	 * @param SwitcherSettings $settings Current display settings.
	 */
	private function render_advanced_card( SwitcherSettings $settings ): void {
		$can_edit = SwitcherCustomCss::can_edit();
		$css      = $settings->custom_css();

		?>
		<div class="umc-display-card umc-display-card--advanced">
			<h3 class="umc-display-card__title"><?php esc_html_e( 'Advanced Custom CSS', 'universal-multicurrency' ); ?></h3>
			<?php
			$this->echo_control_markup(
				$this->controls->callout(
					'info',
					__( 'Custom CSS applies on the storefront after you save. The live preview reflects the structured Design settings only — verify Custom CSS on a storefront page.', 'universal-multicurrency' )
				)
			);

			if ( ! $can_edit ) {
				$this->echo_control_markup(
					$this->controls->callout(
						'warning',
						__( 'Editing Custom CSS also requires the WordPress "edit_css" capability. Your stored CSS is shown read-only and is preserved exactly when you save other Display settings.', 'universal-multicurrency' )
					)
				);
			}
			?>
			<label class="umc-display-field umc-display-custom-css">
				<span><?php esc_html_e( 'Switcher CSS', 'universal-multicurrency' ); ?></span>
				<?php if ( $can_edit ) : ?>
					<textarea
						name="umc_display[custom_css]"
						class="umc-display-custom-css__input code"
						rows="12"
						spellcheck="false"
						autocomplete="off"
						aria-describedby="umc-display-custom-css-help"
					><?php echo esc_textarea( $css ); ?></textarea>
				<?php else : ?>
					<textarea
						class="umc-display-custom-css__input code"
						rows="12"
						spellcheck="false"
						readonly
						aria-readonly="true"
						aria-describedby="umc-display-custom-css-help"
					><?php echo esc_textarea( $css ); ?></textarea>
				<?php endif; ?>
			</label>
			<p class="description" id="umc-display-custom-css-help">
				<?php esc_html_e( 'Write complete selectors. Custom CSS is not scoped for you — prefix rules with .umc-switcher so the rest of your site is unaffected. @import, url(), backslash escape sequences, expression(), behavior:, -moz-binding, and raw angle brackets are rejected. A rejected submission keeps your last saved CSS and shows an error notice.', 'universal-multicurrency' ); ?>
			</p>
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
					'umc_display[behavior][active_first]',
					$settings->active_first(),
					__( 'Show selected currency first', 'universal-multicurrency' ),
					'',
					array( 'data-umc-display-field' => 'active_first' )
				)
			);

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

		if ( is_array( $raw['content'] ?? null ) ) {
			foreach ( array( 'trigger', 'menu' ) as $context ) {
				if ( is_array( $raw['content'][ $context ] ?? null ) ) {
					$raw['content'][ $context ] = $this->normalize_content_group_post( $raw['content'][ $context ] );
				}
			}
		}

		return $raw;
	}

	/**
	 * Normalizes one posted content group's toggles and element ordering.
	 *
	 * The Display screen submits the ordering as one delimited select value so
	 * merchants get a single native control; the stored shape stays a list.
	 *
	 * @param array<string, mixed> $group Posted group payload.
	 * @return array<string, mixed>
	 */
	private function normalize_content_group_post( array $group ): array {
		foreach ( SwitcherSettings::ELEMENT_SEQUENCE as $element ) {
			$key = 'show_' . $element;

			if ( array_key_exists( $key, $group ) ) {
				$group[ $key ] = ! empty( $group[ $key ] );
			}
		}

		if ( array_key_exists( 'show_icon', $group ) ) {
			$group['show_icon'] = ! empty( $group['show_icon'] );
		}

		if ( is_string( $group['order'] ?? null ) ) {
			$group['order'] = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $group['order'] ) ),
					static fn( string $element ): bool => '' !== $element
				)
			);
		}

		return $group;
	}

	/**
	 * Merges posted presentation settings over stored values.
	 *
	 * @param array<string, mixed> $stored Stored presentation subtree.
	 * @param array<string, mixed> $posted Posted presentation payload.
	 * @return array<string, mixed>
	 */
	private function merge_presentation( array $stored, array $posted ): array {
		if ( array() === $posted ) {
			return $stored;
		}

		$merged = $stored;

		if ( array_key_exists( 'icon_size', $posted ) ) {
			$merged['icon_size'] = $posted['icon_size'];
		}

		if ( array_key_exists( 'icon_shape', $posted ) ) {
			$merged['icon_shape'] = $posted['icon_shape'];
		}

		$stored_overrides = is_array( $stored['icon_overrides'] ?? null ) ? $stored['icon_overrides'] : array();
		$posted_overrides = is_array( $posted['icon_overrides'] ?? null ) ? $posted['icon_overrides'] : array();

		$merged['icon_overrides'] = $this->merge_icon_overrides( $stored_overrides, $posted_overrides );

		return $merged;
	}

	/**
	 * Merges posted icon overrides and retains disabled-currency entries.
	 *
	 * @param array<string, string> $stored Stored overrides.
	 * @param array<string, mixed>  $posted Posted overrides.
	 * @return array<string, string>
	 */
	private function merge_icon_overrides( array $stored, array $posted ): array {
		$merged = $stored;

		foreach ( $posted as $currency => $region ) {
			if ( ! is_string( $currency ) ) {
				continue;
			}

			$currency = strtoupper( trim( $currency ) );
			$region   = is_string( $region ) ? strtoupper( trim( $region ) ) : '';

			if ( '' === $region ) {
				unset( $merged[ $currency ] );
				continue;
			}

			$merged[ $currency ] = $region;
		}

		return $merged;
	}

	/**
	 * Merges posted content toggles over the stored content composition.
	 *
	 * The Content card submits the schema-6 shape (per-context toggles plus an
	 * element order), which replaces the stored composition wholesale. A flat
	 * schema-5 payload — an older cached screen, or a programmatic save — is
	 * still accepted and spread the way the 5 → 6 migration does: code and
	 * symbol apply to both contexts, the currency name applies to the menu
	 * only, and the trigger's own name setting is preserved.
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
