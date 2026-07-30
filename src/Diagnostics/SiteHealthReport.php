<?php
/**
 * Site Health direct tests and debug information for diagnostics.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

use UMC\Geo\GeoDetectionSettings;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateStatusEvaluator;
use UMC\Rates\RateUpdateState;
use UMC\Rates\Scheduler;
use UMC\Settings;

/**
 * Passive reporting only: reuses {@see ConflictDetector} findings and
 * {@see VersionPolicy} classifications without persisting state, altering
 * monetary behaviour, or duplicating detection logic.
 */
final class SiteHealthReport {

	public const SECTION = 'universal-multicurrency';

	public const TEST_CONFLICTS = 'umc_conflicts';

	public const TEST_ENVIRONMENT = 'umc_environment';

	public const TEST_RATE_HEALTH = 'umc_rate_health';

	public const TEST_GEO_CONFIGURATION = 'umc_geo_configuration';

	/**
	 * Tested-up-to ceilings for PHP and WordPress, mirroring
	 * `docs/COMPATIBILITY.md` § Machine-readable summary. WooCommerce's
	 * ceiling is read from the plugin header at runtime.
	 */
	private const TESTED_PHP = '8.4';

	private const TESTED_WP = '7.0';

	/**
	 * Announced future support floors. Empty until
	 * `docs/COMPATIBILITY.md § Planned floor changes` records one.
	 *
	 * @var array<string, string> axis => announced floor (bare X.Y)
	 */
	private const ANNOUNCED_FLOORS = array();

	/**
	 * Memoized conflict detector shared with other advisory surfaces.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $detector;

	/**
	 * Pure version classifier for environment evaluation.
	 *
	 * @var VersionPolicy
	 */
	private VersionPolicy $policy;

	/**
	 * Merchant settings store for rate health checks.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings;

	/**
	 * Operational rate store for rate health checks.
	 *
	 * @var ExchangeRateStore|null
	 */
	private ?ExchangeRateStore $rate_store;

	/**
	 * Binds the report to a shared conflict detector.
	 *
	 * @param ConflictDetector       $detector   Memoized conflict detector.
	 * @param VersionPolicy|null     $policy     Optional policy for tests.
	 * @param Settings|null          $settings   Optional settings for rate health.
	 * @param ExchangeRateStore|null $rate_store Optional rate store for rate health.
	 */
	public function __construct(
		ConflictDetector $detector,
		?VersionPolicy $policy = null,
		?Settings $settings = null,
		?ExchangeRateStore $rate_store = null
	) {
		$this->detector   = $detector;
		$this->policy     = $policy ?? new VersionPolicy();
		$this->settings   = $settings;
		$this->rate_store = $rate_store;
	}

	/**
	 * Registers Site Health filters.
	 */
	public function register(): void {
		\add_filter( 'site_status_tests', array( $this, 'tests' ), 10 );
		\add_filter( 'debug_information', array( $this, 'debug' ), 10 );
	}

	/**
	 * Adds direct Site Health tests when the viewer may activate plugins.
	 *
	 * @param array<string, mixed> $tests Existing tests grouped by type.
	 *
	 * @return array<string, mixed>
	 */
	public function tests( array $tests ): array {
		if ( ! \current_user_can( 'activate_plugins' ) ) {
			return $tests;
		}

		$tests['direct'][ self::TEST_CONFLICTS ]         = array(
			'label' => \__( 'Currency switcher conflicts', 'universal-multicurrency' ),
			'test'  => array( $this, 'run_conflict_test' ),
		);
		$tests['direct'][ self::TEST_ENVIRONMENT ]       = array(
			'label' => \__( 'Universal Multicurrency environment', 'universal-multicurrency' ),
			'test'  => array( $this, 'run_environment_test' ),
		);
		$tests['direct'][ self::TEST_RATE_HEALTH ]       = array(
			'label' => \__( 'Exchange rate provider health', 'universal-multicurrency' ),
			'test'  => array( $this, 'run_rate_health_test' ),
		);
		$tests['direct'][ self::TEST_GEO_CONFIGURATION ] = array(
			'label' => \__( 'Geo Detection configuration', 'universal-multicurrency' ),
			'test'  => array( $this, 'run_geo_configuration_test' ),
		);

		return $tests;
	}

