<?php
/**
 * Fixed Pricing catalog operations admin screen.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\CurrencyRegistry;
use UMC\Pricing\FixedPriceCatalogQuery;
use UMC\Pricing\FixedPriceCoverageReport;

/**
 * Dedicated Fixed Pricing screen (ADR-0029): catalog coverage visibility and
 * the preview step of the preview → confirm → execute flow. The confirm/
 * execute step is handled by {@see FixedPricingOperationController}; this
 * class only ever reads — it performs no writes.
 *
 * Registered as `SettingsPage::SECTION_FIXED_PRICING`, following the same
 * `SettingsPage` section pattern as Reporting/Compatibility/Decision
 * Inspector. No new top-level WordPress menu, and no WooCommerce Products-list
 * bulk-action dropdown entries.
 */
final class FixedPricingSettingsField {

	/**
	 * Maximum products fetched for the browsing/coverage-table view.
	 */
	public const DISPLAY_LIMIT = 200;

	/**
	 * Products shown per browsing page.
	 */
	public const PER_PAGE = 20;

	/**
	 * Binds the field to its collaborators.
	 *
	 * @param FixedPriceCatalogQuery $query    Shared classified catalog listing.
	 * @param CurrencyRegistry       $registry Currency configuration.
	 */
	public function __construct(
		private FixedPriceCatalogQuery $query,
		private CurrencyRegistry $registry
	) {
	}

