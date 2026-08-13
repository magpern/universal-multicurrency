<?php
/**
 * Extension compatibility diagnostic check.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Checks;

use UMC\Compatibility\CompatibilityCategory;
use UMC\Compatibility\CompatibilityCheckInterface;
use UMC\Compatibility\CompatibilityDeterminism;
use UMC\Compatibility\CompatibilityInventory;
use UMC\Compatibility\CompatibilityResult;
use UMC\Compatibility\CompatibilitySeverity;
use UMC\Compatibility\Extension\ExtensionCompatibilityRegistry;
use UMC\Compatibility\Extension\ExtensionCompatibilityStatus;

/**
 * Reports third-party extension compatibility with evidence-tier sub-labels.
 */
final class ExtensionCompatibilityCheck implements CompatibilityCheckInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param CompatibilityInventory $inventory Shared inventory.
	 * @return array<int, CompatibilityResult>
	 */
	public function run( CompatibilityInventory $inventory ): array {
		$results = array();

		$records = ExtensionCompatibilityRegistry::records(
			$inventory->plugins(),
			$inventory->active_plugins()
		);

		foreach ( $records as $record ) {
			if ( ! $record->installed() ) {
				continue;
			}

			if ( ExtensionCompatibilityStatus::NOT_EVALUATED === $record->status() && ! $record->active() ) {
				continue;
			}

			$severity = $this->severity_for( $record->status(), $record->is_untested_version() );
			$summary  = $this->build_summary( $record );

			$details = $record->limitations();
			if ( $record->is_untested_version() ) {
				$details[] = sprintf(
					/* translators: 1: detected version, 2: tested-through version */
					__( 'Untested version: detected %1$s, validated through %2$s.', 'universal-multicurrency' ),
					$record->detected_version(),
					$record->tested_through()
				);
			}

			$results[] = new CompatibilityResult(
				'extension.' . $record->id(),
				CompatibilityCategory::INTEGRATIONS,
				$severity,
				$record->label(),
				$summary,
				CompatibilityDeterminism::DETERMINISTIC,
				$record->evidence_payload(),
				array(),
				$details
			);
		}

		return $results;
	}

	/**
	 * Maps compatibility status to admin severity.
	 *
	 * @param string $status           Compatibility status.
	 * @param bool   $untested_version Whether version exceeds tested-through.
	 */
	private function severity_for( string $status, bool $untested_version ): string {
		if ( $untested_version ) {
			return CompatibilitySeverity::WARNING;
		}

		return match ( $status ) {
			ExtensionCompatibilityStatus::INCOMPATIBLE => CompatibilitySeverity::CONFLICT,
			ExtensionCompatibilityStatus::KNOWN_LIMITATION => CompatibilitySeverity::WARNING,
			ExtensionCompatibilityStatus::INTEGRATED,
			ExtensionCompatibilityStatus::NATIVE => CompatibilitySeverity::PASS,
			default => CompatibilitySeverity::INFO,
		};
	}

	/**
	 * Builds the merchant-facing summary line.
	 *
	 * @param \UMC\Compatibility\Extension\ExtensionCompatibilityRecord $record Extension record.
	 */
	private function build_summary( $record ): string {
		$line = $record->merchant_status_line();

		if ( ! $record->active() ) {
			return sprintf(
				/* translators: 1: extension label, 2: status line */
				__( '%1$s is installed but inactive. Status: %2$s', 'universal-multicurrency' ),
				$record->label(),
				$line
			);
		}

		return sprintf(
			/* translators: 1: extension label, 2: status line */
			__( '%1$s — %2$s', 'universal-multicurrency' ),
			$record->label(),
			$line
		);
	}
}