	/**
	 * Adds the debug section when the viewer may activate plugins.
	 *
	 * @param array<string, mixed> $info Existing debug sections.
	 *
	 * @return array<string, mixed>
	 */
	public function debug( array $info ): array {
		if ( ! \current_user_can( 'activate_plugins' ) ) {
			return $info;
		}

		$info[ self::SECTION ] = $this->debug_section();

		return $info;
	}

	/**
	 * Executes the conflict Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public function run_conflict_test(): array {
		return self::conflict_test_result( $this->detector->findings() );
	}

	/**
	 * Executes the environment Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public function run_environment_test(): array {
		$declared = self::declared_versions();
		$running  = self::running_versions();

		return self::environment_test_result(
			self::evaluate_environment_axes( $this->policy, $declared, $running ),
			self::is_hpos_enabled(),
			self::is_below_announced_floor( $running, self::ANNOUNCED_FLOORS )
		);
	}

	/**
	 * Executes the exchange-rate health Site Health test (last-known state only).
	 *
	 * @return array<string, mixed>
	 */
	public function run_rate_health_test(): array {
		if ( null === $this->settings || null === $this->rate_store ) {
			return self::format_test_result(
				self::TEST_RATE_HEALTH,
				\__( 'Exchange rate health unavailable', 'universal-multicurrency' ),
				'good',
				'<p>' . \esc_html__( 'Rate health diagnostics were not initialized.', 'universal-multicurrency' ) . '</p>'
			);
		}

		$evaluator = new RateStatusEvaluator( $this->settings, $this->rate_store );
		$stale     = 0;
		$failed    = 0;

		foreach ( array_keys( $this->settings->get_currencies() ) as $code ) {
			$label = $evaluator->label_for_currency( $code );

			if ( RateStatusEvaluator::LABEL_STALE === $label ) {
				++$stale;
			}

			if ( RateStatusEvaluator::LABEL_FAILED === $label ) {
				++$failed;
			}
		}

		$scheduled = function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( Scheduler::HOOK );
		$config    = $this->rate_store->get_configuration();

		if ( $config->is_automatic_enabled() && ! $scheduled ) {
			return self::format_test_result(
				self::TEST_RATE_HEALTH,
				\__( 'Automatic rate updates are not scheduled', 'universal-multicurrency' ),
				'critical',
				'<p>' . \esc_html__( 'Automatic mode is enabled but no recurring update is scheduled.', 'universal-multicurrency' ) . '</p>'
			);
		}

		if ( $failed > 0 ) {
			return self::format_test_result(
				self::TEST_RATE_HEALTH,
				\__( 'Recent exchange-rate fetch failures detected', 'universal-multicurrency' ),
				'critical',
				'<p>' . \esc_html(
					sprintf(
						/* translators: %d: number of currencies with failed fetches */
						_n(
							'%d automatic currency has a failed fetch on record.',
							'%d automatic currencies have failed fetches on record.',
							$failed,
							'universal-multicurrency'
						),
						$failed
					)
				) . '</p>'
			);
		}

		if ( $stale >= 3 ) {
			return self::format_test_result(
				self::TEST_RATE_HEALTH,
				\__( 'Multiple automatic exchange rates are stale', 'universal-multicurrency' ),
				'critical',
				'<p>' . \esc_html(
					sprintf(
						/* translators: %d: stale currency count */
						__( '%d automatic currencies exceed the configured maximum age.', 'universal-multicurrency' ),
						$stale
					)
				) . '</p>'
			);
		}

		if ( $stale > 0 ) {
			return self::format_test_result(
				self::TEST_RATE_HEALTH,
				\__( 'Some automatic exchange rates are stale', 'universal-multicurrency' ),
				'recommended',
				'<p>' . \esc_html__(
					'One or more automatic currencies exceed the configured maximum age. Conversion still uses the last known rate.',
					'universal-multicurrency'
				) . '</p>'
			);
		}

		return self::format_test_result(
			self::TEST_RATE_HEALTH,
			\__( 'Exchange rate provider health looks good', 'universal-multicurrency' ),
			'good',
			'<p>' . \esc_html__( 'No stale or failed automatic rates were recorded in the last-known operational state.', 'universal-multicurrency' ) . '</p>'
		);
	}

	/**
	 * Executes the Geo Detection configuration Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public function run_geo_configuration_test(): array {
		if ( null === $this->settings ) {
			return self::format_test_result(
				self::TEST_GEO_CONFIGURATION,
				\__( 'Geo Detection status unavailable', 'universal-multicurrency' ),
				'good',
				'<p>' . \esc_html__( 'Geo diagnostics were not initialized.', 'universal-multicurrency' ) . '</p>'
			);
		}

		$geo = GeoDetectionSettings::from_array( $this->settings->get()['geo'] ?? array() );

		if ( ! $geo->is_enabled() ) {
			return self::format_test_result(
				self::TEST_GEO_CONFIGURATION,
				\__( 'Geo Detection is disabled', 'universal-multicurrency' ),
				'good',
				'<p>' . \esc_html__( 'Automatic country-based currency routing is not active.', 'universal-multicurrency' ) . '</p>'
			);
		}

		if ( array() === $geo->rules() ) {
			return self::format_test_result(
				self::TEST_GEO_CONFIGURATION,
				\__( 'Geo Detection is enabled without routing rules', 'universal-multicurrency' ),
				'recommended',
				'<p>' . \esc_html__( 'Add geographic routing rules or disable Geo Detection.', 'universal-multicurrency' ) . '</p>'
			);
		}

		if ( ! $geo->allow_wc_geolocation_fallback() && ! function_exists( 'universal_geo_get_country_code' ) ) {
			return self::format_test_result(
				self::TEST_GEO_CONFIGURATION,
				\__( 'Geo Detection has no country provider', 'universal-multicurrency' ),
				'critical',
				'<p>' . \esc_html__( 'Universal Geo Context is unavailable and WooCommerce geolocation fallback is disabled.', 'universal-multicurrency' ) . '</p>'
			);
		}

		return self::format_test_result(
			self::TEST_GEO_CONFIGURATION,
			\__( 'Geo Detection configuration looks good', 'universal-multicurrency' ),
			'good',
			'<p>' . \esc_html__( 'Geo Detection is enabled with routing rules configured.', 'universal-multicurrency' ) . '</p>'
		);
	}

	/**
	 * Maps the highest finding confidence to a Site Health status string.
	 *
	 * @param string $confidence One of {@see Confidence::ALL}.
	 */
	public static function conflict_status_for_confidence( string $confidence ): string {
		if ( Confidence::HIGH === $confidence ) {
			return 'critical';
		}

		if ( Confidence::MEDIUM === $confidence ) {
			return 'recommended';
		}

		return 'good';
	}

	/**
	 * Builds the conflict test result from memoized findings.
	 *
	 * @param array<int, Finding> $findings Scored findings from the detector.
	 *
	 * @return array<string, mixed>
	 */
	public static function conflict_test_result( array $findings ): array {
		if ( array() === $findings ) {
			return self::format_test_result(
				self::TEST_CONFLICTS,
				\__( 'No conflicting currency switchers detected', 'universal-multicurrency' ),
				'good',
				'<p>' . \esc_html__(
					'Universal Multicurrency did not detect another currency switcher on this site.',
					'universal-multicurrency'
				) . '</p>'
			);
		}

		$highest = ConflictNotice::highest_confidence( $findings );
		$status  = self::conflict_status_for_confidence( $highest );
		$labels  = array_map(
			static function ( Finding $finding ): string {
				return $finding->label();
			},
			$findings
		);

		$description = '<p>' . sprintf(
			/* translators: %s: comma-separated list of detected switcher labels */
			_n(
				'Universal Multicurrency detected a conflicting currency switcher: %s.',
				'Universal Multicurrency detected conflicting currency switchers: %s.',
				count( $labels ),
				'universal-multicurrency'
			),
			self::format_label_list( $labels )
		) . '</p>';

		if ( Confidence::LOW === $highest ) {
			$description .= '<p>' . \esc_html__(
				'The evidence is weak and may be a false positive. Review the Multicurrency settings tab if prices look wrong.',
				'universal-multicurrency'
			) . '</p>';
		} elseif ( Confidence::MEDIUM === $highest ) {
			$description .= '<p>' . \esc_html__(
				'Deactivate the other switcher to avoid multiplied exchange rates. Universal Multicurrency will not deactivate it for you.',
				'universal-multicurrency'
			) . '</p>';
		} else {
			$description .= '<p>' . \esc_html__(
				'Running two currency switchers can multiply exchange rates and corrupt cart and order totals. Deactivate the other switcher immediately.',
				'universal-multicurrency'
			) . '</p>';
		}

		$result_label = Confidence::HIGH === $highest
			? \__( 'Conflicting currency switcher detected', 'universal-multicurrency' )
			: ( Confidence::MEDIUM === $highest
				? \__( 'Possible currency switcher conflict', 'universal-multicurrency' )
				: \__( 'Weak currency switcher signal detected', 'universal-multicurrency' ) );

		return self::format_test_result(
			self::TEST_CONFLICTS,
			$result_label,
			$status,
			$description
		);
	}

