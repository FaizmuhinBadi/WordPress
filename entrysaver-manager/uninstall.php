<?php
/**
 * EntrySaver Manager Uninstall Handler
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop plugin database table
$table_name = $wpdb->prefix . 'cf7_entries';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options & user meta
delete_option('cf7em_db_version');
delete_option('cf7_settings');

// Clean user column preferences
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cf7em_cols_%'");
