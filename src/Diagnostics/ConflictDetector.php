<?php
/**
 * Orchestrates registry lookup, one-pass probing, and pure scoring.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Advisory only: the findings this class memoizes may influence rendered admin
 * text in later milestones, and nothing else. Detection runs lazily on the
 * first read, probes the environment exactly once per instance, and then
 * memoizes both the evidence map and the scored findings.
 */
final class ConflictDetector {

	private DetectorCatalog $registry;

	private EnvironmentProbe $probe;

	private ConflictScorer $scorer;

	/**
	 * Memoized scored findings.
	 *
	 * @var array<int, Finding>|null
	 */
	private ?array $findings = null;

	/**
	 * Memoized evidence from the single probe pass.
	 *
	 * @var array<string, bool>|null
	 */
	private ?array $evidence = null;

	public function __construct(
		DetectorCatalog $registry,
		EnvironmentProbe $probe,
		ConflictScorer $scorer
	) {
		$this->registry = $registry;
		$this->probe    = $probe;
		$this->scorer   = $scorer;
	}

	/**
	 * Memoized findings ordered by score descending, then id ascending.
	 *
	 * @return array<int, Finding>
	 */
	public function findings(): array {
		if ( null === $this->findings ) {
			$detectors      = $this->registry->detectors();
			$this->evidence = $this->probe->evaluate( $this->collect_signatures( $detectors ) );
			$this->findings = $this->scorer->score( $detectors, $this->evidence );
		}

		return $this->findings;
	}

	/**
	 * Whether at least one finding cleared the default reporting threshold.
	 */
	public function has_conflict(): bool {
		return array() !== $this->findings();
	}

	/**
	 * Stable fingerprint for the current conflict set, used by dismissal in a
	 * later milestone. Empty when no conflict is detected.
	 */
	public function fingerprint(): string {
		$findings = $this->findings();

		if ( array() === $findings ) {
			return '';
		}

		$ids = array_map(
			static function ( Finding $finding ): string {
				return $finding->id();
			},
			$findings
		);

		sort( $ids );

		$confidence  = $this->highest_confidence( $findings );
		$major_minor = $this->major_minor_version();

		return substr( sha1( implode( '|', $ids ) . '#' . $confidence . '#' . $major_minor ), 0, 16 );
	}

	/**
	 * Exposes the memoized evidence map for tests that assert one-pass probing.
	 *
	 * @return array<string, bool>
	 */
	public function evidence(): array {
		if ( null === $this->evidence ) {
			$this->findings();
		}

		return $this->evidence ?? array();
	}

	/**
	 * @param array<int, Detector> $detectors Hydrated detectors in registry order.
	 *
	 * @return array<int, Signature>
	 */
	private function collect_signatures( array $detectors ): array {
		$signatures = array();

		foreach ( $detectors as $detector ) {
			foreach ( $detector->signatures() as $signature ) {
				$signatures[ $signature->key() ] = $signature;
			}
		}

		ksort( $signatures );

		return array_values( $signatures );
	}

	/**
	 * @param array<int, Finding> $findings Scored findings.
	 */
	private function highest_confidence( array $findings ): string {
		$highest = Confidence::NONE;

		foreach ( $findings as $finding ) {
			if ( Confidence::RANK[ $finding->confidence() ] > Confidence::RANK[ $highest ] ) {
				$highest = $finding->confidence();
			}
		}

		return $highest;
	}

	private function major_minor_version(): string {
		if ( ! defined( 'UMC_VERSION' ) ) {
			return '0.0';
		}

		$version = (string) UMC_VERSION;

		if ( 1 === preg_match( '/^(\d+\.\d+)/', $version, $match ) ) {
			return $match[1];
		}

		return '0.0';
	}
}
