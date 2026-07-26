<?php
/**
 * Per-user, fingerprint-keyed dismissal persistence for conflict notices.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * The only Diagnostics class that writes persistent state. Stores dismissal
 * timestamps in the acting user's meta, capped and expired so the map cannot
 * grow without bound or hide warnings forever.
 */
final class NoticeDismissal {

	public const META_KEY = 'umc_dismissed_notices';

	public const QUERY_ARG = 'umc-dismiss';

	public const MAX_ENTRIES = 20;

	private const SECONDS_PER_DAY = 86400;

	public const EXPIRY_SECONDS = 180 * self::SECONDS_PER_DAY;

	/**
	 * Memoized conflict detector supplying the authoritative fingerprint.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $detector;

	/**
	 * Binds dismissal handling to a conflict detector.
	 *
	 * @param ConflictDetector $detector Memoized conflict detector.
	 */
	public function __construct( ConflictDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Registers the admin-init dismiss handler.
	 */
	public function register(): void {
		\add_action( 'admin_init', array( $this, 'maybe_dismiss' ), 10 );
	}

	/**
	 * Handles a nonce'd GET dismiss request and redirects on success.
	 */
	public function maybe_dismiss(): void {
		$redirect = $this->try_dismiss_from_request();

		if ( null === $redirect ) {
			return;
		}

		\wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Validates a dismiss request, persists the current fingerprint, and returns
	 * the post-redirect URL. Returns null when no dismiss action was taken.
	 */
	public function try_dismiss_from_request(): ?string {
		if ( \wp_doing_ajax() ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated below with check_admin_referer().
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return null;
		}

		if ( ! \current_user_can( $this->required_capability() ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated below with check_admin_referer().
		$submitted = \sanitize_key( \wp_unslash( (string) $_GET[ self::QUERY_ARG ] ) );

		if ( '' === $submitted ) {
			return null;
		}

		\check_admin_referer( 'umc_dismiss_' . $submitted );

		$current = $this->detector->fingerprint();

		if ( '' === $current || ! \hash_equals( $current, $submitted ) ) {
			return null;
		}

		$this->persist_dismissal( $current );

		return \remove_query_arg(
			array( self::QUERY_ARG, '_wpnonce' )
		);
	}

	/**
	 * Whether the acting user has dismissed the given fingerprint recently.
	 *
	 * @param string $fingerprint Current conflict fingerprint.
	 */
	public function is_dismissed( string $fingerprint ): bool {
		if ( '' === $fingerprint ) {
			return false;
		}

		$user_id = \get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$stored = \get_user_meta( $user_id, self::META_KEY, true );
		$stored = self::sanitize_storage( is_array( $stored ) ? $stored : array(), \time() );

		return isset( $stored[ $fingerprint ] );
	}

	/**
	 * Normalises stored dismissal rows: valid keys only, expiry enforced, cap applied.
	 *
	 * @param array<string, mixed> $stored Raw user-meta map.
	 * @param int                  $now    Current unix timestamp.
	 *
	 * @return array<string, int> fingerprint => dismissed_at
	 */
	public static function sanitize_storage( array $stored, int $now ): array {
		$clean = array();

		foreach ( $stored as $fingerprint => $dismissed_at ) {
			if ( ! is_string( $fingerprint ) || ! self::is_valid_fingerprint( $fingerprint ) ) {
				continue;
			}

			$at = (int) $dismissed_at;

			if ( $at <= 0 || ( $now - $at ) > self::EXPIRY_SECONDS ) {
				continue;
			}

			$clean[ $fingerprint ] = $at;
		}

		if ( count( $clean ) <= self::MAX_ENTRIES ) {
			return $clean;
		}

		\asort( $clean, SORT_NUMERIC );

		return \array_slice( $clean, -self::MAX_ENTRIES, null, true );
	}

	/**
	 * Returns the stored map with one fingerprint dismissed at `$now`.
	 *
	 * @param array<string, int> $stored      Existing dismissal map.
	 * @param string             $fingerprint Fingerprint to record.
	 * @param int                $now         Current unix timestamp.
	 *
	 * @return array<string, int>
	 */
	public static function with_dismissal( array $stored, string $fingerprint, int $now ): array {
		if ( ! self::is_valid_fingerprint( $fingerprint ) ) {
			return self::sanitize_storage( $stored, $now );
		}

		$stored[ $fingerprint ] = $now;

		return self::sanitize_storage( $stored, $now );
	}

	/**
	 * Whether a fingerprint string matches the detector's output shape.
	 *
	 * @param string $fingerprint Candidate fingerprint.
	 */
	public static function is_valid_fingerprint( string $fingerprint ): bool {
		return 1 === \preg_match( '/^[a-f0-9]{16}$/', $fingerprint );
	}

	/**
	 * Capability required to dismiss on the current admin surface.
	 */
	private function required_capability(): string {
		return \is_network_admin() ? 'manage_network_plugins' : 'activate_plugins';
	}

	/**
	 * Writes the dismissal for the current user.
	 *
	 * @param string $fingerprint Authoritative current conflict fingerprint.
	 */
	private function persist_dismissal( string $fingerprint ): void {
		$user_id = \get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		$stored = \get_user_meta( $user_id, self::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();
		$stored = self::with_dismissal( $stored, $fingerprint, \time() );

		\update_user_meta( $user_id, self::META_KEY, $stored );
	}
}
