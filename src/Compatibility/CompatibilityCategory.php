<?php
/**
 * Result categories for the Compatibility center.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility;

/**
 * Groups compatibility results for summary cards and ordering.
 */
final class CompatibilityCategory {

	public const CONFLICTS = 'conflicts';

	public const CONFIGURATION = 'configuration';

	public const INTEGRATIONS = 'integrations';

	public const THEME = 'theme';

	public const CACHE = 'cache';

	public const ENVIRONMENT = 'environment';

	public const RUNTIME = 'runtime';

	/**
	 * Display order for categories.
	 *
	 * @var array<int, string>
	 */
	public const ORDER = array(
		self::CONFLICTS,
		self::CONFIGURATION,
		self::INTEGRATIONS,
		self::THEME,
		self::CACHE,
		self::ENVIRONMENT,
		self::RUNTIME,
	);

	/**
	 * Whether the category is valid.
	 *
	 * @param string $category Category slug.
	 */
	public static function is_valid( string $category ): bool {
		return in_array( $category, self::ORDER, true );
	}

	/**
	 * Sort index for a category.
	 *
	 * @param string $category Category slug.
	 */
	public static function sort_index( string $category ): int {
		$index = array_search( $category, self::ORDER, true );

		return false === $index ? 999 : (int) $index;
	}
}
