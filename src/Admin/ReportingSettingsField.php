<?php
/**
 * Multicurrency Reporting admin section.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\CurrencyRegistry;
use UMC\CurrencySwitcher;
use UMC\Reporting\ReportingConstants;
use UMC\Reporting\ReportingCache;
use UMC\Reporting\ReportingDateRange;
use UMC\Reporting\ReportingQuery;
use UMC\Reporting\ReportingQueryTooLargeException;
use UMC\Reporting\ReportingResult;

/**
 * Multicurrency Reporting admin section.
 */
final class ReportingSettingsField {

	/**
	 * Binds the field to reporting services.
	 *
	 * @param ReportingCache   $cache    Reporting cache.
	 * @param CurrencyRegistry $registry Currency registry.
	 */
	public function __construct(
		private ReportingCache $cache,
		private CurrencyRegistry $registry
	) {
	}

	/**
	 * Renders the reporting settings section.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to view reporting.', 'universal-multicurrency' ) . '</p>';
			return;
		}

		$ui     = new AdminComponentRenderer();
		$values = $this->values_from_request();
		$query  = ReportingQuery::from_input( $values );
		$result = null;
		$error  = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only reporting view.
		if ( isset( $_GET['umc_report'] ) ) {
			try {
				$refresh = isset( $_GET['umc_refresh'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['umc_refresh'] ) );
				$result  = $this->cache->get( $query, $refresh );
			} catch ( ReportingQueryTooLargeException $exception ) {
				$error = $exception->getMessage();
			}
		}

		echo '<div class="umc-reporting">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes page intro markup.
		echo $ui->page_intro(
			__( 'Reporting', 'universal-multicurrency' ),
			__( 'Historical order facts in native transaction currency. Reports never use live exchange rates or reconstruct historical pricing from current product settings.', 'universal-multicurrency' )
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->render_filters( $values );

		if ( '' !== $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		} elseif ( $result instanceof ReportingResult ) {
			$this->render_warnings( $result );
			$this->render_statistics( $ui, $result );
			$this->render_tables( $result );
			$this->render_export_link( $values );
		} else {
			echo '<p class="description">' . esc_html__( 'Choose filters and select View report.', 'universal-multicurrency' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Renders the reporting filter form.
	 *
	 * @param array<string, mixed> $values Current filter values.
	 */
	private function render_filters( array $values ): void {
		$action = admin_url( 'admin.php?page=wc-settings&tab=umc&section=' . SettingsPage::SECTION_REPORTING );

		echo '<form method="get" action="' . esc_url( $action ) . '" class="umc-reporting-filters">';
		echo '<input type="hidden" name="page" value="wc-settings" />';
		echo '<input type="hidden" name="tab" value="umc" />';
		echo '<input type="hidden" name="section" value="' . esc_attr( SettingsPage::SECTION_REPORTING ) . '" />';
		echo '<input type="hidden" name="umc_report" value="1" />';

		echo '<p><label>' . esc_html__( 'Date preset', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="preset">';
		foreach (
			array(
				ReportingDateRange::PRESET_7_DAYS  => __( 'Last 7 days', 'universal-multicurrency' ),
				ReportingDateRange::PRESET_30_DAYS => __( 'Last 30 days', 'universal-multicurrency' ),
				ReportingDateRange::PRESET_90_DAYS => __( 'Last 90 days', 'universal-multicurrency' ),
				ReportingDateRange::PRESET_YTD     => __( 'Year to date', 'universal-multicurrency' ),
				ReportingDateRange::PRESET_CUSTOM  => __( 'Custom range', 'universal-multicurrency' ),
			) as $value => $label
		) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( (string) ( $values['preset'] ?? '' ), $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		echo '<p><label>' . esc_html__( 'Custom start (YYYY-MM-DD)', 'universal-multicurrency' ) . '</label><br />';
		echo '<input type="date" name="start" value="' . esc_attr( (string) ( $values['start'] ?? '' ) ) . '" /></p>';
		echo '<p><label>' . esc_html__( 'Custom end (YYYY-MM-DD)', 'universal-multicurrency' ) . '</label><br />';
		echo '<input type="date" name="end" value="' . esc_attr( (string) ( $values['end'] ?? '' ) ) . '" /></p>';

		echo '<fieldset><legend>' . esc_html__( 'Order statuses', 'universal-multicurrency' ) . '</legend>';
		$selected_statuses = is_array( $values['statuses'] ?? null ) ? $values['statuses'] : ReportingConstants::default_statuses();
		foreach ( ReportingConstants::selectable_statuses() as $status ) {
			printf(
				'<label><input type="checkbox" name="statuses[]" value="%s" %s /> %s</label><br />',
				esc_attr( $status ),
				checked( in_array( $status, $selected_statuses, true ), true, false ),
				esc_html( $status )
			);
		}
		echo '</fieldset>';

		echo '<p><label>' . esc_html__( 'Transaction currency', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="currency"><option value="">' . esc_html__( 'All currencies', 'universal-multicurrency' ) . '</option>';
		foreach ( $this->registry->get_selectable_codes() as $code ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				selected( (string) ( $values['currency'] ?? '' ), $code, false ),
				esc_html( $code )
			);
		}
		echo '</select></p>';

		echo '<p><label>' . esc_html__( 'Currency origin', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="origin">';
		foreach (
			array(
				''                                        => __( 'All origins', 'universal-multicurrency' ),
				CurrencySwitcher::ORIGIN_CUSTOMER         => __( 'Customer selected', 'universal-multicurrency' ),
				CurrencySwitcher::ORIGIN_VISITOR_LOCATION => __( 'Visitor Location', 'universal-multicurrency' ),
				ReportingConstants::ORIGIN_UNKNOWN        => __( 'Unknown / legacy', 'universal-multicurrency' ),
			) as $value => $label
		) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( (string) ( $values['origin'] ?? '' ), $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		echo '<p><label>' . esc_html__( 'Checkout fallback', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="fallback">';
		foreach (
			array(
				''    => __( 'Any', 'universal-multicurrency' ),
				'yes' => __( 'Fallback occurred', 'universal-multicurrency' ),
				'no'  => __( 'No fallback', 'universal-multicurrency' ),
			) as $value => $label
		) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( (string) ( $values['fallback'] ?? '' ), $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		echo '<p><label>' . esc_html__( 'Pricing source (Pricing Source report only)', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="pricing_source">';
		foreach (
			array(
				''                                   => __( 'All product-line sources', 'universal-multicurrency' ),
				ReportingConstants::SOURCE_FIXED     => __( 'Fixed', 'universal-multicurrency' ),
				ReportingConstants::SOURCE_CONVERTED => __( 'Converted', 'universal-multicurrency' ),
				ReportingConstants::SOURCE_UNKNOWN   => __( 'Unknown / legacy', 'universal-multicurrency' ),
			) as $value => $label
		) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( (string) ( $values['pricing_source'] ?? '' ), $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		submit_button( __( 'View report', 'universal-multicurrency' ), 'primary', 'submit', false );
		echo ' ';
		echo '<button type="submit" name="umc_refresh" value="1" class="button">' . esc_html__( 'Refresh report', 'universal-multicurrency' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Renders data-quality warnings when diagnostics are non-zero.
	 *
	 * @param ReportingResult $result Reporting result.
	 */
	private function render_warnings( ReportingResult $result ): void {
		if ( ! $result->diagnostics()->has_warnings() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'This report includes legacy, partial, or unknown provenance data. Unknown buckets are shown explicitly and are not treated as zero.',
			'universal-multicurrency'
		);
		echo '</p></div>';
	}

	/**
	 * Renders summary statistics cards.
	 *
	 * @param AdminComponentRenderer $ui     Design-system renderer.
	 * @param ReportingResult        $result Reporting result.
	 */
	private function render_statistics( AdminComponentRenderer $ui, ReportingResult $result ): void {
		$stats = $result->statistics();
		$share = $stats->fixed_price_share();

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes all dynamic output.
		echo $ui->statistics_grid_open();
		echo $ui->statistics_card(
			__( 'Orders', 'universal-multicurrency' ),
			(string) $stats->qualifying_orders(),
			__( 'Qualifying orders in range', 'universal-multicurrency' )
		);
		echo $ui->statistics_card(
			__( 'Net order value', 'universal-multicurrency' ),
			number_format_i18n( $stats->net_order_value(), 2 ),
			__( 'Sum of order value minus refunds (native transaction currencies)', 'universal-multicurrency' )
		);
		echo $ui->statistics_card(
			__( 'Active currencies', 'universal-multicurrency' ),
			(string) $stats->active_currencies(),
			__( 'Distinct transaction currencies', 'universal-multicurrency' )
		);
		echo $ui->statistics_card(
			__( 'Fixed-price share', 'universal-multicurrency' ),
			null === $share ? '—' : sprintf( '%s%%', number_format_i18n( $share * 100, 1 ) ),
			__( 'Fixed product-line value divided by classified product-line value', 'universal-multicurrency' )
		);
		echo $ui->statistics_grid_close();
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders reporting detail tables.
	 *
	 * @param ReportingResult $result Reporting result.
	 */
	private function render_tables( ReportingResult $result ): void {
		echo '<h3>' . esc_html__( 'Currency Performance', 'universal-multicurrency' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Currency', 'Orders', 'Order value', 'Refunded value', 'Net order value', 'Average order value' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $result->currency_performance()->rows() as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row->currency() ) . '</td>';
			echo '<td>' . esc_html( (string) $row->order_count() ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row->order_value(), 2 ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row->refunded_value(), 2 ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row->net_order_value(), 2 ) ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( $row->average_order_value(), 2 ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		$pricing = $result->pricing_source();
		echo '<h3>' . esc_html__( 'Pricing Source (product lines only)', 'universal-multicurrency' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		foreach (
			array(
				'fixed'     => $pricing->fixed_total(),
				'converted' => $pricing->converted_total(),
				'unknown'   => $pricing->unknown_total(),
			) as $label => $amount
		) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( number_format_i18n( $amount, 2 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$origin = $result->origin();
		echo '<h3>' . esc_html__( 'Currency Origin', 'universal-multicurrency' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		foreach (
			array(
				'customer'         => $origin->customer_count(),
				'visitor_location' => $origin->visitor_location_count(),
				'unknown'          => $origin->unknown_count(),
			) as $label => $count
		) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$fallback = $result->checkout_fallback();
		echo '<h3>' . esc_html__( 'Checkout Fallback Summary', 'universal-multicurrency' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		foreach (
			array(
				'fallback_occurred'   => $fallback->fallback_count(),
				'shopper_mismatch'    => $fallback->shopper_mismatch_count(),
				'selected_checkout'   => $fallback->selected_mode_count(),
				'store_checkout_mode' => $fallback->store_mode_count(),
				'unknown_checkout'    => $fallback->unknown_checkout_data_count(),
			) as $label => $count
		) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $count ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Rate Provenance', 'universal-multicurrency' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Source</th><th>Provider</th><th>Orders</th></tr></thead><tbody>';
		foreach ( $result->rate_provenance()->rows() as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row->rate_source() ) . '</td>';
			echo '<td>' . esc_html( '' !== $row->provider() ? $row->provider() : '—' ) . '</td>';
			echo '<td>' . esc_html( (string) $row->order_count() ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Renders the CSV export link for the active filters.
	 *
	 * @param array<string, mixed> $values Current filter values.
	 */
	private function render_export_link( array $values ): void {
		$url = wp_nonce_url(
			add_query_arg(
				array_merge(
					$values,
					array(
						'action' => ReportingExportController::ACTION,
					)
				),
				admin_url( 'admin-post.php' )
			),
			ReportingExportController::ACTION
		);

		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'universal-multicurrency' ) . '</a></p>';
	}

	/**
	 * Normalizes reporting filter values from the current request.
	 *
	 * @return array<string, mixed>
	 */
	private function values_from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form.
		$raw = wp_unslash( $_GET );

		return array(
			'preset'         => sanitize_key( (string) ( $raw['preset'] ?? ReportingDateRange::PRESET_30_DAYS ) ),
			'start'          => sanitize_text_field( (string) ( $raw['start'] ?? '' ) ),
			'end'            => sanitize_text_field( (string) ( $raw['end'] ?? '' ) ),
			'statuses'       => isset( $raw['statuses'] ) && is_array( $raw['statuses'] ) ? array_map( 'sanitize_key', $raw['statuses'] ) : ReportingConstants::default_statuses(),
			'currency'       => sanitize_text_field( (string) ( $raw['currency'] ?? '' ) ),
			'origin'         => sanitize_key( (string) ( $raw['origin'] ?? '' ) ),
			'fallback'       => sanitize_key( (string) ( $raw['fallback'] ?? '' ) ),
			'pricing_source' => sanitize_key( (string) ( $raw['pricing_source'] ?? '' ) ),
		);
	}
}
