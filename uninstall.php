<?php
/**
 * Uninstall handler.
 *
 * WordPress calls this file directly when the admin deletes the plugin.
 * It is NOT called on deactivation.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Clear core settings configurations
delete_option('wc_bpm_action');
delete_option('wc_bpm_type');
delete_option('wc_bpm_amount');
delete_option('wc_bpm_exclude');

// Drop custom audit trail tracking table cleanly
$table_name = $wpdb->prefix . 'wc_bulk_price_manager';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );