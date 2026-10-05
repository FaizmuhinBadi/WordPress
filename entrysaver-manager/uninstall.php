<?php
/**
 * EntrySaver Manager Uninstall Handler
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop plugin database table
$table_name = $wpdb->prefix . 'entrma_entries';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Delete plugin options & user meta
delete_option('entrma_db_version');
delete_option('entrma_settings');

// Clean user column preferences
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'entrma_cols_%'");
