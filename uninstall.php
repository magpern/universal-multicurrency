<?php
/**
 * Uninstall handler.
 *
 * Deletes plugin configuration only. All commerce audit data and per-user
 * dismissal state are preserved forever (ADR-0009).
 *
 * Must stay aligned with UMC\PersistedKeys::uninstall_deleted_option_keys().
 *
 * @package UniversalMulticurrency
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'umc_settings' );
delete_option( 'umc_rate_state' );
