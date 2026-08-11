<?php
/**
 * Global exchange-rate settings field.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Rates\RateHealthReport;
use UMC\Rates\RateHealthService;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateInterval;
use UMC\Rates\ExchangeRateStore;
use UMC\Settings;

/**
 * Renders global automatic-rate configuration on the settings tab.
 */
final class ExchangeRateSettingsField {

	/**
	 * Merchant settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Rate persistence boundary.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $store;

	/**
	 * Read-only rate health aggregation.
	 *
	 * @var RateHealthService
	 */
	private RateHealthService $health;

	/**
	 * Design-system renderer.
	 *
	 * @var AdminComponentRenderer
	 */
	private AdminComponentRenderer $ui;

	/**
	 * Binds the field to settings, the rate store, and health reporting.
	 *
	 * @param Settings               $settings Merchant settings store.
	 * @param ExchangeRateStore      $store    Rate persistence boundary.
	 * @param RateHealthService|null $health   Optional health service (built when omitted).
	 */
	public function __construct( Settings $settings, ExchangeRateStore $store, ?RateHealthService $health = null ) {
		$this->settings = $settings;
		$this->store    = $store;
		$this->health   = $health ?? new RateHealthService(
			$settings,
			$store,
			new RateStatusEvaluator( $settings, $store )
		);
		$this->ui       = new AdminComponentRenderer();
	}

	/**
	 * Renders the exchange-rate settings section.
	 */
	public function render(): void {
		$data     = $this->settings->get();
		$interval = (string) ( $data['rate_update_interval'] ?? Settings::DEFAULT_RATE_INTERVAL );
		$mode     = (string) ( $data['rate_mode'] ?? Settings::RATE_MODE_MANUAL );
		$max_age  = (int) ( $data['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS );
		$report   = $this->health->report();

		?>
		<tr valign="top">
			<td class="forminp umc-settings" colspan="2">
				<div class="umc-admin-card">
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes all dynamic output.
					echo $this->ui->page_intro(
						__( 'Exchange rate operations', 'universal-multicurrency' ),
						__( 'Monitor freshness, refresh automatic rates, and configure the global provider schedule. Storefront conversion never calls the provider live.', 'universal-multicurrency' )
					);
					echo $this->statistics_markup( $report );
					echo $this->quick_actions_markup();
					echo $this->ui->settings_card_open(
						__( 'Global exchange rates', 'universal-multicurrency' ),
						__( 'Choose the default rate mode and how often automatic currencies refresh.', 'universal-multicurrency' )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<table class="form-table" role="presentation">
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
					</table>
					<?php echo $this->ui->settings_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in renderer. ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Parses global exchange-rate settings from the current POST payload.
	 *
	 * @return array<string, mixed>
	 */
	public function parse_post(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce settings save.
		$mode = isset( $_POST['umc_rate_mode'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['umc_rate_mode'] ) ) : Settings::RATE_MODE_MANUAL;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$interval = isset( $_POST['umc_rate_update_interval'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['umc_rate_update_interval'] ) ) : Settings::DEFAULT_RATE_INTERVAL;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$max_age = isset( $_POST['umc_rate_max_age_hours'] ) ? absint( wp_unslash( $_POST['umc_rate_max_age_hours'] ) ) : Settings::DEFAULT_RATE_MAX_AGE_HOURS;

		return array(
			'rate_mode'            => $mode,
			'rate_provider'        => Settings::DEFAULT_RATE_PROVIDER,
			'rate_update_interval' => $interval,
			'rate_max_age_hours'   => $max_age,
		);
	}

	/**
	 * Builds the operations statistics grid markup.
	 *
	 * @param RateHealthReport $report Current health report.
	 */
	private function statistics_markup( RateHealthReport $report ): string {
		$provider = '' !== $report->provider_id()
			? ucfirst( $report->provider_id() )
			: __( 'Frankfurter', 'universal-multicurrency' );

		$aging_stale = (string) ( $report->aging_count() + $report->stale_count() );
		$aging_hint  = sprintf(
			/* translators: 1: aging count, 2: stale count */
			__( '%1$d aging · %2$d stale', 'universal-multicurrency' ),
			$report->aging_count(),
			$report->stale_count()
		);

		$html  = $this->ui->statistics_grid_open();
		$html .= $this->ui->statistics_card(
			__( 'Provider', 'universal-multicurrency' ),
			$provider,
			Settings::RATE_MODE_AUTOMATIC === $report->global_mode()
				? __( 'Automatic mode', 'universal-multicurrency' )
				: __( 'Manual mode', 'universal-multicurrency' )
		);
		$html .= $this->ui->statistics_card(
			__( 'Last successful update', 'universal-multicurrency' ),
			$this->format_timestamp( $report->last_success_at() )
		);
		$html .= $this->ui->statistics_card(
			__( 'Fresh rates', 'universal-multicurrency' ),
			(string) $report->fresh_count(),
			sprintf(
				/* translators: %d: automatic currency count */
				_n( '%d automatic currency', '%d automatic currencies', $report->automatic_target_count(), 'universal-multicurrency' ),
				$report->automatic_target_count()
			)
		);
		$html .= $this->ui->statistics_card(
			__( 'Aging + stale', 'universal-multicurrency' ),
			$aging_stale,
			$aging_hint
		);
		$html .= $this->ui->statistics_card(
			__( 'Next scheduled refresh', 'universal-multicurrency' ),
			$this->format_next_scheduled( $report )
		);
		$html .= $this->ui->statistics_grid_close();

		return $html;
	}

	/**
	 * Builds the quick-actions panel markup.
	 */
	private function quick_actions_markup(): string {
		return $this->ui->quick_actions_panel(
			__( 'Rate operations', 'universal-multicurrency' ),
			array(
				array(
					'label'       => __( 'Refresh now', 'universal-multicurrency' ),
					'url'         => wp_nonce_url(
						admin_url( 'admin-post.php?action=umc_update_rates&scope=all' ),
						'umc_update_rates'
					),
					'description' => __( 'Fetch automatic rates immediately', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'Review currencies', 'universal-multicurrency' ),
					'url'         => admin_url( 'admin.php?page=wc-settings&tab=umc&section=' . SettingsPage::SECTION_CURRENCIES ),
					'description' => __( 'Inspect per-currency modes and rates', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'Compatibility', 'universal-multicurrency' ),
					'url'         => admin_url( 'admin.php?page=wc-settings&tab=umc&section=' . SettingsPage::SECTION_COMPATIBILITY ),
					'description' => __( 'Configuration and conflict checks', 'universal-multicurrency' ),
				),
				array(
					'label'       => __( 'Site Health', 'universal-multicurrency' ),
					'url'         => admin_url( 'site-health.php' ),
					'description' => __( 'Exchange-rate health test', 'universal-multicurrency' ),
				),
			)
		);
	}

	/**
	 * Formats a Unix timestamp for admin display.
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	private function format_timestamp( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return __( 'Never', 'universal-multicurrency' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Formats the next scheduled refresh label from a health report.
	 *
	 * @param RateHealthReport $report Health report.
	 */
	private function format_next_scheduled( RateHealthReport $report ): string {
		if ( $report->scheduler_missing() ) {
			return __( 'Missing (should be scheduled)', 'universal-multicurrency' );
		}

		$next = $report->next_scheduled_at();

		if ( null === $next ) {
			return $report->has_automatic_targets()
				? __( 'Unavailable', 'universal-multicurrency' )
				: __( 'Not scheduled', 'universal-multicurrency' );
		}

		return $this->format_timestamp( $next );
	}
}
