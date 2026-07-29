<?php
/**
 * Test double metadata provider backed by a fixed map.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Tests\Unit\Doubles;

use UMC\Currency\CurrencyMetadata;
use UMC\Currency\CurrencyMetadataProvider;

/**
 * Returns predefined metadata for unit tests.
 */
final class MapMetadataProvider implements CurrencyMetadataProvider {

	/**
	 * @param array<string, CurrencyMetadata> $map Metadata keyed by ISO code.
	 */
	public function __construct( private array $map ) {
	}

	public function get( string $code ): ?CurrencyMetadata {
		return $this->map[ strtoupper( trim( $code ) ) ] ?? null;
	}

	public function all(): array {
		return $this->map;
	}

	public function search( string $query ): array {
		unset( $query );

		return $this->map;
	}

	public function is_known( string $code ): bool {
		return isset( $this->map[ strtoupper( trim( $code ) ) ] );
	}
}
