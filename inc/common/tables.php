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

/**
 * UFSC PATCH: Cached table columns helper to avoid repeated DESCRIBE calls.
 *
 * @param string $table_name Table name to inspect.
 * @param bool   $force      Force refresh cache.
 * @return array Column names.
 */
function ufsc_table_columns( $table_name, $force = false ) {
    global $wpdb;

    $sanitized_table = ufsc_sanitize_table_name( $table_name );
    if ( empty( $sanitized_table ) ) {
        return array();
    }

    $cache_key = 'ufsc_table_columns_' . md5( $sanitized_table );
    $group     = 'ufsc_table_columns';

    if ( ! $force ) {
        $cached = wp_cache_get( $cache_key, $group );
        if ( false !== $cached ) {
            return is_array( $cached ) ? $cached : array();
        }

        $cached = get_transient( $cache_key );
        if ( false !== $cached ) {
            wp_cache_set( $cache_key, $cached, $group, 10 * MINUTE_IN_SECONDS );
            return is_array( $cached ) ? $cached : array();
        }
    }

    $columns = $wpdb->get_col( "DESCRIBE `{$sanitized_table}`" );
    if ( empty( $columns ) || ! is_array( $columns ) ) {
        $columns = array();
    }

    wp_cache_set( $cache_key, $columns, $group, 10 * MINUTE_IN_SECONDS );
    set_transient( $cache_key, $columns, 10 * MINUTE_IN_SECONDS );

    return $columns;
}

/**
 * UFSC PATCH: Flush cached table columns.
 *
 * @param string|null $table_name Optional table to flush.
 * @return void
 */
function ufsc_flush_table_columns_cache( $table_name = null ) {
    $group = 'ufsc_table_columns';

    if ( $table_name ) {
        $sanitized_table = ufsc_sanitize_table_name( $table_name );
        if ( ! $sanitized_table ) {
            return;
        }
        $cache_key = 'ufsc_table_columns_' . md5( $sanitized_table );
        wp_cache_delete( $cache_key, $group );
        delete_transient( $cache_key );
        return;
    }

    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
}