	/**
	 * Renders the Fixed Pricing screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce shop-manager capability.
			echo '<p>' . esc_html__( 'You do not have permission to manage fixed prices.', 'universal-multicurrency' ) . '</p>';
			return;
		}

		$ui     = new AdminComponentRenderer();
		$values = $this->values_from_request();

		echo '<div class="umc-fixed-pricing">';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes page intro markup.
		echo $ui->page_intro(
			__( 'Fixed Pricing', 'universal-multicurrency' ),
			__( 'Catalog-wide coverage for per-currency fixed prices (M20). Seed fixed prices from the current exchange rate, or clear them, without editing every product by hand.', 'universal-multicurrency' )
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->render_currency_form( $ui, $values );

		if ( '' === $values['currency'] ) {
			echo '</div>';
			return;
		}

		if ( '' !== $values['action'] && '' !== $values['scope'] ) {
			$this->render_preview( $ui, $values );
			echo '</div>';
			return;
		}

		$this->render_filters( $values );
		$this->render_coverage_table( $ui, $values );
		echo '</div>';
	}

	/**
	 * Renders the currency picker, always visible.
	 *
	 * @param AdminComponentRenderer $ui     Design-system renderer.
	 * @param array<string, mixed>   $values Current request values.
	 */
	private function render_currency_form( AdminComponentRenderer $ui, array $values ): void {
		unset( $ui );

		$action = admin_url( 'admin.php' );

		echo '<form method="get" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="page" value="wc-settings" />';
		echo '<input type="hidden" name="tab" value="umc" />';
		echo '<input type="hidden" name="section" value="' . esc_attr( SettingsPage::SECTION_FIXED_PRICING ) . '" />';
		echo '<p><label>' . esc_html__( 'Currency', 'universal-multicurrency' ) . '</label><br />';
		echo '<select name="umc_fp_currency">';
		echo '<option value="">' . esc_html__( 'Choose a currency…', 'universal-multicurrency' ) . '</option>';

		foreach ( $this->registry->get_currencies() as $currency ) {
			if ( $this->registry->is_base( $currency->code() ) ) {
				continue;
			}

			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $currency->code() ),
				selected( $values['currency'], $currency->code(), false ),
				esc_html( $currency->code() )
			);
		}

		echo '</select> ';
		submit_button( __( 'View coverage', 'universal-multicurrency' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Renders the status/search filter form for the selected currency.
	 *
	 * @param array<string, mixed> $values Current request values.
	 */
	private function render_filters( array $values ): void {
		$action = admin_url( 'admin.php' );

		echo '<form method="get" action="' . esc_url( $action ) . '" class="umc-fixed-pricing-filters">';
		echo '<input type="hidden" name="page" value="wc-settings" />';
		echo '<input type="hidden" name="tab" value="umc" />';
		echo '<input type="hidden" name="section" value="' . esc_attr( SettingsPage::SECTION_FIXED_PRICING ) . '" />';
		echo '<input type="hidden" name="umc_fp_currency" value="' . esc_attr( $values['currency'] ) . '" />';

		echo '<label>' . esc_html__( 'Status', 'universal-multicurrency' ) . ' ';
		echo '<select name="umc_fp_status">';
		foreach (
			array(
				''                                       => __( 'All statuses', 'universal-multicurrency' ),
				FixedPriceCoverageReport::STATUS_FIXED   => __( 'Fixed', 'universal-multicurrency' ),
				FixedPriceCoverageReport::STATUS_PARTIAL => __( 'Partial', 'universal-multicurrency' ),
				FixedPriceCoverageReport::STATUS_FX_FALLBACK => __( 'FX fallback', 'universal-multicurrency' ),
				FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS => __( 'No priceable variations', 'universal-multicurrency' ),
			) as $value => $label
		) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $value ),
				selected( $values['status'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select></label> ';

		echo '<label>' . esc_html__( 'Search', 'universal-multicurrency' ) . ' ';
		echo '<input type="search" name="umc_fp_search" value="' . esc_attr( $values['search'] ) . '" /></label> ';

		submit_button( __( 'Apply filters', 'universal-multicurrency' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Renders the paginated, checkbox-selectable coverage table.
	 *
	 * @param AdminComponentRenderer $ui     Design-system renderer.
	 * @param array<string, mixed>   $values Current request values.
	 */
	private function render_coverage_table( AdminComponentRenderer $ui, array $values ): void {
		$classified = $this->query->classify_catalog( $values['currency'], $values['status'], $values['search'], self::DISPLAY_LIMIT );
		$rows       = $classified['rows'];
		$total      = count( $rows );
		$offset     = ( $values['page'] - 1 ) * self::PER_PAGE;
		$page_rows  = array_slice( $rows, $offset, self::PER_PAGE );

		if ( array() === $rows ) {
			echo '<p class="description">' . esc_html__( 'No products match this filter.', 'universal-multicurrency' ) . '</p>';
			return;
		}

		if ( $classified['truncated'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				sprintf(
					/* translators: %d: display limit */
					__( 'Showing the first %d matching products. Use the search or status filter to narrow the list, or use the CLI for whole-catalog operations.', 'universal-multicurrency' ),
					self::DISPLAY_LIMIT
				)
			) . '</p></div>';
		}

		$action_base = add_query_arg(
			array(
				'page'            => 'wc-settings',
				'tab'             => 'umc',
				'section'         => SettingsPage::SECTION_FIXED_PRICING,
				'umc_fp_currency' => $values['currency'],
				'umc_fp_status'   => $values['status'],
				'umc_fp_search'   => $values['search'],
			),
			admin_url( 'admin.php' )
		);

		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		foreach ( array(
			'page'            => 'wc-settings',
			'tab'             => 'umc',
			'section'         => SettingsPage::SECTION_FIXED_PRICING,
			'umc_fp_currency' => $values['currency'],
			'umc_fp_status'   => $values['status'],
			'umc_fp_search'   => $values['search'],
		) as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}

		echo '<table class="widefat striped"><thead><tr><th></th><th>' . esc_html__( 'Product', 'universal-multicurrency' ) . '</th><th>' . esc_html__( 'Status', 'universal-multicurrency' ) . '</th></tr></thead><tbody>';

		foreach ( $page_rows as $row ) {
			$product = $row['product'];
			printf(
				'<tr><td><input type="checkbox" name="product_ids[]" value="%1$d" /></td><td>%2$s</td><td>%3$s</td></tr>',
				(int) $product->get_id(),
				esc_html( $product->get_name() ),
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes badge markup.
				$ui->status_badge( $this->status_label( $row['status'] ), $this->status_variant( $row['status'] ) )
			);
		}

		echo '</tbody></table>';

		echo '<input type="hidden" name="umc_fp_scope" value="' . esc_attr( FixedPricingOperationController::SCOPE_CHECKED ) . '" />';
		echo '<p>';
		printf(
			'<button type="submit" class="button" name="umc_fp_action" value="%1$s">%2$s</button> ',
			esc_attr( FixedPricingOperationController::ACTION_SEED ),
			esc_html__( 'Preview: seed fixed prices for checked', 'universal-multicurrency' )
		);
		printf(
			'<button type="submit" class="button" name="umc_fp_action" value="%1$s">%2$s</button>',
			esc_attr( FixedPricingOperationController::ACTION_CLEAR ),
			esc_html__( 'Preview: clear fixed prices for checked', 'universal-multicurrency' )
		);
		echo '</p>';
		echo '</form>';

		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: total number of products matching the current filter */
				_n(
					'%d product matches the current filter.',
					'%d products match the current filter.',
					$total,
					'universal-multicurrency'
				),
				$total
			)
		) . '</p>';

		printf(
			'<p><a class="button" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
			esc_url(
				add_query_arg(
					array(
						'umc_fp_action' => FixedPricingOperationController::ACTION_SEED,
						'umc_fp_scope'  => FixedPricingOperationController::SCOPE_FILTERED,
					),
					$action_base
				)
			),
			esc_html__( 'Preview: seed fixed prices for all matching products', 'universal-multicurrency' ),
			esc_url(
				add_query_arg(
					array(
						'umc_fp_action' => FixedPricingOperationController::ACTION_CLEAR,
						'umc_fp_scope'  => FixedPricingOperationController::SCOPE_FILTERED,
					),
					$action_base
				)
			),
			esc_html__( 'Preview: clear fixed prices for all matching products', 'universal-multicurrency' )
		);

		$this->render_pagination( $action_base, $values['page'], (int) ceil( $total / self::PER_PAGE ) );
	}

	/**
	 * Renders simple prev/next pagination links.
	 *
	 * @param string $base_url  Base URL carrying current filters.
	 * @param int    $page      Current page (1-indexed).
	 * @param int    $last_page Last page number.
	 */
	private function render_pagination( string $base_url, int $page, int $last_page ): void {
		if ( $last_page <= 1 ) {
			return;
		}

		echo '<p class="umc-fixed-pricing-pagination">';

		if ( $page > 1 ) {
			printf( '<a href="%s">%s</a> ', esc_url( add_query_arg( 'umc_fp_page', $page - 1, $base_url ) ), esc_html__( '« Previous', 'universal-multicurrency' ) );
		}

		/* translators: 1: current page number, 2: last page number */
		printf( esc_html__( 'Page %1$d of %2$d', 'universal-multicurrency' ), (int) $page, (int) $last_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format string is a static, translated literal.

		if ( $page < $last_page ) {
			printf( ' <a href="%s">%s</a>', esc_url( add_query_arg( 'umc_fp_page', $page + 1, $base_url ) ), esc_html__( 'Next »', 'universal-multicurrency' ) );
		}

		echo '</p>';
	}

	/**
	 * Renders the preview summary and the nonce-protected confirm form.
	 *
	 * @param AdminComponentRenderer $ui     Design-system renderer.
	 * @param array<string, mixed>   $values Current request values.
	 */
	private function render_preview( AdminComponentRenderer $ui, array $values ): void {
		$resolved = $this->resolve_preview_scope( $values );

		if ( null !== $resolved['error'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes panel markup.
			echo $ui->warning_panel( __( 'Cannot preview this operation', 'universal-multicurrency' ), $resolved['error'] );
			return;
		}

		$count  = count( $resolved['product_ids'] );
		$sample = array_slice( $resolved['names'], 0, 10 );

		$summary = self::seed_action( $values['action'] )
			? sprintf(
				/* translators: 1: number of products, 2: currency code, 3: current exchange rate */
				__( 'This will seed fixed %2$s prices for %1$d product(s)/variation(s) from the current exchange rate (%3$s — subject to change; the rate actually used is resolved again at execution and reported in the result).', 'universal-multicurrency' ),
				$count,
				$values['currency'],
				$resolved['preview_rate']
			)
			: sprintf(
				/* translators: 1: number of products, 2: currency code */
				__( 'This will clear fixed %2$s prices for %1$d product(s)/variation(s). Other currencies are unaffected.', 'universal-multicurrency' ),
				$count,
				$values['currency']
			);

		if ( $resolved['excluded'] > 0 ) {
			$summary .= ' ' . sprintf(
				/* translators: %d: number of products excluded */
				__( '%d matching product(s) will be excluded because you do not have permission to edit them.', 'universal-multicurrency' ),
				$resolved['excluded']
			);
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminComponentRenderer escapes panel markup; sample list is separately escaped below.
		echo $ui->info_panel( __( 'Preview', 'universal-multicurrency' ), $summary );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( array() !== $sample ) {
			echo '<ul>';
			foreach ( $sample as $name ) {
				echo '<li>' . esc_html( $name ) . '</li>';
			}
			if ( $count > count( $sample ) ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %d: number of additional products not shown in the sample */
						__( '… and %d more.', 'universal-multicurrency' ),
						$count - count( $sample )
					)
				) . '</li>';
			}
			echo '</ul>';
		}

		if ( $count > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'umc_fixed_pricing_execute' );
			echo '<input type="hidden" name="action" value="umc_fixed_pricing_execute" />';
			echo '<input type="hidden" name="umc_fp_action" value="' . esc_attr( $values['action'] ) . '" />';
			echo '<input type="hidden" name="umc_fp_currency" value="' . esc_attr( $values['currency'] ) . '" />';
			echo '<input type="hidden" name="umc_fp_scope" value="' . esc_attr( $values['scope'] ) . '" />';

			if ( FixedPricingOperationController::SCOPE_CHECKED === $values['scope'] ) {
				foreach ( $resolved['product_ids'] as $id ) {
					echo '<input type="hidden" name="product_ids[]" value="' . (int) $id . '" />';
				}
			} else {
				echo '<input type="hidden" name="umc_fp_status" value="' . esc_attr( $values['status'] ) . '" />';
				echo '<input type="hidden" name="umc_fp_search" value="' . esc_attr( $values['search'] ) . '" />';
			}

			submit_button( __( 'Confirm', 'universal-multicurrency' ), 'primary', 'submit', false );
			echo '</form>';
		}

		printf(
			'<p><a class="button" href="%s">%s</a></p>',
			esc_url(
				add_query_arg(
					array(
						'page'            => 'wc-settings',
						'tab'             => 'umc',
						'section'         => SettingsPage::SECTION_FIXED_PRICING,
						'umc_fp_currency' => $values['currency'],
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Cancel', 'universal-multicurrency' )
		);
	}

	/**
	 * Read-only resolution of the previewed scope, mirroring
	 * {@see FixedPricingOperationController}'s authoritative resolution so
	 * the preview accurately represents what execute will do. Performs no
	 * writes.
	 *
	 * @param array<string, mixed> $values Current request values.
	 * @return array{product_ids: array<int, int>, names: array<int, string>, excluded: int, preview_rate: string, error: string|null}
	 */
	private function resolve_preview_scope( array $values ): array {
		if ( FixedPricingOperationController::SCOPE_CHECKED === $values['scope'] ) {
			$ids = $values['product_ids'];
		} else {
			$classified = $this->query->classify_catalog(
				$values['currency'],
				$values['status'],
				$values['search'],
				FixedPricingOperationController::FILTERED_SCOPE_CAP
			);

			if ( $classified['truncated'] ) {
				return array(
					'product_ids'  => array(),
					'names'        => array(),
					'excluded'     => 0,
					'preview_rate' => '',
					'error'        => sprintf(
						/* translators: %d: maximum number of products the admin screen can process in one operation */
						__( 'This filter matches more than %d products. Use `wp umc prices` on the command line for catalogs this size.', 'universal-multicurrency' ),
						FixedPricingOperationController::FILTERED_SCOPE_CAP
					),
				);
			}

			$ids = array_map(
				static fn( array $row ): int => (int) $row['product']->get_id(),
				$classified['rows']
			);
		}

		if ( array() === $ids ) {
			return array(
				'product_ids'  => array(),
				'names'        => array(),
				'excluded'     => 0,
				'preview_rate' => '',
				'error'        => __( 'No products were selected.', 'universal-multicurrency' ),
			);
		}

		$product_ids = array();
		$names       = array();
		$excluded    = 0;

		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) ) {
				++$excluded;
				continue;
			}

			$product = wc_get_product( $id );

			if ( $product instanceof \WC_Product ) {
				$product_ids[] = $id;
				$names[]       = $product->get_name();
			}
		}

		return array(
			'product_ids'  => $product_ids,
			'names'        => $names,
			'excluded'     => $excluded,
			'preview_rate' => self::seed_action( $values['action'] ) ? $this->registry_rate_preview( $values['currency'] ) : '',
			'error'        => null,
		);
	}

	/**
	 * Informational-only rate preview for the given currency.
	 *
	 * @param string $currency_code Target currency code.
	 */
	private function registry_rate_preview( string $currency_code ): string {
		$currency = $this->registry->get_currency( $currency_code );

		return null !== $currency ? __( 'resolved at confirm time', 'universal-multicurrency' ) : '';
	}

	/**
	 * Whether the requested action is "seed".
	 *
	 * @param string $action Requested action.
	 */
	private static function seed_action( string $action ): bool {
		return FixedPricingOperationController::ACTION_SEED === $action;
	}

	/**
	 * Human-readable label for a coverage status constant.
	 *
	 * @param string $status STATUS_* constant.
	 */
	private function status_label( string $status ): string {
		return match ( $status ) {
			FixedPriceCoverageReport::STATUS_FIXED                   => __( 'Fixed', 'universal-multicurrency' ),
			FixedPriceCoverageReport::STATUS_PARTIAL                 => __( 'Partial', 'universal-multicurrency' ),
			FixedPriceCoverageReport::STATUS_FX_FALLBACK              => __( 'FX fallback', 'universal-multicurrency' ),
			FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS => __( 'No priceable variations', 'universal-multicurrency' ),
			default                                                    => $status,
		};
	}

	/**
	 * Status badge variant for a coverage status constant.
	 *
	 * @param string $status STATUS_* constant.
	 */
	private function status_variant( string $status ): string {
		return match ( $status ) {
			FixedPriceCoverageReport::STATUS_FIXED                   => 'active',
			FixedPriceCoverageReport::STATUS_PARTIAL                 => 'warning',
			FixedPriceCoverageReport::STATUS_NO_PRICEABLE_VARIATIONS => 'missing',
			default                                                    => 'disabled',
		};
	}

	/**
	 * Normalizes Fixed Pricing screen request values.
	 *
	 * @return array<string, mixed>
	 */
	private function values_from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter/preview view; no write occurs here.
		$raw = wp_unslash( $_GET );

		$product_ids = isset( $raw['product_ids'] ) && is_array( $raw['product_ids'] )
			? array_map( 'absint', $raw['product_ids'] )
			: array();

		return array(
			'currency'    => isset( $raw['umc_fp_currency'] ) ? strtoupper( sanitize_text_field( (string) $raw['umc_fp_currency'] ) ) : '',
			'status'      => isset( $raw['umc_fp_status'] ) ? sanitize_key( (string) $raw['umc_fp_status'] ) : '',
			'search'      => isset( $raw['umc_fp_search'] ) ? sanitize_text_field( (string) $raw['umc_fp_search'] ) : '',
			'page'        => isset( $raw['umc_fp_page'] ) ? max( 1, absint( $raw['umc_fp_page'] ) ) : 1,
			'action'      => isset( $raw['umc_fp_action'] ) ? sanitize_key( (string) $raw['umc_fp_action'] ) : '',
			'scope'       => isset( $raw['umc_fp_scope'] ) ? sanitize_key( (string) $raw['umc_fp_scope'] ) : '',
			'product_ids' => $product_ids,
		);
	}
}
