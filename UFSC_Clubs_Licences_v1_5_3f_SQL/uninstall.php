<?php
/**
 * Uninstall script for UFSC Clubs & Licences plugin
 * This file is called when the plugin is deleted
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option('ufsc_sql_settings');
delete_option('ufsc_sql_db_version');

// Check if we should delete tables
// Only delete tables if UFSC_FORCE_DELETE_TABLES constant is defined
if ( defined('UFSC_FORCE_DELETE_TABLES') && UFSC_FORCE_DELETE_TABLES ) {
    global $wpdb;
    
    // Get table names
    $clubs_table = $wpdb->prefix . 'ufsc_clubs';
    $licences_table = $wpdb->prefix . 'ufsc_licences';
    
    // Drop tables
    $wpdb->query("DROP TABLE IF EXISTS `$licences_table`");
    $wpdb->query("DROP TABLE IF EXISTS `$clubs_table`");
}

// Clear any cached data
wp_cache_flush();