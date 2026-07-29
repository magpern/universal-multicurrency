<?php
/**
 * Compatibility diagnostics settings field renderer.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilityScan;
use UMC\Compatibility\CompatibilityScanner;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\CompatibilitySummary;

/**
 * Renders the read-only Compatibility diagnostics center.
 */
final class CompatibilitySettingsField {

	/**
	 * Compatibility scanner.
	 *
	 * @var CompatibilityScanner
	 */
	private CompatibilityScanner $scanner;

	/**
	 * Creates the field renderer.
	 *
	 * @param CompatibilityScanner $scanner Compatibility scanner.
	 */
	public function __construct( CompatibilityScanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Renders the Compatibility center.
	 *
	 * @param array<string, mixed> $value Field definition.
	 */
	public function render( array $value ): void {
		unset( $value );

		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce settings capability.
			return;
		}

		$scan = $this->scanner->scan();

		echo '<tr class="umc-compatibility-row"><td colspan="2">';
		echo '<div class="umc-compatibility" data-umc-compatibility-root>';
		$this->render_summary( $scan );
		$this->render_section(
			__( 'Critical conflicts', 'universal-multicurrency' ),
			'conflicts',
			$scan->results_for_severity( CompatibilitySeverity::CONFLICT ),
			true
		);
		$this->render_section(
			__( 'Configuration', 'universal-multicurrency' ),
			'configuration',
			$this->non_pass_results( $scan->results_for_category( CompatibilityCategory::CONFIGURATION ) )
		);
		$this->render_section(
			__( 'Integrations', 'universal-multicurrency' ),
			'integrations',
			$scan->results_for_category( CompatibilityCategory::INTEGRATIONS )
		);
		$this->render_section(
			__( 'Theme', 'universal-multicurrency' ),
			'theme',
			$scan->results_for_category( CompatibilityCategory::THEME )
		);
		$this->render_section(
			__( 'Cache', 'universal-multicurrency' ),
			'cache',
			$scan->results_for_category( CompatibilityCategory::CACHE )
		);
		$this->render_section(
			__( 'Environment', 'universal-multicurrency' ),
			'environment',
			$scan->results_for_category( CompatibilityCategory::ENVIRONMENT )
		);
		$this->render_report( $scan );
		echo '</div>';
		echo '</td></tr>';
	}

	/**
	 * Renders the summary hero card.
	 *
	 * @param CompatibilityScan $scan Compatibility scan.
	 */
	private function render_summary( CompatibilityScan $scan ): void {
		$summary = $scan->summary();
		$status  = $this->overall_label( $summary );

		printf(
			'<section class="umc-compat-card umc-compat-card--summary umc-compat-card--%1$s" aria-labelledby="umc-compat-summary-title">',
			esc_attr( $summary->overall() )
		);
		echo '<h3 id="umc-compat-summary-title" class="umc-compat-card__title">';
		echo '<span class="umc-compat-status-icon" aria-hidden="true"></span>';
		echo esc_html( $status );
		echo '</h3>';
		echo '<p class="umc-compat-card__intro">' . esc_html__( 'Local compatibility checks for this store configuration.', 'universal-multicurrency' ) . '</p>';
		echo '<ul class="umc-compat-counts">';
		$this->render_count_chip( __( 'Passed', 'universal-multicurrency' ), $summary->passed(), 'pass' );
		$this->render_count_chip( __( 'Informational', 'universal-multicurrency' ), $summary->informational(), 'info' );
		$this->render_count_chip( __( 'Warnings', 'universal-multicurrency' ), $summary->warnings(), 'warning' );
		$this->render_count_chip( __( 'Conflicts', 'universal-multicurrency' ), $summary->conflicts(), 'conflict' );
		echo '</ul>';
		echo '</section>';
	}

	/**
	 * Renders one count chip.
	 *
	 * @param string $label Count label.
	 * @param int    $count Count value.
	 * @param string $type  Chip type.
	 */
	private function render_count_chip( string $label, int $count, string $type ): void {
		printf(
			'<li class="umc-compat-count umc-compat-count--%1$s"><span class="umc-compat-count__value">%2$d</span><span class="umc-compat-count__label">%3$s</span></li>',
			esc_attr( $type ),
			$count,
			esc_html( $label )
		);
	}

	/**
	 * Renders one results section.
	 *
	 * @param string                       $title   Section title.
	 * @param string                       $slug    Section slug.
	 * @param array<int, CompatibilityResult> $results Section results.
	 * @param bool                         $hide_empty Whether to hide empty sections.
	 */
	private function render_section( string $title, string $slug, array $results, bool $hide_empty = false ): void {
		if ( $hide_empty && array() === $results ) {
			return;
		}

		printf(
			'<section class="umc-compat-card umc-compat-card--section umc-compat-card--%1$s" aria-labelledby="umc-compat-section-%1$s">',
			esc_attr( $slug )
		);
		printf( '<h3 id="umc-compat-section-%s" class="umc-compat-card__title">%s</h3>', esc_attr( $slug ), esc_html( $title ) );

		if ( array() === $results ) {
			echo '<p class="umc-compat-empty">' . esc_html__( 'No items to show in this section.', 'universal-multicurrency' ) . '</p>';
		} else {
			echo '<div class="umc-compat-results">';
			foreach ( $results as $result ) {
				$this->render_result_card( $result );
			}
			echo '</div>';
		}

		echo '</section>';
	}

