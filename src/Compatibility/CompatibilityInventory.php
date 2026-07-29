<?php
/**
 * Request-scoped facts shared by compatibility checks.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

use UMC\Currency;
use UMC\Diagnostics\ConflictDetector;
use UMC\Rates\ExchangeRateStore;
use UMC\Settings;

/**
 * Normalized environment inventory loaded once per scan.
 */
final class CompatibilityInventory {

	/**
	 * Plugin settings store.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Rate persistence boundary.
	 *
	 * @var ExchangeRateStore
	 */
	private ExchangeRateStore $rate_store;

	/**
	 * Store base currency.
	 *
	 * @var Currency
	 */
	private Currency $base;

	/**
	 * Shared conflict detector.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $conflict_detector;

	/**
	 * Installed plugins from get_plugins().
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $plugins;

	/**
	 * Active plugin file paths.
	 *
	 * @var array<int, string>
	 */
	private array $active_plugins;

	/**
	 * Active theme metadata.
	 *
	 * @var array<string, string>
	 */
	private array $theme;

	/**
	 * Parent theme metadata when present.
	 *
	 * @var array<string, string>
	 */
	private array $parent_theme;

	/**
	 * Environment facts.
	 *
	 * @var array<string, string>
	 */
	private array $facts;

	/**
	 * Creates inventory from runtime dependencies.
	 *
	 * @param Settings              $settings           Settings store.
	 * @param ExchangeRateStore     $rate_store         Rate store.
	 * @param Currency              $base               Base currency.
	 * @param ConflictDetector      $conflict_detector  Conflict detector.
	 * @param array<string, mixed>  $plugins           Installed plugins.
	 * @param array<int, string>    $active_plugins     Active plugin paths.
	 * @param array<string, string> $theme           Active theme facts.
	 * @param array<string, string> $parent_theme    Parent theme facts.
	 * @param array<string, string> $facts           Environment facts.
	 */
	public function __construct(
		Settings $settings,
		ExchangeRateStore $rate_store,
		Currency $base,
		ConflictDetector $conflict_detector,
		array $plugins,
		array $active_plugins,
		array $theme,
		array $parent_theme,
		array $facts
	) {
		$this->settings          = $settings;
		$this->rate_store        = $rate_store;
		$this->base              = $base;
		$this->conflict_detector = $conflict_detector;
		$this->plugins           = $plugins;
		$this->active_plugins    = $active_plugins;
		$this->theme             = $theme;
		$this->parent_theme      = $parent_theme;
		$this->facts             = $facts;
	}

	/**
	 * Builds inventory from the current WordPress runtime.
	 *
	 * @param Settings          $settings          Settings store.
	 * @param ExchangeRateStore $rate_store        Rate store.
	 * @param Currency          $base              Base currency.
	 * @param ConflictDetector  $conflict_detector Conflict detector.
	 */
	public static function from_runtime(
		Settings $settings,
		ExchangeRateStore $rate_store,
		Currency $base,
		ConflictDetector $conflict_detector
	): self {
		$plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
		}

		$active = get_option( 'active_plugins', array() );
		if ( ! is_array( $active ) ) {
			$active = array();
		}

		$theme       = wp_get_theme();
		$theme_facts = array(
			'name'       => (string) $theme->get( 'Name' ),
			'version'    => (string) $theme->get( 'Version' ),
			'stylesheet' => (string) $theme->get_stylesheet(),
			'template'   => (string) $theme->get_template(),
		);

		$parent_facts = array();
		if ( $theme->parent() ) {
			$parent       = $theme->parent();
			$parent_facts = array(
				'name'       => (string) $parent->get( 'Name' ),
				'version'    => (string) $parent->get( 'Version' ),
				'stylesheet' => (string) $parent->get_stylesheet(),
				'template'   => (string) $parent->get_template(),
			);
		}

		$permalink  = (string) get_option( 'permalink_structure', '' );
		$db_version = '';
		$facts      = array(
			'umc_version'    => defined( 'UMC_VERSION' ) ? (string) UMC_VERSION : '',
			'schema_version' => (string) ( $settings->get()['schema_version'] ?? Settings::SCHEMA_VERSION ),
			'wordpress'      => (string) get_bloginfo( 'version' ),
			'woocommerce'    => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
			'php'            => PHP_VERSION,
			'database'       => $db_version,
			'multisite'      => is_multisite() ? 'yes' : 'no',
			'hpos'           => self::hpos_enabled() ? 'enabled' : 'disabled',
			'object_cache'   => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ? 'external' : 'default',
			'cron_disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'yes' : 'no',
			'permalink'      => '' === $permalink ? 'plain' : 'pretty',
			'memory_limit'   => (string) ini_get( 'memory_limit' ),
			'max_execution'  => (string) ini_get( 'max_execution_time' ),
			'locale'         => (string) get_locale(),
			'base_currency'  => strtoupper( (string) get_woocommerce_currency() ),
			'enabled_codes'  => implode(
				', ',
				array_keys(
					array_filter(
						$settings->get_currencies(),
						static function ( array $config ): bool {
							return ! empty( $config['enabled'] );
						}
					)
				)
			),
		);

		return new self(
			$settings,
			$rate_store,
			$base,
			$conflict_detector,
			$plugins,
			array_values( array_map( 'strval', $active ) ),
			$theme_facts,
			$parent_facts,
			$facts
		);
	}

	/**
	 * Settings store.
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Rate store.
	 */
	public function rate_store(): ExchangeRateStore {
		return $this->rate_store;
	}

	/**
	 * Base currency.
	 */
	public function base(): Currency {
		return $this->base;
	}

	/**
	 * Conflict detector.
	 */
	public function conflict_detector(): ConflictDetector {
		return $this->conflict_detector;
	}

	/**
	 * Installed plugins.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function plugins(): array {
		return $this->plugins;
	}

	/**
	 * Active plugin file paths.
	 *
	 * @return array<int, string>
	 */
	public function active_plugins(): array {
		return $this->active_plugins;
	}

	/**
	 * Active theme facts.
	 *
	 * @return array<string, string>
	 */
	public function theme(): array {
		return $this->theme;
	}

	/**
	 * Parent theme facts.
	 *
	 * @return array<string, string>
	 */
	public function parent_theme(): array {
		return $this->parent_theme;
	}

	/**
	 * Environment facts.
	 *
	 * @return array<string, string>
	 */
	public function facts(): array {
		return $this->facts;
	}

	/**
	 * Whether a plugin basename is active.
	 *
	 * @param string $plugin_file Plugin basename/path.
	 */
	public function is_plugin_active( string $plugin_file ): bool {
		return in_array( $plugin_file, $this->active_plugins, true );
	}

	/**
	 * Finds an installed plugin file ending with the given suffix.
	 *
	 * @param string $path_suffix Plugin path suffix such as `plugin/file.php`.
	 */
	public function find_plugin_by_path_suffix( string $path_suffix ): ?string {
		foreach ( array_keys( $this->plugins ) as $plugin_file ) {
			if ( str_ends_with( $plugin_file, $path_suffix ) ) {
				return $plugin_file;
			}
		}

		return null;
	}

	/**
	 * Version string for an installed plugin file.
	 *
	 * @param string $plugin_file Plugin basename/path.
	 */
	public function plugin_version( string $plugin_file ): string {
		return (string) ( $this->plugins[ $plugin_file ]['Version'] ?? '' );
	}

	/**
	 * Whether WooCommerce HPOS is enabled.
	 */
	private static function hpos_enabled(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
