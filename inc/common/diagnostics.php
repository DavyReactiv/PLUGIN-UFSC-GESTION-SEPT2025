<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Return a structured, non-writing UFSC data configuration diagnostic. */
function ufsc_get_configuration_diagnostic() {
    global $wpdb;

    $settings = class_exists( 'UFSC_SQL' ) ? UFSC_SQL::get_settings() : array();
    $prefix   = isset( $wpdb->prefix ) ? (string) $wpdb->prefix : '';
    $tables   = array(
        'clubs'        => $settings['table_clubs'] ?? 'clubs',
        'licences'     => $settings['table_licences'] ?? 'licences',
        'affiliations' => class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliations_table() : $prefix . 'ufsc_affiliations_seasons',
        'attestations' => $prefix . 'ufsc_attestations',
    );

    foreach ( $tables as $key => $table ) {
        $table = (string) $table;
        if ( '' !== $prefix && 0 !== strpos( $table, $prefix ) && 0 !== strpos( $table, 'wp_' ) ) {
            $table = $prefix . $table;
        }
        $tables[ $key ] = function_exists( 'ufsc_sanitize_table_name' ) ? ufsc_sanitize_table_name( $table ) : preg_replace( '/[^A-Za-z0-9_]/', '', $table );
    }

    $critical = array( 'clubs', 'licences' );
    $optional = array( 'affiliations', 'attestations' );
    $details  = array();
    $critical_missing = array();
    $optional_missing = array();

    foreach ( $tables as $key => $table ) {
        $exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
        $count  = null;
        if ( $exists ) {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        }
        $details[ $key ] = array( 'table' => $table, 'exists' => $exists, 'rows' => $count );
        if ( ! $exists && in_array( $key, $critical, true ) ) { $critical_missing[] = $table; }
        if ( ! $exists && in_array( $key, $optional, true ) ) { $optional_missing[] = $table; }
    }

    $migration_required = false;
    if ( class_exists( 'UFSC_DB_Migrations' ) ) {
        $migration_required = version_compare( (string) get_option( UFSC_DB_Migrations::VERSION_OPTION, '0.0.0' ), UFSC_DB_Migrations::MIGRATION_VERSION, '<' );
    }

    $configured = empty( $critical_missing );
    $message = $configured
        ? __( 'Tables principales disponibles. Les données clubs/licences peuvent être affichées.', 'ufsc-clubs' )
        : __( 'Tables critiques clubs/licences absentes : configuration requise.', 'ufsc-clubs' );

    return array(
        'configured'              => $configured,
        'critical_missing_tables' => $critical_missing,
        'optional_missing_tables' => $optional_missing,
        'migration_required'      => $migration_required,
        'message'                 => $message,
        'diagnostic_details'      => $details,
    );
}
