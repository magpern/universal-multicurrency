<?php
/**
 * Safe passive detection of third-party WooCommerce extensions.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

/**
 * Detects extension presence without fatal references when absent.
 */
final class ExtensionDetector {

	/**
	 * Detects extension state from signatures and plugin registry.
	 *
	 * @param array<string, mixed> $definition Extension definition from registry.
	 * @param array<string, mixed> $plugins    Installed plugins from get_plugins().
	 * @param array<int, string>   $active     Active plugin paths.
	 * @return array{installed: bool, active: bool, plugin_file: string, version: string}
	 */
	public static function detect( array $definition, array $plugins, array $active ): array {
		$signatures  = $definition['signatures'] ?? array();
		$plugin_file = self::match_plugin_file( $signatures, $plugins );

		if ( null === $plugin_file && self::match_runtime_signature( $signatures ) ) {
			return array(
				'installed'   => true,
				'active'      => true,
				'plugin_file' => '',
				'version'     => self::match_constant_version( $signatures ),
			);
		}

		if ( null === $plugin_file ) {
			return array(
				'installed'   => false,
				'active'      => false,
				'plugin_file' => '',
				'version'     => '',
			);
		}

		return array(
			'installed'   => true,
			'active'      => in_array( $plugin_file, $active, true ),
			'plugin_file' => $plugin_file,
			'version'     => (string) ( $plugins[ $plugin_file ]['Version'] ?? '' ),
		);
	}

	/**
	 * Whether detected version exceeds tested-through version.
	 *
	 * @param string $detected       Detected semver.
	 * @param string $tested_through Tested-through semver.
	 */
	public static function is_untested_version( string $detected, string $tested_through ): bool {
		if ( '' === $detected || '' === $tested_through ) {
			return false;
		}

		return version_compare( $detected, $tested_through, '>' );
	}

	/**
	 * Matches a plugin bootstrap file from signatures.
	 *
	 * @param array<int, array<string, string>> $signatures Signature definitions.
	 * @param array<string, mixed>              $plugins    Installed plugins.
	 */
	private static function match_plugin_file( array $signatures, array $plugins ): ?string {
		foreach ( $signatures as $signature ) {
			if ( 'plugin_path' !== ( $signature['type'] ?? '' ) ) {
				continue;
			}

			$needle = (string) ( $signature['needle'] ?? '' );
			if ( '' === $needle ) {
				continue;
			}

			if ( isset( $plugins[ $needle ] ) ) {
				return $needle;
			}

			foreach ( array_keys( $plugins ) as $plugin_file ) {
				if ( str_ends_with( $plugin_file, $needle ) ) {
					return $plugin_file;
				}
			}
		}

		return null;
	}

	/**
	 * Matches non-path runtime signatures without autoload.
	 *
	 * @param array<int, array<string, string>> $signatures Signature definitions.
	 */
	private static function match_runtime_signature( array $signatures ): bool {
		foreach ( $signatures as $signature ) {
			$type   = (string) ( $signature['type'] ?? '' );
			$needle = (string) ( $signature['needle'] ?? '' );

			if ( '' === $needle ) {
				continue;
			}

			switch ( $type ) {
				case 'class':
					if ( class_exists( $needle, false ) ) {
						return true;
					}
					break;
				case 'function':
					if ( function_exists( $needle ) ) {
						return true;
					}
					break;
				case 'constant':
					if ( defined( $needle ) ) {
						return true;
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Reads version from a defined constant when plugin header unavailable.
	 *
	 * @param array<int, array<string, string>> $signatures Signature definitions.
	 */
	private static function match_constant_version( array $signatures ): string {
		foreach ( $signatures as $signature ) {
			if ( 'version_constant' !== ( $signature['type'] ?? '' ) ) {
				continue;
			}

			$needle = (string) ( $signature['needle'] ?? '' );
			if ( '' !== $needle && defined( $needle ) ) {
				return (string) constant( $needle );
			}
		}

		return '';
	}
}
