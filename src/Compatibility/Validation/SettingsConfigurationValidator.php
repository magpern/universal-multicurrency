<?php
/**
 * Read-only settings configuration validation for Compatibility diagnostics.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Validation;

use UMC\Compatibility\CompatibilityAction;
use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Currency;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\Currency\WooCommerceCurrencyProvider;
use UMC\Display\SwitcherSettings;
use UMC\Display\SwitcherSettingsRepository;
use UMC\Display\SwitcherShortcodeScanner;
use UMC\Rates\RateHealthService;
use UMC\Rates\RateStatusEvaluator;
use UMC\Settings;

/**
 * Validates persisted configuration without running the save pipeline.
 */
final class SettingsConfigurationValidator {

	/**
	 * Currency metadata source for default symbol resolution.
	 *
	 * @var CurrencyMetadataProvider
	 */
	private CurrencyMetadataProvider $metadata;

	/**
	 * Creates the validator.
	 *
	 * @param CurrencyMetadataProvider|null $metadata Optional metadata provider.
	 */
	public function __construct( ?CurrencyMetadataProvider $metadata = null ) {
		$this->metadata = $metadata ?? new WooCommerceCurrencyProvider();
	}

	/**
	 * Validates current settings and returns compatibility results.
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function validate( CompatibilityInventory $inventory ): array {
		$settings   = $inventory->settings();
		$data       = $settings->get();
		$results    = array();
		$base       = strtoupper( $inventory->base()->code() );
		$currencies = $settings->get_currencies();

		$enabled_codes = array( $base );
		foreach ( $currencies as $code => $config ) {
			if ( ! empty( $config['enabled'] ) ) {
				$enabled_codes[] = strtoupper( (string) $code );
			}
		}
		$enabled_codes = array_values( array_unique( $enabled_codes ) );

		if ( 1 === count( $enabled_codes ) && in_array( $base, $enabled_codes, true ) ) {
			$results[] = new CompatibilityResult(
				'config.no_enabled_currencies',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'No enabled currencies', 'universal-multicurrency' ),
				__( 'Universal Multicurrency has no enabled currencies configured.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC,
				array(),
				array(
					new CompatibilityAction(
						__( 'Review currencies', 'universal-multicurrency' ),
						admin_url( 'admin.php?page=wc-settings&tab=umc&section=currencies' )
					),
				)
			);
		}

		foreach ( $currencies as $code => $config ) {
			if ( empty( $config['enabled'] ) ) {
				continue;
			}

			$upper = strtoupper( (string) $code );

			if ( ! $this->has_usable_symbol( $upper, $config, $inventory->base() ) ) {
				$results[] = new CompatibilityResult(
					'config.missing_symbol.' . strtolower( $upper ),
					CompatibilityCategory::CONFIGURATION,
					CompatibilitySeverity::WARNING,
					sprintf(
						/* translators: %s: currency code */
						__( 'Missing symbol for %s', 'universal-multicurrency' ),
						$upper
					),
					sprintf(
						/* translators: %s: currency code */
						__( 'Enabled currency %s has no symbol configured.', 'universal-multicurrency' ),
						$upper
					),
					CompatibilityDeterminism::DETERMINISTIC
				);
			}

			if ( $upper === $base ) {
				continue;
			}

			$mode = $settings->get_effective_rate_mode( $upper );
			if ( Settings::RATE_MODE_MANUAL === $mode && '' === Settings::normalize_rate( $config['manual_rate'] ?? '' ) ) {
				$results[] = new CompatibilityResult(
					'config.missing_manual_rate.' . strtolower( $upper ),
					CompatibilityCategory::CONFIGURATION,
					CompatibilitySeverity::WARNING,
					sprintf(
						/* translators: %s: currency code */
						__( 'Missing exchange rate for %s', 'universal-multicurrency' ),
						$upper
					),
					sprintf(
						/* translators: %s: currency code */
						__( 'Enabled currency %s has no usable manual exchange rate.', 'universal-multicurrency' ),
						$upper
					),
					CompatibilityDeterminism::DETERMINISTIC,
					array(),
					array(
						new CompatibilityAction(
							__( 'Review exchange rates', 'universal-multicurrency' ),
							admin_url( 'admin.php?page=wc-settings&tab=umc&section=exchange_rates' )
						),
					)
				);
			}
		}

		$schema = (int) ( $data['schema_version'] ?? 0 );
		if ( $schema > 0 && $schema < Settings::SCHEMA_VERSION ) {
			$results[] = new CompatibilityResult(
				'config.schema_partial',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'Settings schema may be partially migrated', 'universal-multicurrency' ),
				sprintf(
					/* translators: 1: stored schema version, 2: current schema version */
					__( 'Stored schema version %1$d is older than the current schema %2$d.', 'universal-multicurrency' ),
					$schema,
					Settings::SCHEMA_VERSION
				),
				CompatibilityDeterminism::DETERMINISTIC,
				array(
					'stored_schema'  => (string) $schema,
					'current_schema' => (string) Settings::SCHEMA_VERSION,
				)
			);
		}

		$evaluator = new RateStatusEvaluator( $settings, $inventory->rate_store() );
		$health    = ( new RateHealthService( $settings, $inventory->rate_store(), $evaluator ) )->report();

		if ( $health->scheduler_missing() ) {
			$results[] = new CompatibilityResult(
				'config.automatic_not_scheduled',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::CONFLICT,
				__( 'Automatic rate updates are not scheduled', 'universal-multicurrency' ),
				__( 'Automatic currencies require a refresh schedule, but no recurring update is queued.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC,
				array(),
				array(
					new CompatibilityAction(
						__( 'Review exchange rates', 'universal-multicurrency' ),
						admin_url( 'admin.php?page=wc-settings&tab=umc&section=exchange_rates' )
					),
				)
			);
		}

		if ( $health->unavailable_count() > 0 ) {
			$results[] = new CompatibilityResult(
				'config.rate_unavailable',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::CONFLICT,
				__( 'Enabled automatic currencies are missing usable rates', 'universal-multicurrency' ),
				sprintf(
					/* translators: %d: number of unavailable automatic currencies */
					_n(
						'%d enabled automatic currency has no usable exchange rate.',
						'%d enabled automatic currencies have no usable exchange rate.',
						$health->unavailable_count(),
						'universal-multicurrency'
					),
					$health->unavailable_count()
				),
				CompatibilityDeterminism::DETERMINISTIC,
				array(),
				array(
					new CompatibilityAction(
						__( 'Review exchange rates', 'universal-multicurrency' ),
						admin_url( 'admin.php?page=wc-settings&tab=umc&section=exchange_rates' )
					),
				)
			);
		} elseif ( $health->stale_count() > 0 ) {
			$results[] = new CompatibilityResult(
				'config.rate_stale',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'Stale automatic exchange rates detected', 'universal-multicurrency' ),
				sprintf(
					/* translators: %d: number of currencies */
					_n(
						'%d automatic currency has a stale exchange rate.',
						'%d automatic currencies have stale exchange rates.',
						$health->stale_count(),
						'universal-multicurrency'
					),
					$health->stale_count()
				),
				CompatibilityDeterminism::HEURISTIC
			);
		}

		$display       = ( new SwitcherSettingsRepository( $settings ) )->get();
		$enabled_count = count(
			array_filter(
				$currencies,
				static function ( array $config ): bool {
					return ! empty( $config['enabled'] );
				}
			)
		);

		if ( $display->is_enabled() && $enabled_count < 2 ) {
			$results[] = new CompatibilityResult(
				'config.switcher_no_usable_currencies',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'Switcher enabled without multiple currencies', 'universal-multicurrency' ),
				__( 'The storefront switcher is enabled but fewer than two currencies are enabled.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		$visibility = $display->visibility();
		if ( $display->is_enabled() && empty( $visibility['desktop'] ) && empty( $visibility['mobile'] ) ) {
			$results[] = new CompatibilityResult(
				'config.switcher_no_visibility',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'Switcher has no device visibility', 'universal-multicurrency' ),
				__( 'The storefront switcher is enabled but no desktop or mobile visibility option is active.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		$scanner = new SwitcherShortcodeScanner();
		if ( $display->is_enabled() && SwitcherSettings::PLACEMENT_MANUAL !== $display->placement() && $scanner->has_shortcode_on_key_surface() ) {
			$results[] = new CompatibilityResult(
				'config.duplicate_shortcode',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::WARNING,
				__( 'Manual switcher shortcode detected with automatic placement', 'universal-multicurrency' ),
				__( 'A switcher shortcode appears on a key storefront page while automatic placement is also enabled.', 'universal-multicurrency' ),
				CompatibilityDeterminism::HEURISTIC
			);
		}

		if ( array() === $results ) {
			$results[] = new CompatibilityResult(
				'config.ok',
				CompatibilityCategory::CONFIGURATION,
				CompatibilitySeverity::PASS,
				__( 'Configuration checks passed', 'universal-multicurrency' ),
				__( 'Universal Multicurrency settings look usable.', 'universal-multicurrency' ),
				CompatibilityDeterminism::DETERMINISTIC
			);
		}

		return $results;
	}

	/**
	 * Whether an enabled currency has a usable symbol from override or metadata.
	 *
	 * @param string               $code   Currency code.
	 * @param array<string, mixed> $config Currency configuration row.
	 * @param Currency             $base   Store base currency.
	 */
	private function has_usable_symbol( string $code, array $config, Currency $base ): bool {
		$override = trim( (string) ( $config['symbol'] ?? '' ) );

		if ( '' !== $override ) {
			return true;
		}

		if ( strtoupper( $code ) === $base->code() && '' !== trim( $base->symbol() ) ) {
			return true;
		}

		$metadata = $this->metadata->get( $code );

		return null !== $metadata && '' !== trim( $metadata->symbol() );
	}
}
