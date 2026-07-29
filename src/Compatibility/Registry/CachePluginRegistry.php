<?php
/**
 * Cache plugin registry for Compatibility diagnostics.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Registry;

/**
 * Cache-related plugin and signal definitions.
 */
final class CachePluginRegistry {

	public const TYPE_OBJECT = 'object_cache';

	public const TYPE_PAGE = 'page_cache';

	public const TYPE_EDGE = 'edge_cache';

	/**
	 * Returns cache integration definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions(): array {
		return array(
			array(
				'id'           => 'redis_object_cache',
				'label'        => 'Redis Object Cache',
				'type'         => self::TYPE_OBJECT,
				'status_label' => 'Usually safe',
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'redis-cache/redis-cache.php',
					),
				),
			),
			array(
				'id'           => 'litespeed_cache',
				'label'        => 'LiteSpeed Cache',
				'type'         => self::TYPE_PAGE,
				'status_label' => 'Potential interaction',
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'litespeed-cache/litespeed-cache.php',
					),
				),
			),
			array(
				'id'           => 'wp_rocket',
				'label'        => 'WP Rocket',
				'type'         => self::TYPE_PAGE,
				'status_label' => 'Potential interaction',
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'wp-rocket/wp-rocket.php',
					),
				),
			),
			array(
				'id'           => 'w3_total_cache',
				'label'        => 'W3 Total Cache',
				'type'         => self::TYPE_PAGE,
				'status_label' => 'Potential interaction',
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'w3-total-cache/w3-total-cache.php',
					),
				),
			),
			array(
				'id'           => 'wp_super_cache',
				'label'        => 'WP Super Cache',
				'type'         => self::TYPE_PAGE,
				'status_label' => 'Potential interaction',
				'signatures'   => array(
					array(
						'type'   => 'plugin_path',
						'needle' => 'wp-super-cache/wp-cache.php',
					),
				),
			),
			array(
				'id'           => 'cloudflare',
				'label'        => 'Cloudflare',
				'type'         => self::TYPE_EDGE,
				'status_label' => 'Potential interaction',
				'signatures'   => array(
					array(
						'type'   => 'constant',
						'needle' => 'CLOUDFLARE_PLUGIN',
					),
					array(
						'type'   => 'plugin_path',
						'needle' => 'cloudflare/cloudflare.php',
					),
				),
			),
		);
	}
}