	/**
	 * Renders one result card.
	 *
	 * @param CompatibilityResult $result Compatibility result.
	 */
	private function render_result_card( CompatibilityResult $result ): void {
		$panel_id  = 'umc-compat-evidence-' . sanitize_html_class( $result->id() );
		$severity  = $result->severity();
		$badge     = $this->severity_label( $severity );

		printf(
			'<article class="umc-compat-result umc-compat-result--%1$s">',
			esc_attr( $severity )
		);
		echo '<header class="umc-compat-result__header">';
		printf(
			'<span class="umc-compat-badge umc-compat-badge--%1$s"><span class="umc-compat-badge__icon" aria-hidden="true"></span><span class="umc-compat-badge__text">%2$s</span></span>',
			esc_attr( $severity ),
			esc_html( $badge )
		);
		echo '<h4 class="umc-compat-result__title">' . esc_html( $result->title() ) . '</h4>';
		echo '</header>';
		echo '<p class="umc-compat-result__summary">' . esc_html( $result->summary() ) . '</p>';

		if ( array() !== $result->details() ) {
			echo '<ul class="umc-compat-result__details">';
			foreach ( $result->details() as $detail ) {
				echo '<li>' . esc_html( $detail ) . '</li>';
			}
			echo '</ul>';
		}

		foreach ( $result->actions() as $action ) {
			if ( ! $action->has_url() ) {
				continue;
			}

			printf(
				'<p class="umc-compat-result__action"><a class="button button-secondary" href="%1$s">%2$s</a></p>',
				esc_url( $action->url() ),
				esc_html( $action->label() )
			);
		}

		if ( $result->has_evidence() ) {
			printf(
				'<button type="button" class="button-link umc-compat-evidence-toggle" aria-expanded="false" aria-controls="%1$s" data-umc-compat-evidence-toggle>%2$s</button>',
				esc_attr( $panel_id ),
				esc_html__( 'Show technical evidence', 'universal-multicurrency' )
			);
			printf( '<div class="umc-compat-evidence" id="%1$s" hidden>', esc_attr( $panel_id ) );
			if ( array() !== $result->evidence() ) {
				echo '<dl class="umc-compat-evidence__list">';
				foreach ( $result->evidence() as $key => $value ) {
					echo '<dt>' . esc_html( (string) $key ) . '</dt>';
					echo '<dd>' . esc_html( (string) $value ) . '</dd>';
				}
				echo '</dl>';
			}
			echo '</div>';
		}

		echo '</article>';
	}

	/**
	 * Renders the support report block.
	 *
	 * @param CompatibilityScan $scan Compatibility scan.
	 */
	private function render_report( CompatibilityScan $scan ): void {
		echo '<section class="umc-compat-card umc-compat-card--section umc-compat-card--report" aria-labelledby="umc-compat-section-report">';
		echo '<h3 id="umc-compat-section-report" class="umc-compat-card__title">' . esc_html__( 'Support report', 'universal-multicurrency' ) . '</h3>';
		echo '<p class="umc-compat-card__intro">' . esc_html__( 'Copy this redacted report when contacting support.', 'universal-multicurrency' ) . '</p>';
		printf(
			'<label class="screen-reader-text" for="umc-compat-report-text">%1$s</label>',
			esc_html__( 'Support environment report', 'universal-multicurrency' )
		);
		printf(
			'<textarea id="umc-compat-report-text" class="umc-compat-report" readonly rows="14" data-umc-compat-report>%s</textarea>',
			esc_textarea( $scan->report() )
		);
		printf(
			'<p class="umc-compat-report-actions"><button type="button" class="button button-secondary" data-umc-compat-copy-report>%1$s</button><span class="umc-compat-report-status" role="status" aria-live="polite" data-umc-compat-copy-status></span></p>',
			esc_html__( 'Copy report', 'universal-multicurrency' )
		);
		echo '</section>';
	}

	/**
	 * Human-readable overall status label.
	 *
	 * @param CompatibilitySummary $summary Summary aggregate.
	 */
	private function overall_label( CompatibilitySummary $summary ): string {
		return match ( $summary->overall() ) {
			CompatibilitySummary::OVERALL_CONFLICT => __( 'Conflict detected', 'universal-multicurrency' ),
			CompatibilitySummary::OVERALL_CONFIG_INCOMPLETE => __( 'Configuration incomplete', 'universal-multicurrency' ),
			CompatibilitySummary::OVERALL_ATTENTION => __( 'Attention recommended', 'universal-multicurrency' ),
			CompatibilitySummary::OVERALL_UNAVAILABLE => __( 'Some checks unavailable', 'universal-multicurrency' ),
			default => __( 'All checks passed', 'universal-multicurrency' ),
		};
	}

	/**
	 * Human-readable severity label.
	 *
	 * @param string $severity Severity slug.
	 */
	private function severity_label( string $severity ): string {
		return match ( $severity ) {
			CompatibilitySeverity::CONFLICT => __( 'Conflict', 'universal-multicurrency' ),
			CompatibilitySeverity::WARNING => __( 'Warning', 'universal-multicurrency' ),
			CompatibilitySeverity::UNAVAILABLE => __( 'Unavailable', 'universal-multicurrency' ),
			CompatibilitySeverity::PASS => __( 'Passed', 'universal-multicurrency' ),
			default => __( 'Information', 'universal-multicurrency' ),
		};
	}

	/**
	 * Filters out pass-level configuration results for the main card list.
	 *
	 * @param array<int, CompatibilityResult> $results Section results.
	 * @return array<int, CompatibilityResult>
	 */
	private function non_pass_results( array $results ): array {
		return array_values(
			array_filter(
				$results,
				static function ( CompatibilityResult $result ): bool {
					return CompatibilitySeverity::PASS !== $result->severity();
				}
			)
		);
	}
}