	/**
	 * Classifies each environment axis with {@see VersionPolicy}.
	 *
	 * @param VersionPolicy                                                 $policy   Pure version classifier.
	 * @param array{php: string, wp: string, wc: string, wc_tested: string} $declared Declared floors and WC tested ceiling.
	 * @param array{php: string, wp: string, wc: string}                    $running  Host-reported versions.
	 *
	 * @return array{php: string, wp: string, wc: string}
	 */
	public static function evaluate_environment_axes( VersionPolicy $policy, array $declared, array $running ): array {
		return array(
			'php' => $policy->evaluate( $running['php'], $declared['php'], self::TESTED_PHP ),
			'wp'  => $policy->evaluate( $running['wp'], $declared['wp'], self::TESTED_WP ),
			'wc'  => $policy->evaluate( $running['wc'], $declared['wc'], $declared['wc_tested'] ),
		);
	}

	/**
	 * Derives the environment test status from axis classifications and HPOS state.
	 *
	 * @param array{php: string, wp: string, wc: string} $axes Axis classifications.
	 * @param bool                                       $hpos_enabled Whether HPOS is enabled.
	 * @param bool                                       $below_announced_floor Whether the store is below an announced future floor.
	 */
	public static function environment_status( array $axes, bool $hpos_enabled, bool $below_announced_floor ): string {
		foreach ( $axes as $status ) {
			if ( VersionPolicy::BELOW_FLOOR === $status ) {
				return 'critical';
			}
		}

		foreach ( $axes as $status ) {
			if ( VersionPolicy::ABOVE_TESTED === $status || VersionPolicy::UNPARSEABLE === $status ) {
				return 'recommended';
			}
		}

		if ( $below_announced_floor || ! $hpos_enabled ) {
			return 'recommended';
		}

		return 'good';
	}

	/**
	 * Builds the environment test result.
	 *
	 * @param array{php: string, wp: string, wc: string} $axes Axis classifications.
	 * @param bool                                       $hpos_enabled Whether HPOS is enabled.
	 * @param bool                                       $below_announced_floor Whether the store is below an announced future floor.
	 *
	 * @return array<string, mixed>
	 */
	public static function environment_test_result( array $axes, bool $hpos_enabled, bool $below_announced_floor ): array {
		$status = self::environment_status( $axes, $hpos_enabled, $below_announced_floor );

		if ( 'good' === $status ) {
			return self::format_test_result(
				self::TEST_ENVIRONMENT,
				\__( 'Environment meets Universal Multicurrency requirements', 'universal-multicurrency' ),
				'good',
				'<p>' . \esc_html__(
					'PHP, WordPress, WooCommerce, and High-Performance Order Storage meet the supported range for this plugin.',
					'universal-multicurrency'
				) . '</p>'
			);
		}

		$issues = self::environment_issue_sentences( $axes, $hpos_enabled, $below_announced_floor );

		$description = '';
		foreach ( $issues as $issue ) {
			$description .= '<p>' . \esc_html( $issue ) . '</p>';
		}

		$result_label = 'critical' === $status
			? \__( 'Environment is below the supported minimum', 'universal-multicurrency' )
			: \__( 'Environment should be reviewed', 'universal-multicurrency' );

		return self::format_test_result(
			self::TEST_ENVIRONMENT,
			$result_label,
			$status,
			$description
		);
	}

