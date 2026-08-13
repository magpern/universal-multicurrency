<?php
/**
 * Immutable extension compatibility claim with evidence metadata.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Compatibility\Extension;

use InvalidArgumentException;

/**
 * One extension's compatibility posture for diagnostics and matrix drift guards.
 */
final class ExtensionCompatibilityRecord {

	/**
	 * Creates a validated compatibility record.
	 *
	 * @param string             $id               Stable extension id.
	 * @param string             $label            Merchant-facing label.
	 * @param string             $status           Compatibility status slug.
	 * @param string             $evidence_tier    Evidence tier (E0–E3).
	 * @param string             $tested_through   Semver tested through.
	 * @param string             $detected_version Runtime detected version.
	 * @param bool               $installed        Whether extension is installed.
	 * @param bool               $active           Whether extension is active.
	 * @param bool               $adapter_active   Whether UMC adapter is active.
	 * @param array<int, string> $surfaces         Monetary surfaces covered.
	 * @param array<int, string> $limitations      Known limitations.
	 * @param string             $adapter_id       Adapter class/id if any.
	 * @param array<int, string> $evidence_tests   Test class names supporting claim.
	 * @param string             $plugin_file      Plugin bootstrap path if known.
	 *
	 * @throws InvalidArgumentException When id, status, or tier is invalid.
	 */
	public function __construct(
		private string $id,
		private string $label,
		private string $status,
		private string $evidence_tier,
		private string $tested_through,
		private string $detected_version,
		private bool $installed,
		private bool $active,
		private bool $adapter_active,
		private array $surfaces = array(),
		private array $limitations = array(),
		private string $adapter_id = '',
		private array $evidence_tests = array(),
		private string $plugin_file = ''
	) {
		if ( '' === $id ) {
			throw new InvalidArgumentException( 'Extension id cannot be empty.' );
		}

		if ( ! ExtensionCompatibilityStatus::is_valid( $status ) ) {
			throw new InvalidArgumentException( "Unknown extension status: {$status}." );
		}

		if ( ! ExtensionEvidenceTier::is_valid( $evidence_tier ) ) {
			throw new InvalidArgumentException( "Unknown evidence tier: {$evidence_tier}." );
		}

		$this->enforce_evidence_ceiling();
	}

	/**
	 * Ensures status does not exceed evidence tier ceiling.
	 *
	 * @throws InvalidArgumentException When status exceeds tier ceiling.
	 */
	private function enforce_evidence_ceiling(): void {
		$max = ExtensionEvidenceTier::max_status( $this->evidence_tier );

		if ( ExtensionCompatibilityStatus::INTEGRATED === $this->status
			&& ExtensionCompatibilityStatus::INTEGRATED !== $max ) {
			throw new InvalidArgumentException(
				"Status Integrated requires E3 evidence; got {$this->evidence_tier}."
			);
		}

		if ( ExtensionCompatibilityStatus::CHARACTERIZED === $this->status
			&& ExtensionCompatibilityStatus::NOT_EVALUATED === $max ) {
			throw new InvalidArgumentException(
				"Status Characterized requires at least E1 evidence; got {$this->evidence_tier}."
			);
		}
	}

	/**
	 * Stable extension id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Merchant-facing label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Compatibility status slug.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Evidence tier slug.
	 */
	public function evidence_tier(): string {
		return $this->evidence_tier;
	}

	/**
	 * Semver tested through.
	 */
	public function tested_through(): string {
		return $this->tested_through;
	}

	/**
	 * Runtime detected version.
	 */
	public function detected_version(): string {
		return $this->detected_version;
	}

	/**
	 * Whether the extension is installed.
	 */
	public function installed(): bool {
		return $this->installed;
	}

	/**
	 * Whether the extension is active.
	 */
	public function active(): bool {
		return $this->active;
	}

	/**
	 * Whether the UMC adapter is active.
	 */
	public function adapter_active(): bool {
		return $this->adapter_active;
	}

	/**
	 * Monetary surfaces covered by the claim.
	 *
	 * @return array<int, string>
	 */
	public function surfaces(): array {
		return $this->surfaces;
	}

	/**
	 * Known limitations for the extension.
	 *
	 * @return array<int, string>
	 */
	public function limitations(): array {
		return $this->limitations;
	}

	/**
	 * Adapter class or id when present.
	 */
	public function adapter_id(): string {
		return $this->adapter_id;
	}

	/**
	 * Test class names supporting the compatibility claim.
	 *
	 * @return array<int, string>
	 */
	public function evidence_tests(): array {
		return $this->evidence_tests;
	}

	/**
	 * Plugin bootstrap path when known.
	 */
	public function plugin_file(): string {
		return $this->plugin_file;
	}

	/**
	 * Primary merchant status line for Compatibility Center.
	 */
	public function merchant_status_line(): string {
		return ExtensionEvidenceTier::merchant_status_line( $this->status, $this->evidence_tier );
	}

	/**
	 * Whether detected version exceeds tested-through range.
	 */
	public function is_untested_version(): bool {
		if ( '' === $this->detected_version || '' === $this->tested_through ) {
			return false;
		}

		return version_compare( $this->detected_version, $this->tested_through, '>' );
	}

	/**
	 * Structured evidence payload for admin rendering.
	 *
	 * @return array<string, string>
	 */
	public function evidence_payload(): array {
		return array(
			'status'           => $this->status,
			'status_line'      => $this->merchant_status_line(),
			'evidence_tier'    => $this->evidence_tier,
			'tested_through'   => $this->tested_through,
			'detected_version' => $this->detected_version,
			'untested_version' => $this->is_untested_version() ? 'yes' : 'no',
			'adapter_active'   => $this->adapter_active ? 'yes' : 'no',
			'adapter_id'       => $this->adapter_id,
			'surfaces'         => implode( ', ', $this->surfaces ),
			'evidence_tests'   => implode( ', ', $this->evidence_tests ),
		);
	}
}
