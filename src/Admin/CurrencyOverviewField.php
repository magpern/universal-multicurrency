<?php
/**
 * Read-only currency overview and expandable editors.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Admin\ViewModel\CurrencyEditorViewModel;
use UMC\Admin\ViewModel\CurrencyOverviewViewModel;
use UMC\Admin\ViewModel\CurrencyViewModel;
use UMC\Admin\ViewModel\CurrencyViewModelFactory;
use UMC\Settings;

/**
 * Renders the currencies overview table and expandable editor rows.
 */
final class CurrencyOverviewField {

	private const FIELD = 'umc_currencies';

	/**
	 * Binds the field to its view-model factory.
	 *
	 * @param CurrencyViewModelFactory $factory View-model factory.
	 */
	public function __construct(
		private CurrencyViewModelFactory $factory
	) {
	}

	/**
	 * Renders the currencies section field.
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query arg.
		$open_editor = isset( $_GET['umc_edit'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['umc_edit'] ) ) : '';
		$view        = $this->factory->overview( $open_editor );

		?>
		<tr valign="top">
			<td class="forminp umc-settings" colspan="2">
				<div class="umc-currencies-section">
					<?php $this->render_add_currency( $view ); ?>
					<table class="widefat striped umc-currencies-overview">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Enabled', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Currency', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Mode', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Effective Rate', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Adjustment', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Status', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Updated', 'universal-multicurrency' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'universal-multicurrency' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $view->rows as $row ) {
								$this->render_overview_row( $row );
							}
							foreach ( $view->editors as $editor ) {
								$this->render_editor_row( $editor );
							}
							?>
						</tbody>
					</table>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the add-currency selector form.
	 *
	 * @param CurrencyOverviewViewModel $view Overview view model.
	 */
	private function render_add_currency( CurrencyOverviewViewModel $view ): void {
		$add_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_currency_add' ),
			'umc_currency_add'
		);
		?>
		<div class="umc-add-currency">
			<label for="umc-add-currency-code" class="screen-reader-text">
				<?php esc_html_e( 'Add currency', 'universal-multicurrency' ); ?>
			</label>
			<select
				id="umc-add-currency-code"
				class="wc-enhanced-select umc-add-currency__select"
				data-placeholder="<?php esc_attr_e( 'Search currencies…', 'universal-multicurrency' ); ?>"
				style="min-width: 360px;"
			>
				<option value=""></option>
				<?php foreach ( $view->add_options as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button
				type="button"
				class="button button-primary umc-add-currency__submit"
				data-add-url="<?php echo esc_url( $add_url ); ?>"
			>
				<?php esc_html_e( 'Add currency', 'universal-multicurrency' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Renders one read-only overview row.
	 *
	 * @param CurrencyViewModel $row Overview row view model.
	 */
	private function render_overview_row( CurrencyViewModel $row ): void {
		?>
		<tr class="<?php echo esc_attr( $row->is_base ? 'umc-overview-row umc-overview-row--base' : 'umc-overview-row' ); ?>">
			<td>
				<?php if ( $row->is_base ) : ?>
					<span class="umc-enabled-badge umc-enabled-badge--on"><?php esc_html_e( 'Always', 'universal-multicurrency' ); ?></span>
				<?php else : ?>
					<span class="umc-enabled-badge <?php echo esc_attr( $row->enabled ? 'umc-enabled-badge--on' : 'umc-enabled-badge--off' ); ?>">
						<?php echo esc_html( $row->enabled ? __( 'Yes', 'universal-multicurrency' ) : __( 'No', 'universal-multicurrency' ) ); ?>
					</span>
				<?php endif; ?>
			</td>
			<td>
				<strong><?php echo esc_html( $row->name ); ?></strong>
				<div class="umc-currency-meta">
					<span class="umc-currency-code"><?php echo esc_html( $row->code ); ?></span>
					<?php if ( '' !== $row->symbol ) : ?>
						<span class="umc-currency-symbol"><?php echo esc_html( $row->symbol ); ?></span>
					<?php endif; ?>
					<?php if ( $row->is_base ) : ?>
						<span class="umc-base-label"><?php esc_html_e( 'Base currency', 'universal-multicurrency' ); ?></span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo esc_html( $row->mode_label ); ?></td>
			<td>
				<?php if ( $row->is_base ) : ?>
					<span aria-hidden="true">—</span>
				<?php else : ?>
					<div class="umc-effective-rate">
						<span class="umc-effective-rate__value"><?php echo esc_html( $row->effective_rate_value ); ?></span>
						<?php if ( '' !== $row->effective_rate_source ) : ?>
							<span class="umc-effective-rate__source"><?php echo esc_html( $row->effective_rate_source ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $row->adjustment_label ); ?></td>
			<td>
				<span class="umc-status-badge umc-status-badge--<?php echo esc_attr( $row->status_class ); ?>">
					<?php echo esc_html( $row->status_label ); ?>
				</span>
			</td>
			<td><?php echo esc_html( $row->updated_label ); ?></td>
			<td class="umc-row-actions">
				<?php if ( $row->is_base ) : ?>
					<a href="<?php echo esc_url( $row->general_settings_url ); ?>"><?php esc_html_e( 'WooCommerce settings', 'universal-multicurrency' ); ?></a>
				<?php else : ?>
					<button type="button" class="button-link umc-editor-toggle" data-target="<?php echo esc_attr( $row->edit_anchor ); ?>">
						<?php esc_html_e( 'Edit', 'universal-multicurrency' ); ?>
					</button>
					<?php if ( $row->can_update_rate && '' !== $row->update_rate_url ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $row->update_rate_url ); ?>"><?php esc_html_e( 'Update rate', 'universal-multicurrency' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $row->toggle_url ); ?>"><?php echo esc_html( $row->toggle_label ); ?></a>
					<a href="<?php echo esc_url( $row->remove_url ); ?>" class="umc-remove-currency" data-confirm="<?php echo esc_attr__( 'Remove this currency from the store configuration?', 'universal-multicurrency' ); ?>"><?php esc_html_e( 'Remove', 'universal-multicurrency' ); ?></a>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders one expandable editor row.
	 *
	 * @param CurrencyEditorViewModel $editor Editor row view model.
	 */
	private function render_editor_row( CurrencyEditorViewModel $editor ): void {
		$name = self::FIELD . '[' . $editor->index . ']';
		?>
		<tr
			id="<?php echo esc_attr( 'umc-editor-' . strtolower( $editor->code ) ); ?>"
			class="umc-editor-row<?php echo $editor->is_open ? ' umc-editor-row--open' : ''; ?>"
		>
			<td colspan="8">
				<div class="umc-editor">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[code]" value="<?php echo esc_attr( $editor->code ); ?>" />
					<div class="umc-editor__header">
						<h3><?php echo esc_html( sprintf( '%1$s (%2$s)', $editor->name, $editor->code ) ); ?></h3>
						<button type="button" class="button-link umc-editor-close" data-target="<?php echo esc_attr( 'umc-editor-' . strtolower( $editor->code ) ); ?>">
							<?php esc_html_e( 'Close', 'universal-multicurrency' ); ?>
						</button>
					</div>
					<div class="umc-editor__grid">
						<fieldset class="umc-editor__section">
							<legend><?php esc_html_e( 'General', 'universal-multicurrency' ); ?></legend>
							<p>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $editor->enabled ); ?> />
									<?php esc_html_e( 'Enabled', 'universal-multicurrency' ); ?>
								</label>
							</p>
							<p>
								<label><?php esc_html_e( 'Symbol override', 'universal-multicurrency' ); ?></label><br />
								<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[symbol]" value="<?php echo esc_attr( $editor->symbol ); ?>" />
							</p>
							<p>
								<label><?php esc_html_e( 'Symbol position', 'universal-multicurrency' ); ?></label><br />
								<?php echo $this->position_select( $name . '[position]', $editor->position ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
							<p>
								<label><?php esc_html_e( 'Decimals', 'universal-multicurrency' ); ?></label><br />
								<?php echo $this->decimals_select( $name . '[decimals]', $editor->decimals ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
						</fieldset>
						<fieldset class="umc-editor__section">
							<legend><?php esc_html_e( 'Exchange rate', 'universal-multicurrency' ); ?></legend>
							<p>
								<label><?php esc_html_e( 'Rate mode', 'universal-multicurrency' ); ?></label><br />
								<?php echo $this->mode_select( $name . '[rate_mode]', $editor->rate_mode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</p>
							<p>
								<label><?php esc_html_e( 'Provider rate', 'universal-multicurrency' ); ?></label><br />
								<input type="text" class="regular-text" value="<?php echo esc_attr( '' === $editor->provider_rate ? '—' : $editor->provider_rate ); ?>" readonly />
							</p>
							<p class="<?php echo esc_attr( $editor->show_adjustment ? '' : 'umc-editor-field--hidden' ); ?>">
								<label><?php esc_html_e( 'Adjustment percentage', 'universal-multicurrency' ); ?></label><br />
								<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[merchant_adjustment]" value="<?php echo esc_attr( $editor->merchant_adjustment ); ?>" />
							</p>
							<p>
								<label><?php esc_html_e( 'Effective rate', 'universal-multicurrency' ); ?></label><br />
								<span class="umc-effective-rate">
									<span class="umc-effective-rate__value"><?php echo esc_html( $editor->effective_rate_value ); ?></span>
									<span class="umc-effective-rate__source"><?php echo esc_html( $editor->effective_rate_source ); ?></span>
								</span>
							</p>
							<p class="<?php echo esc_attr( $editor->show_manual_rate ? '' : 'umc-editor-field--hidden' ); ?>">
								<label><?php esc_html_e( 'Manual rate', 'universal-multicurrency' ); ?></label><br />
								<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[manual_rate]" value="<?php echo esc_attr( $editor->manual_rate ); ?>" />
							</p>
							<?php if ( '' !== $editor->update_rate_url ) : ?>
								<p>
									<a class="button button-secondary" href="<?php echo esc_url( $editor->update_rate_url ); ?>">
										<?php esc_html_e( 'Update now', 'universal-multicurrency' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</fieldset>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Builds the per-row rate mode select markup.
	 *
	 * @param string $name    Field name.
	 * @param string $current Current selected value.
	 */
	private function mode_select( string $name, string $current ): string {
		$options = array(
			''                            => __( 'Inherit global', 'universal-multicurrency' ),
			Settings::RATE_MODE_MANUAL    => __( 'Manual', 'universal-multicurrency' ),
			Settings::RATE_MODE_AUTOMATIC => __( 'Automatic', 'universal-multicurrency' ),
		);

		$html = '';

		foreach ( $options as $value => $label ) {
			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}

		return sprintf( '<select name="%1$s">%2$s</select>', esc_attr( $name ), $html );
	}

	/**
	 * Builds the per-row symbol position select markup.
	 *
	 * @param string $name    Field name.
	 * @param string $current Current selected value.
	 */
	private function position_select( string $name, string $current ): string {
		$labels = array(
			'left'        => __( 'Left', 'universal-multicurrency' ),
			'right'       => __( 'Right', 'universal-multicurrency' ),
			'left_space'  => __( 'Left with space', 'universal-multicurrency' ),
			'right_space' => __( 'Right with space', 'universal-multicurrency' ),
		);

		$options = '';

		foreach ( $labels as $value => $label ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}

		return sprintf( '<select name="%1$s">%2$s</select>', esc_attr( $name ), $options );
	}

	/**
	 * Builds the per-row decimals select markup.
	 *
	 * @param string $name    Field name.
	 * @param int    $current Current selected value.
	 */
	private function decimals_select( string $name, int $current ): string {
		$options = '';

		for ( $decimals = 0; $decimals <= \UMC\Currency::MAX_DECIMALS; $decimals++ ) {
			$options .= sprintf(
				'<option value="%1$d"%2$s>%1$d</option>',
				$decimals,
				selected( $current, $decimals, false )
			);
		}

		return sprintf( '<select name="%1$s">%2$s</select>', esc_attr( $name ), $options );
	}
}