	/**
	 * Serialises detected findings for the debug section.
	 *
	 * Only findings that cleared the reporting threshold are included — never
	 * the full manifest or unmatched needles.
	 *
	 * @param array<int, Finding> $findings Scored findings from the detector.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function conflicts_detected_rows( array $findings ): array {
		$rows = array();

		foreach ( $findings as $finding ) {
			$rows[] = array(
				'id'         => $finding->id(),
				'label'      => $finding->label(),
				'confidence' => $finding->confidence(),
				'matched'    => array_map(
					static function ( Signature $signature ): string {
						return $signature->key();
					},
					$finding->matched()
				),
			);
		}

		return $rows;
	}

	/**
	 * Counts configured currencies without exposing rate values.
	 *
	 * @param array<string, array<string, mixed>> $currencies Sanitized currency rows.
	 *
	 * @return array{configured: int, enabled_and_rated: int}
	 */
	public static function currency_counts( array $currencies ): array {
		$configured        = count( $currencies );
		$enabled_and_rated = 0;

		foreach ( $currencies as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$enabled = ! isset( $config['enabled'] ) || (bool) $config['enabled'];
			$manual  = isset( $config['manual_rate'] ) ? trim( (string) $config['manual_rate'] ) : '';
			if ( '' === $manual && isset( $config['rate'] ) ) {
				$manual = trim( (string) $config['rate'] );
			}
			$provider = isset( $config['provider_rate'] ) ? trim( (string) $config['provider_rate'] ) : '';
			$has_rate = '' !== $manual || '' !== $provider;

			if ( $enabled && $has_rate ) {
				++$enabled_and_rated;
			}
		}

		return array(
			'configured'        => $configured,
			'enabled_and_rated' => $enabled_and_rated,
		);
	}

	/**
	 * Reads declared version floors from the plugin header.
	 *
	 * @return array{php: string, wp: string, wc: string, wc_tested: string}
	 */
	public static function declared_versions(): array {
		$defaults = array(
			'php'       => '8.1',
			'wp'        => '6.5',
			'wc'        => '8.2',
			'wc_tested' => '10.9',
		);

		$header = \get_file_data(
			UMC_PLUGIN_FILE,
			array(
				'requires_php' => 'Requires PHP',
				'requires_wp'  => 'Requires at least',
				'requires_wc'  => 'WC requires at least',
				'tested_wc'    => 'WC tested up to',
			)
		);

		return array(
			'php'       => '' !== (string) ( $header['requires_php'] ?? '' ) ? (string) $header['requires_php'] : $defaults['php'],
			'wp'        => '' !== (string) ( $header['requires_wp'] ?? '' ) ? (string) $header['requires_wp'] : $defaults['wp'],
			'wc'        => '' !== (string) ( $header['requires_wc'] ?? '' ) ? (string) $header['requires_wc'] : $defaults['wc'],
			'wc_tested' => '' !== (string) ( $header['tested_wc'] ?? '' ) ? (string) $header['tested_wc'] : $defaults['wc_tested'],
		);
	}

	/**
	 * Reads host-reported runtime versions.
	 *
	 * @return array{php: string, wp: string, wc: string}
	 */
	public static function running_versions(): array {
		global $wp_version;

		return array(
			'php' => PHP_VERSION,
			'wp'  => isset( $wp_version ) ? (string) $wp_version : '',
			'wc'  => (string) WC_VERSION,
		);
	}

