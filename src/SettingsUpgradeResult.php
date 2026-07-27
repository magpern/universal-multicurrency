<?php
/**
 * Result of a settings schema upgrade attempt.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC;

/**
 * Value object returned by {@see SettingsUpgrader::upgrade()}.
 */
final class SettingsUpgradeResult {

	/**
	 * Builds a result object.
	 *
	 * @param array<string, mixed> $settings Canonical settings after upgrade (always populated on success).
	 * @param bool                 $should_persist Whether the caller should write {@see $settings} to the option.
	 * @param string|null          $error Human-readable failure reason, when present.
	 * @param int|null             $unsupported_future_version Stored schema version when newer than supported.
	 */
	private function __construct(
		private array $settings,
		private bool $should_persist,
		private ?string $error = null,
		private ?int $unsupported_future_version = null
	) {
	}

	/**
	 * Successful upgrade to canonical settings.
	 *
	 * @param array<string, mixed> $settings Sanitized settings ready to use.
	 * @param bool                 $should_persist Whether the caller should persist {@see $settings}.
	 */
	public static function success( array $settings, bool $should_persist ): self {
		return new self( $settings, $should_persist );
	}

	/**
	 * Failed upgrade with safe defaults and no persistence.
	 *
	 * @param string $error Human-readable failure reason.
	 */
	public static function failed( string $error ): self {
		return new self( Settings::defaults(), false, $error );
	}

	/**
	 * Unsupported future schema version with safe defaults and no persistence.
	 *
	 * @param int $stored_version Persisted schema version.
	 * @param int $target_version Highest schema version supported by the runner.
	 */
	public static function unsupported_future( int $stored_version, int $target_version ): self {
		return new self(
			Settings::defaults(),
			false,
			sprintf(
				/* translators: 1: stored settings schema version, 2: highest schema version supported by this plugin release. */
				__( 'Unsupported settings schema version %1$d (current plugin supports up to %2$d).', 'universal-multicurrency' ),
				$stored_version,
				$target_version
			),
			$stored_version
		);
	}

	/**
	 * Canonical settings after upgrade.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * Whether the caller should write {@see settings()} back to the option store.
	 */
	public function should_persist(): bool {
		return $this->should_persist;
	}

	/**
	 * Whether a migration step failed.
	 */
	public function is_failed(): bool {
		return null !== $this->error && null === $this->unsupported_future_version;
	}

	/**
	 * Whether the stored schema version is newer than this plugin supports.
	 */
	public function is_unsupported_future(): bool {
		return null !== $this->unsupported_future_version;
	}

	/**
	 * Human-readable failure reason, when present.
	 */
	public function error(): ?string {
		return $this->error;
	}

	/**
	 * Stored schema version when {@see is_unsupported_future()} is true.
	 */
	public function unsupported_future_version(): ?int {
		return $this->unsupported_future_version;
	}
}
