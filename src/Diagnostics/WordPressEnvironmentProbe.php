<?php
/**
 * WordPress-backed environment observation for passive signature checks.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The only Diagnostics class that reads WordPress registries. Every probe is
 * a pure lookup that returns a boolean — never a value, never a side effect,
 * never autoload (`class_exists( $needle, false )`), never `constant()`.
 */
final class WordPressEnvironmentProbe implements EnvironmentProbe {

	/**
	 * Memoized active-plugin path set for this request.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $active_paths = null;

	/**
	 * @param array<int, Signature> $signatures Signatures to evaluate.
	 *
	 * @return array<string, bool> Keyed by {@see Signature::key()}.
	 */
	public function evaluate( array $signatures ): array {
		$result = array();

		foreach ( $signatures as $signature ) {
			$result[ $signature->key() ] = $this->is_present( $signature );
		}

		return $result;
	}

	/**
	 * Answers whether one signature is present in the host environment.
	 */
	private function is_present( Signature $signature ): bool {
		switch ( $signature->kind() ) {
			case SignatureKind::PLUGIN_PATH:
				return $this->has_active_plugin_path( $signature->needle() );
			case SignatureKind::CLASS_NAME:
				return class_exists( $signature->needle(), false );
			case SignatureKind::FUNCTION:
				return function_exists( $signature->needle() );
			case SignatureKind::CONSTANT:
				return defined( $signature->needle() );
			case SignatureKind::SHORTCODE:
				return $this->has_registered_shortcode( $signature->needle() );
			case SignatureKind::HOOK:
				return $this->has_registered_hook( $signature->needle() );
			default:
				return false;
		}
	}

	/**
	 * @return array<string, true> Active plugin bootstrap paths keyed for O(1) lookup.
	 */
	private function active_plugin_paths(): array {
		if ( null !== $this->active_paths ) {
			return $this->active_paths;
		}

		$paths = array();

		$active = get_option( 'active_plugins', array() );

		if ( is_array( $active ) ) {
			foreach ( $active as $path ) {
				if ( is_string( $path ) && '' !== $path ) {
					$paths[ $path ] = true;
				}
			}
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );

			if ( is_array( $network ) ) {
				foreach ( array_keys( $network ) as $path ) {
					if ( is_string( $path ) && '' !== $path ) {
						$paths[ $path ] = true;
					}
				}
			}
		}

		$this->active_paths = $paths;

		return $paths;
	}

	private function has_active_plugin_path( string $needle ): bool {
		return isset( $this->active_plugin_paths()[ $needle ] );
	}

	private function has_registered_shortcode( string $needle ): bool {
		global $shortcode_tags;

		return is_array( $shortcode_tags ) && isset( $shortcode_tags[ $needle ] );
	}

	private function has_registered_hook( string $needle ): bool {
		global $wp_filter;

		if ( ! is_array( $wp_filter ) && ! ( $wp_filter instanceof \WP_Hook ) ) {
			return false;
		}

		return isset( $wp_filter[ $needle ] );
	}
}