	/**
	 * Whether WooCommerce HPOS (custom order tables) is enabled.
	 */
	public static function is_hpos_enabled(): bool {
		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Whether any running version is below an announced future support floor.
	 *
	 * @param array{php: string, wp: string, wc: string} $running Host-reported versions.
	 * @param array<string, string>                      $announced axis => announced floor.
	 */
	public static function is_below_announced_floor( array $running, array $announced ): bool {
		$map = array(
			'php' => $running['php'],
			'wp'  => $running['wp'],
			'wc'  => $running['wc'],
		);

		foreach ( $announced as $axis => $floor ) {
			if ( ! isset( $map[ $axis ] ) || '' === $map[ $axis ] ) {
				continue;
			}

			if ( version_compare( $map[ $axis ], $floor, '<' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the debug-information section payload.
	 *
	 * @return array<string, mixed>
	 */
	private function debug_section(): array {
		$declared  = self::declared_versions();
		$running   = self::running_versions();
		$settings  = new Settings();
		$counts    = self::currency_counts( $settings->get_currencies() );
		$findings  = self::conflicts_detected_rows( $this->detector->findings() );
		$base_code = strtoupper( (string) \get_woocommerce_currency() );

		return array(
			'label'       => \__( 'Universal Multicurrency', 'universal-multicurrency' ),
			'description' => \__( 'Passive compatibility and environment diagnostics for support requests.', 'universal-multicurrency' ),
			'fields'      => array(
				'plugin_version'               => array(
					'label' => \__( 'Plugin version', 'universal-multicurrency' ),
					'value' => (string) UMC_VERSION,
				),
				'base_currency'                => array(
					'label' => \__( 'Base currency', 'universal-multicurrency' ),
					'value' => $base_code,
				),
				'currencies_configured'        => array(
					'label' => \__( 'Currencies configured', 'universal-multicurrency' ),
					'value' => (string) $counts['configured'],
				),
				'currencies_enabled_and_rated' => array(
					'label' => \__( 'Currencies enabled with a rate', 'universal-multicurrency' ),
					'value' => (string) $counts['enabled_and_rated'],
				),
				'hpos_enabled'                 => array(
					'label' => \__( 'HPOS enabled', 'universal-multicurrency' ),
					'value' => self::is_hpos_enabled() ? \__( 'Yes', 'universal-multicurrency' ) : \__( 'No', 'universal-multicurrency' ),
				),
				'snapshot_schema_version'      => array(
					'label' => \__( 'Settings schema version', 'universal-multicurrency' ),
					'value' => (string) Settings::SCHEMA_VERSION,
				),
				'declared_min_php'             => array(
					'label' => \__( 'Declared minimum PHP', 'universal-multicurrency' ),
					'value' => $declared['php'],
				),
				'declared_min_wp'              => array(
					'label' => \__( 'Declared minimum WordPress', 'universal-multicurrency' ),
					'value' => $declared['wp'],
				),
				'declared_min_wc'              => array(
					'label' => \__( 'Declared minimum WooCommerce', 'universal-multicurrency' ),
					'value' => $declared['wc'],
				),
				'running_php'                  => array(
					'label' => \__( 'Running PHP', 'universal-multicurrency' ),
					'value' => $running['php'],
				),
				'running_wp'                   => array(
					'label' => \__( 'Running WordPress', 'universal-multicurrency' ),
					'value' => $running['wp'],
				),
				'running_wc'                   => array(
					'label' => \__( 'Running WooCommerce', 'universal-multicurrency' ),
					'value' => $running['wc'],
				),
				'conflicts_detected'           => array(
					'label' => \__( 'Conflicts detected', 'universal-multicurrency' ),
					'value' => self::format_conflicts_debug_value( $findings ),
				),
				'store_api_conversion'         => array(
					'label' => \__( 'Store API conversion', 'universal-multicurrency' ),
					'value' => \__( 'Active', 'universal-multicurrency' ),
				),
				'stale_automatic_rates'        => array(
					'label' => \__( 'Stale automatic rates', 'universal-multicurrency' ),
					'value' => (string) $this->stale_automatic_count(),
				),
				'oldest_automatic_rate_age'    => array(
					'label' => \__( 'Oldest automatic rate age (hours)', 'universal-multicurrency' ),
					'value' => (string) $this->oldest_automatic_rate_age_hours(),
				),
			),
		);
	}

	/**
	 * Formats detected findings for the debug section value field.
	 *
	 * @param array<int, array<string, mixed>> $findings Detected finding rows.
	 */
	private static function format_conflicts_debug_value( array $findings ): string {
		if ( array() === $findings ) {
			return \__( 'None', 'universal-multicurrency' );
		}

		$lines = array();

		foreach ( $findings as $finding ) {
			$matched = isset( $finding['matched'] ) && is_array( $finding['matched'] )
				? implode( ', ', $finding['matched'] )
				: '';

			$lines[] = sprintf(
				'%s (%s): %s [%s]',
				\esc_html( (string) ( $finding['id'] ?? '' ) ),
				\esc_html( (string) ( $finding['confidence'] ?? '' ) ),
				\esc_html( (string) ( $finding['label'] ?? '' ) ),
				\esc_html( $matched )
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Builds human-readable issue sentences for the environment test body.
	 *
	 * @param array{php: string, wp: string, wc: string} $axes Axis classifications.
	 * @param bool                                       $hpos_enabled Whether HPOS is enabled.
	 * @param bool                                       $below_announced_floor Whether the store is below an announced future floor.
	 *
	 * @return array<int, string>
	 */
	private static function environment_issue_sentences( array $axes, bool $hpos_enabled, bool $below_announced_floor ): array {
		$issues = array();

		if ( VersionPolicy::BELOW_FLOOR === $axes['php'] ) {
			$issues[] = \__( 'PHP is below the minimum version required by Universal Multicurrency.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::BELOW_FLOOR === $axes['wp'] ) {
			$issues[] = \__( 'WordPress is below the minimum version required by Universal Multicurrency.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::BELOW_FLOOR === $axes['wc'] ) {
			$issues[] = \__( 'WooCommerce is below the minimum version required by Universal Multicurrency.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::ABOVE_TESTED === $axes['php'] ) {
			$issues[] = \__( 'PHP is newer than the version Universal Multicurrency has tested up to.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::ABOVE_TESTED === $axes['wp'] ) {
			$issues[] = \__( 'WordPress is newer than the version Universal Multicurrency has tested up to.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::ABOVE_TESTED === $axes['wc'] ) {
			$issues[] = \__( 'WooCommerce is newer than the version Universal Multicurrency has tested up to.', 'universal-multicurrency' );
		}

		if ( VersionPolicy::UNPARSEABLE === $axes['php']
			|| VersionPolicy::UNPARSEABLE === $axes['wp']
			|| VersionPolicy::UNPARSEABLE === $axes['wc'] ) {
			$issues[] = \__( 'One or more environment version strings could not be parsed.', 'universal-multicurrency' );
		}

		if ( $below_announced_floor ) {
			$issues[] = \__( 'This store is below a future minimum version announced in the compatibility documentation.', 'universal-multicurrency' );
		}

		if ( ! $hpos_enabled ) {
			$issues[] = \__( 'High-Performance Order Storage (HPOS) is not enabled. Universal Multicurrency supports legacy order storage, but HPOS is recommended.', 'universal-multicurrency' );
		}

		return $issues;
	}

	/**
	 * Joins escaped detector labels for use inside a translated sentence.
	 *
	 * @param array<int, string> $labels Escaped-ready detector labels.
	 */
	private static function format_label_list( array $labels ): string {
		$escaped = array_map(
			static function ( string $label ): string {
				return \esc_html( $label );
			},
			$labels
		);

		if ( 1 === count( $escaped ) ) {
			return $escaped[0];
		}

		if ( 2 === count( $escaped ) ) {
			return $escaped[0] . ' ' . \__( 'and', 'universal-multicurrency' ) . ' ' . $escaped[1];
		}

		$last = array_pop( $escaped );

		return implode( ', ', $escaped ) . ', ' . \__( 'and', 'universal-multicurrency' ) . ' ' . $last;
	}

	/**
	 * Counts automatic currencies with stale provider rates.
	 */
	private function stale_automatic_count(): int {
		if ( null === $this->settings || null === $this->rate_store ) {
			return 0;
		}

		$evaluator = new RateStatusEvaluator( $this->settings, $this->rate_store );
		$stale     = 0;

		foreach ( array_keys( $this->settings->get_currencies() ) as $code ) {
			if ( RateStatusEvaluator::LABEL_STALE === $evaluator->label_for_currency( $code ) ) {
				++$stale;
			}
		}

		return $stale;
	}

	/**
	 * Returns the age in hours of the oldest automatic provider rate.
	 */
	private function oldest_automatic_rate_age_hours(): int {
		if ( null === $this->settings ) {
			return 0;
		}

		$oldest = 0;

		foreach ( $this->settings->get_currencies() as $code => $config ) {
			if ( Settings::RATE_MODE_AUTOMATIC !== $this->settings->get_effective_rate_mode( (string) $code ) ) {
				continue;
			}

			$updated = (int) ( $config['rate_updated_at'] ?? 0 );

			if ( $updated <= 0 ) {
				continue;
			}

			$age    = (int) floor( ( time() - $updated ) / 3600 );
			$oldest = max( $oldest, $age );
		}

		return $oldest;
	}

	/**
	 * Normalises a Site Health direct-test result payload.
	 *
	 * @param string $test        Site Health test slug.
	 * @param string $label       Result headline shown in the UI.
	 * @param string $status      One of `good`, `recommended`, or `critical`.
	 * @param string $description HTML body paragraphs for the result.
	 *
	 * @return array<string, mixed>
	 */
	private static function format_test_result( string $test, string $label, string $status, string $description ): array {
		$badge_color = 'good' === $status ? 'green' : ( 'critical' === $status ? 'red' : 'orange' );

		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => \__( 'Universal Multicurrency', 'universal-multicurrency' ),
				'color' => $badge_color,
			),
			'description' => $description,
			'actions'     => '',
			'test'        => $test,
		);
	}
}
