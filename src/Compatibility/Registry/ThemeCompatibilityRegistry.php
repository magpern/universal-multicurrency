<?php
/**
 * Theme compatibility registry for the Compatibility center.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Registry;

/**
 * Conservative theme status labels keyed by stylesheet slug.
 */
final class ThemeCompatibilityRegistry {

	public const STATUS_TESTED = 'Tested';

	public const STATUS_EXPECTED = 'Expected compatible';

	public const STATUS_UNTESTED = 'Untested';

	public const STATUS_KNOWN_ISSUE = 'Known issue';

	/**
	 * Known theme entries.
	 *
	 * @return array<string, array{label: string, status: string, note: string}>
	 */
	public static function entries(): array {
		return array(
			'storefront'    => array(
				'label'  => 'Storefront',
				'status' => self::STATUS_EXPECTED,
				'note'   => '',
			),
			'blocksy'       => array(
				'label'  => 'Blocksy',
				'status' => self::STATUS_EXPECTED,
				'note'   => '',
			),
			'astra'         => array(
				'label'  => 'Astra',
				'status' => self::STATUS_EXPECTED,
				'note'   => '',
			),
			'kadence'       => array(
				'label'  => 'Kadence',
				'status' => self::STATUS_EXPECTED,
				'note'   => '',
			),
			'generatepress' => array(
				'label'  => 'GeneratePress',
				'status' => self::STATUS_EXPECTED,
				'note'   => '',
			),
			'flatsome'      => array(
				'label'  => 'Flatsome',
				'status' => self::STATUS_UNTESTED,
				'note'   => '',
			),
		);
	}

	/**
	 * Resolves a registry entry for one stylesheet slug.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return array{label: string, status: string, note: string}
	 */
	public static function resolve( string $stylesheet ): array {
		$stylesheet = strtolower( preg_replace( '/[^a-z0-9_-]/', '', $stylesheet ) ?? '' );

		if ( isset( self::entries()[ $stylesheet ] ) ) {
			return self::entries()[ $stylesheet ];
		}

		return array(
			'label'  => $stylesheet,
			'status' => self::STATUS_UNTESTED,
			'note'   => '',
		);
	}
}
