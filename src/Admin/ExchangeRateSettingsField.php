<?php
/**
 * Global exchange-rate settings field.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateUpdateInterval;
use UMC\Rates\RateUpdateState;
use UMC\Settings;

/**
 * Renders global automatic-rate configuration on the settings tab.
 */
final class ExchangeRateSettingsField {

	private Settings $settings;

	private ExchangeRateStore $store;

	public function __construct( Settings $settings, ExchangeRateStore $store ) {
		$this->settings = $settings;
		$this->store    = $store;
	}

	/**
	 * Renders the exchange-rate settings section.
	 */
	public function render(): void {
		$data     = $this->settings->get();
		$interval = (string) ( $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL );
		$mode     = (string) ( $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL );
		$max_age  = (int) ( $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS );

		$last_success = $this->last_success_label();
		$next_run     = $this->next_run_label();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Exchange rates', 'universal-multicurrency' ); ?></th>
			<td class="forminp">
				<table class="form-table" style="margin:0;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate mode', 'universal-multicurrency' ); ?></th>
						<td>
							<select name="umc_rate_mode">
								<option value="<?php echo esc_attr( Settings::RATE_MODE_MANUAL ); ?>" <?php selected( $mode, Settings::RATE_MODE_MANUAL ); ?>><?php esc_html_e( 'Manual', 'universal-multicurrency' ); ?></option>
								<option value="<?php echo esc_attr( Settings::RATE_MODE_AUTOMATIC ); ?>" <?php selected( $mode, Settings::RATE_MODE_AUTOMATIC ); ?>><?php esc_html_e( 'Automatic', 'universal-multicurrency' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider', 'universal-multicurrency' ); ?></th>
						<td><?php esc_html_e( 'Frankfurter', 'universal-multicurrency' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Update interval', 'universal-multicurrency' ); ?></th>
						<td>
							<select name="umc_rate_update_interval">
								<?php foreach ( RateUpdateInterval::options() as $option ) : ?>
									<option value="<?php echo esc_attr( $option->iso8601() ); ?>" <?php selected( $interval, $option->iso8601() ); ?>>
										<?php echo esc_html( $option->label() ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum accepted age (hours)', 'universal-multicurrency' ); ?></th>
						<td>
							<input type="number" min="1" max="720" step="1" name="umc_rate_max_age_hours" value="<?php echo esc_attr( (string) $max_age ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last successful update', 'universal-multicurrency' ); ?></th>
						<td><?php echo esc_html( $last_success ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Next scheduled update', 'universal-multicurrency' ); ?></th>
						<td><?php echo esc_html( $next_run ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Update all now', 'universal-multicurrency' ); ?></th>
						<td>
							<?php echo wp_kses_post( $this->update_all_button() ); ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * @return array<string, mixed>
	 */
	public function parse_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		$mode = isset( $_POST['umc_rate_mode'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['umc_rate_mode'] ) ) : Settings::RATE_MODE_MANUAL;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$interval = isset( $_POST['umc_rate_update_interval'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['umc_rate_update_interval'] ) ) : Settings::DEFAULT_RATE_INTERVAL;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$max_age = isset( $_POST['umc_rate_max_age_hours'] ) ? (int) wp_unslash( $_POST['umc_rate_max_age_hours'] ) : Settings::DEFAULT_RATE_MAX_AGE_HOURS;

		return array(
			'rate_mode'            => $mode,
			'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $interval,
			'rate_max_age_hours'   => $max_age,
		);
	}

	private function last_success_label(): string {
		$latest = 0;

		foreach ( array_keys( $this->settings->get_currencies() ) as $code ) {
			$status = $this->store->get_operational_status( $code );

			if ( RateUpdateState::STATUS_SUCCESS === $status->last_status() ) {
				$latest = max( $latest, $status->last_fetch_at() );
			}
		}

		if ( $latest <= 0 ) {
			return __( 'Never', 'universal-multicurrency' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $latest );
	}

	private function next_run_label(): string {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return __( 'Unavailable', 'universal-multicurrency' );
		}

		$next = as_next_scheduled_action( \UMC\Rates\Scheduler::HOOK );

		if ( false === $next ) {
			return __( 'Not scheduled', 'universal-multicurrency' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $next );
	}

	private function update_all_button(): string {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_update_rates&scope=all' ),
			'umc_update_rates'
		);

		return sprintf(
			'<a class="button" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Update all automatic rates', 'universal-multicurrency' )
		);
	}
}
