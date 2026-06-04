<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tables helper module for UFSC Gestion
 * Provides functions to get and validate table names
 */

/**
 * Get legacy UFSC Gestion table settings.
 *
 * This is kept as a non-destructive fallback for installations that were
 * configured before the SQL admin settings became the canonical source.
 *
 * @return array{clubs_table:string,licences_table:string,_source:string} Table names.
 */
function ufsc_get_legacy_table_names() {
    global $wpdb;

    $options = get_option( 'ufsc_gestion_settings', array() );
    if ( ! is_array( $options ) ) {
        $options = array();
    }

    $defaults = array(
        'clubs_table'    => $wpdb->prefix . 'ufsc_clubs',
        'licences_table' => $wpdb->prefix . 'ufsc_licences',
    );

    $tables = wp_parse_args( $options, $defaults );
    $tables['clubs_table']    = ufsc_sanitize_table_name( $tables['clubs_table'] ?? '' );
    $tables['licences_table'] = ufsc_sanitize_table_name( $tables['licences_table'] ?? '' );

    if ( '' === $tables['clubs_table'] ) {
        $tables['clubs_table'] = $defaults['clubs_table'];
    }
    if ( '' === $tables['licences_table'] ) {
        $tables['licences_table'] = $defaults['licences_table'];
    }

    $tables['_source'] = 'ufsc_gestion_settings';

    return $tables;
}

/**
 * Get the canonical UFSC Gestion table names.
 *
 * The unified admin uses UFSC_SQL::get_settings(), therefore this helper uses
 * the same settings as the canonical source when available. It does not write
 * options or migrate data; it only resolves the table names read by admin,
 * front-end and club mapping code.
 *
 * @return array{clubs_table:string,licences_table:string,_source:string} Table names and source.
 */
function ufsc_get_table_names() {
    $fallback = ufsc_get_legacy_table_names();

    if ( class_exists( 'UFSC_SQL' ) && is_callable( array( 'UFSC_SQL', 'get_settings' ) ) ) {
        $settings = UFSC_SQL::get_settings();
        if ( is_array( $settings ) ) {
            $clubs_table    = ufsc_sanitize_table_name( $settings['table_clubs'] ?? '' );
            $licences_table = ufsc_sanitize_table_name( $settings['table_licences'] ?? '' );

            if ( '' !== $clubs_table && '' !== $licences_table ) {
                return array(
                    'clubs_table'    => $clubs_table,
                    'licences_table' => $licences_table,
                    '_source'        => 'ufsc_sql_settings',
                );
            }
        }
    }

    return $fallback;
}

/**
 * Return a non-sensitive diagnostic about the resolved UFSC data tables.
 *
 * @return array{clubs_table:string,licences_table:string,source:string}
 */
function ufsc_get_table_diagnostic() {
    $tables = ufsc_get_table_names();

    return array(
        'clubs_table'    => $tables['clubs_table'] ?? '',
        'licences_table' => $tables['licences_table'] ?? '',
        'source'         => $tables['_source'] ?? 'unknown',
    );
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

/**
 * UFSC PATCH: Check if a column exists using cached columns list.
 *
 * @param string $table_name Table name.
 * @param string $column     Column name.
 * @return bool
 */
function ufsc_table_has_column( $table_name, $column ) {
    if ( empty( $table_name ) || empty( $column ) ) {
        return false;
    }

    $columns = ufsc_table_columns( $table_name );
    return in_array( $column, $columns, true );
}

