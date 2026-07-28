<?php
/**
 * Currencies admin table (custom WooCommerce settings field).
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Currency;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\ProviderMetadata;
use UMC\Rates\RateResolver;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateState;
use UMC\Settings;

/**
 * Renders the currencies table and parses its POST payload.
 */
final class CurrencyTableField {

	private const BLANK_ROWS = 3;
	private const FIELD      = 'umc_currencies';

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Store base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Rate persistence boundary.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $store;

	/**
	 * Rate status label evaluator.
	 *
	 * @var RateStatusEvaluator
	 */
	private RateStatusEvaluator $status;

	/**
	 * Binds the field to settings, the base currency, and the rate store.
	 *
	 * @param Settings          $settings Merchant settings store.
	 * @param Currency          $base     Store base currency.
	 * @param ExchangeRateStore $store    Rate persistence boundary.
	 */
	public function __construct( Settings $settings, Currency $base, ExchangeRateStore $store ) {
		$this->settings = $settings;
		$this->base     = $base;
		$this->store    = $store;
		$this->status   = new RateStatusEvaluator( $settings, $store );
	}

	/**
	 * Renders the currencies table field.
	 */
	public function render(): void {
		$configured = $this->settings->get_currencies();
		unset( $configured[ $this->base->code() ] );

		$provider_date = $this->provider_date_label();

		$rows  = '';
		$rows .= $this->base_row();

		$index = 0;
		foreach ( $configured as $code => $config ) {
			$rows .= $this->editable_row( (string) $index, (string) $code, $config, false, $provider_date );
			++$index;
		}

		for ( $blank = 0; $blank < self::BLANK_ROWS; $blank++ ) {
			$rows .= $this->editable_row( (string) $index, '', array(), true, $provider_date );
			++$index;
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Currencies', 'universal-multicurrency' ); ?></th>
			<td class="forminp">
				<table class="widefat umc-currencies-table" style="max-width:1200px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Enabled', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Code', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Symbol', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Position', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Decimals', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Manual rate', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Provider rate', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Adjustment %', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Effective rate', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Provider date', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Last updated', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Status', 'universal-multicurrency' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'universal-multicurrency' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses the currencies table POST payload into sanitized config rows.
	 *
	 * @param array<int|string, mixed> $raw Unslashed POST payload.
	 * @return array<string, array<string, mixed>>
	 */
	public function parse( array $raw ): array {
		return ( new CurrencySettingsParser( $this->settings, $this->base ) )->parse( $raw );
	}

	/**
	 * Renders the fixed base-currency table row.
	 */
	private function base_row(): string {
		return sprintf(
			'<tr><td>%1$s</td><td><strong>%2$s</strong></td><td colspan="12">%3$s</td></tr>',
			esc_html__( 'Always', 'universal-multicurrency' ),
			esc_html( $this->base->code() ),
			esc_html__( 'Base currency (configured in WooCommerce → Settings → General)', 'universal-multicurrency' )
		);
	}

	/**
	 * Renders one editable currency table row.
	 *
	 * @param string               $index         Row index in the POST array.
	 * @param string               $code          Currency code.
	 * @param array<string, mixed> $config        Row configuration.
	 * @param bool                 $code_open     Whether the code field is editable.
	 * @param string               $provider_date Provider date label for display.
	 */
	private function editable_row( string $index, string $code, array $config, bool $code_open, string $provider_date ): string {
		$name       = self::FIELD . '[' . $index . ']';
		$enabled    = ! empty( $config['enabled'] );
		$symbol     = isset( $config['symbol'] ) ? (string) $config['symbol'] : '';
		$position   = isset( $config['position'] ) ? (string) $config['position'] : Currency::DEFAULT_POSITION;
		$decimals   = isset( $config['decimals'] ) ? (int) $config['decimals'] : Currency::DEFAULT_DECIMALS;
		$manual     = isset( $config['manual_rate'] ) ? (string) $config['manual_rate'] : ( isset( $config['rate'] ) ? (string) $config['rate'] : '' );
		$provider   = isset( $config['provider_rate'] ) ? (string) $config['provider_rate'] : '';
		$adjustment = isset( $config['merchant_adjustment'] ) ? (string) $config['merchant_adjustment'] : '0';
		$mode       = isset( $config['rate_mode'] ) ? (string) $config['rate_mode'] : '';
		$updated_at = isset( $config['rate_updated_at'] ) ? (int) $config['rate_updated_at'] : 0;

		$effective = '';

		if ( '' !== $code ) {
			$resolved_mode = '' === $mode ? $this->settings->get_effective_rate_mode( $code ) : $mode;
			$effective     = RateResolver::effective_rate( $resolved_mode, $manual, $provider, $adjustment ) ?? '—';
		}

		$status_label = '' !== $code ? $this->status->display_label( $this->status->label_for_currency( $code ) ) : '';
		$updated      = $updated_at > 0 ? wp_date( get_option( 'date_format' ), $updated_at ) : '—';

		$code_cell = $code_open
			? sprintf( '<input type="text" maxlength="3" size="4" name="%1$s[code]" value="%2$s" placeholder="USD" />', esc_attr( $name ), esc_attr( $code ) )
			: sprintf( '<strong>%1$s</strong><input type="hidden" name="%2$s[code]" value="%1$s" />', esc_attr( $code ), esc_attr( $name ) );

		$action = '';

		if ( '' !== $code && Settings::RATE_MODE_AUTOMATIC === $this->settings->get_effective_rate_mode( $code ) ) {
			$url    = wp_nonce_url(
				admin_url( 'admin-post.php?action=umc_update_rates&scope=single&code=' . rawurlencode( $code ) ),
				'umc_update_rates'
			);
			$action = sprintf( '<a class="button button-small" href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Update now', 'universal-multicurrency' ) );
		}

		return sprintf(
			'<tr>
				<td><input type="checkbox" name="%1$s[enabled]" value="1"%2$s /></td>
				<td>%3$s</td>
				<td><input type="text" size="4" name="%1$s[symbol]" value="%4$s" /></td>
				<td>%5$s</td>
				<td><input type="number" min="0" max="%6$d" step="1" name="%1$s[decimals]" value="%7$d" /></td>
				<td>%8$s</td>
				<td><input type="text" size="8" name="%1$s[manual_rate]" value="%9$s" /></td>
				<td>%10$s</td>
				<td><input type="text" size="6" name="%1$s[merchant_adjustment]" value="%11$s" /></td>
				<td>%12$s</td>
				<td>%13$s</td>
				<td>%14$s</td>
				<td>%15$s</td>
				<td>%16$s</td>
			</tr>',
			esc_attr( $name ),
			checked( $enabled, true, false ),
			$code_cell,
			esc_attr( $symbol ),
			$this->position_select( $name . '[position]', $position ),
			Currency::MAX_DECIMALS,
			$decimals,
			$this->mode_select( $name . '[rate_mode]', $mode ),
			esc_attr( $manual ),
			esc_html( '' === $provider ? '—' : $provider ),
			esc_attr( $adjustment ),
			esc_html( (string) $effective ),
			esc_html( $provider_date ),
			esc_html( $updated ),
			esc_html( $status_label ),
			$action
		);
	}

	/**
	 * Returns the provider date label for the currencies table.
	 */
	private function provider_date_label(): string {
		$raw = $this->store->get_last_provider_metadata();

		if ( ! $raw instanceof ProviderMetadata ) {
			return '—';
		}

		return $raw->provider_date() ?? '—';
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
}
