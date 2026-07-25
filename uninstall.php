<?php
/**
 * Uninstall handler.
 *
 * Removes plugin settings only. Order snapshot meta (_umc_*) is intentionally
 * preserved forever: exchange-rate snapshots are permanent order data
 * (see docs/adr/0001-single-stock-base-price-model.md).
 *
 * @package UniversalMulticurrency
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'umc_settings' );
