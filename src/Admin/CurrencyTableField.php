<?php

/**
 * Currencies admin table (custom WooCommerce settings field).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Settings;

/**
 * Renders the currencies table and parses its POST payload.
 *
 * Rendering escapes all output; parsing produces the `Settings` array shape,
 * which is then validated by {@see Settings::sanitize()} on save — no
 * validation is reimplemented here. The base currency is shown read-only
 * (managed in WooCommerce → General; never stored in `umc_settings`).
 */
final class CurrencyTableField {

	private const BLANK_ROWS = 3;
	private const FIELD      = 'umc_currencies';

	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Base currency (read-only reference row).
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Binds the field to the settings store and base currency.
	 *
	 * @param Settings $settings Settings store.
	 * @param Currency $base     Base currency.
	 */
	public function __construct( Settings $settings, Currency $base ) {
		$this->settings = $settings;
		$this->base     = $base;
	}

	/**
	 * Renders the currencies table (the `woocommerce_admin_field_umc_currencies` callback).
	 */
	public function render(): void {
		$configured = $this->settings->get_currencies();
		unset( $configured[ $this->base->code() ] );

		$rows  = '';
		$rows .= $this->base_row();

		$index = 0;
		foreach ( $configured as $code => $config ) {
			$rows .= $this->editable_row( (string) $index, (string) $code, $config, false );
			++$index;
		}

		for ( $blank = 0; $blank < self::BLANK_ROWS; $blank++ ) {
			$rows .= $this->editable_row( (string) $index, '', array(), true );
			++$index;
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Currencies', 'universal-multicurrency' ); ?></th>
			<td class="forminp">
				<p class="description">
					<?php esc_html_e( 'Add currencies with a manual exchange rate. A rate means: 1 base unit = rate target units. Leave a row blank to ignore it.', 'universal-multicurrency' ); ?>
				</p>
				<table class="widefat umc-currencies-table" style="max-width:820px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Enabled', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Code', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Symbol', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Position', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Decimals', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Rate', 'universal-multicurrency' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Row markup assembled from escaped fragments below. ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses the posted table into the Settings `currencies` array shape.
	 *
	 * @param array<int|string, mixed> $raw Unslashed `umc_currencies` POST payload.
	 * @return array<string, array<string, mixed>>
	 */
	public function parse( array $raw ): array {
		$currencies = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$code = isset( $row['code'] ) ? strtoupper( sanitize_text_field( (string) $row['code'] ) ) : '';

			if ( '' === $code || $code === $this->base->code() ) {
				continue;
			}

			$currencies[ $code ] = array(
				'enabled'  => ! empty( $row['enabled'] ),
				'symbol'   => isset( $row['symbol'] ) ? sanitize_text_field( (string) $row['symbol'] ) : '',
				'position' => isset( $row['position'] ) ? sanitize_text_field( (string) $row['position'] ) : Currency::DEFAULT_POSITION,
				'decimals' => isset( $row['decimals'] ) ? (int) $row['decimals'] : Currency::DEFAULT_DECIMALS,
				'rate'     => isset( $row['rate'] ) ? sanitize_text_field( (string) $row['rate'] ) : '',
			);
		}

		return $currencies;
	}

	/**
	 * Renders the read-only base-currency row.
	 */
	private function base_row(): string {
		return sprintf(
			'<tr><td>%1$s</td><td><strong>%2$s</strong></td><td colspan="4">%3$s</td></tr>',
			esc_html__( 'Always', 'universal-multicurrency' ),
			esc_html( $this->base->code() ),
			esc_html__( 'Base currency (configured in WooCommerce → Settings → General)', 'universal-multicurrency' )
		);
	}

	/**
	 * Renders one editable currency row.
	 *
	 * @param string               $index     Row index (name grouping).
	 * @param string               $code      Currency code ('' for a blank row).
	 * @param array<string, mixed> $config    Row config.
	 * @param bool                 $code_open Whether the code is editable (blank rows).
	 */
	private function editable_row( string $index, string $code, array $config, bool $code_open ): string {
		$name     = self::FIELD . '[' . $index . ']';
		$enabled  = ! empty( $config['enabled'] );
		$symbol   = isset( $config['symbol'] ) ? (string) $config['symbol'] : '';
		$position = isset( $config['position'] ) ? (string) $config['position'] : Currency::DEFAULT_POSITION;
		$decimals = isset( $config['decimals'] ) ? (int) $config['decimals'] : Currency::DEFAULT_DECIMALS;
		$rate     = isset( $config['rate'] ) ? (string) $config['rate'] : '';

		$code_cell = $code_open
			? sprintf( '<input type="text" maxlength="3" size="4" name="%1$s[code]" value="%2$s" placeholder="USD" />', esc_attr( $name ), esc_attr( $code ) )
			: sprintf( '<strong>%1$s</strong><input type="hidden" name="%2$s[code]" value="%1$s" />', esc_attr( $code ), esc_attr( $name ) );

		return sprintf(
			'<tr>
				<td><input type="checkbox" name="%1$s[enabled]" value="1"%2$s /></td>
				<td>%3$s</td>
				<td><input type="text" size="4" name="%1$s[symbol]" value="%4$s" /></td>
				<td>%5$s</td>
				<td><input type="number" min="0" max="%6$d" step="1" name="%1$s[decimals]" value="%7$d" /></td>
				<td><input type="text" size="10" name="%1$s[rate]" value="%8$s" placeholder="0.00" /></td>
			</tr>',
			esc_attr( $name ),
			checked( $enabled, true, false ),
			$code_cell,
			esc_attr( $symbol ),
			$this->position_select( $name . '[position]', $position ),
			Currency::MAX_DECIMALS,
			$decimals,
			esc_attr( $rate )
		);
	}

	/**
	 * Renders the symbol-position select.
	 *
	 * @param string $name    Field name.
	 * @param string $current Current position value.
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
}
