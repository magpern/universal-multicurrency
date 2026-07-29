<?php
/**
 * Builds human-readable price format examples for admin UI.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin;

/**
 * Formats sample amounts for currency position previews.
 */
final class CurrencyFormatPreview {

	/**
	 * Returns a localized example for one symbol position.
	 *
	 * @param string $position One of left, right, left_space, right_space.
	 * @param string $symbol   Currency symbol.
	 */
	public static function example( string $position, string $symbol = '$' ): string {
		$amount = '115.38';
		$symbol = '' !== trim( $symbol ) ? trim( $symbol ) : '$';

		return match ( $position ) {
			'right'       => $amount . $symbol,
			'left_space'  => $symbol . ' ' . $amount,
			'right_space' => $amount . ' ' . $symbol,
			default       => $symbol . $amount,
		};
	}

	/**
	 * Returns option labels keyed by position slug.
	 *
	 * @return array<string, string>
	 */
	public static function position_labels(): array {
		return array(
			'left'        => __( 'Before amount (no space)', 'universal-multicurrency' ),
			'right'       => __( 'After amount (no space)', 'universal-multicurrency' ),
			'left_space'  => __( 'Before amount (with space)', 'universal-multicurrency' ),
			'right_space' => __( 'After amount (with space)', 'universal-multicurrency' ),
		);
	}
}
