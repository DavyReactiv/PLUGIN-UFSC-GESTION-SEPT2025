<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tables helper module for UFSC Gestion
 * Provides functions to get and validate table names
 */

/**
 * Get the configured table names
 * 
 * @return array Table names
 */
function ufsc_get_table_names() {
    global $wpdb;
    
    $options = get_option( 'ufsc_gestion_settings', array() );
    
    $defaults = array(
        'clubs_table' => $wpdb->prefix . 'ufsc_clubs',
        'licences_table' => $wpdb->prefix . 'ufsc_licences'
    );
    
    return wp_parse_args( $options, $defaults );
}

/**
 * Get the clubs table name
 * 
 * @return string Clubs table name
 */
function ufsc_get_clubs_table() {
    $tables = ufsc_get_table_names();
    return $tables['clubs_table'];
}

/**
 * Get the licences table name
 * 
 * @return string Licences table name
 */
function ufsc_get_licences_table() {
    $tables = ufsc_get_table_names();
    return $tables['licences_table'];
}

/**
 * Sanitize table name - allow only alphanumeric and underscore
 * 
 * @param string $table_name Table name to sanitize
 * @return string Sanitized table name
 */
function ufsc_sanitize_table_name( $table_name ) {
    return preg_replace( '/[^A-Za-z0-9_]/', '', $table_name );
}

/**
 * Check if a table exists in the database
 * 
 * @param string $table_name Table name to check
 * @return bool True if table exists
 */
function ufsc_table_exists( $table_name ) {
    global $wpdb;
    
    $sanitized_table = ufsc_sanitize_table_name( $table_name );
    if ( empty( $sanitized_table ) ) {
        return false;
    }
    
    $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $sanitized_table ) );
    return $result === $sanitized_table;
}