<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Structured, read-only UFSC storage/configuration diagnostic. */
function ufsc_get_configuration_diagnostic() {
    $clubs = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::resolve_table( 'clubs' ) : array( 'exists' => false, 'table' => '', 'rows' => 0, 'compatibility' => 'missing' );
    $licences = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::resolve_table( 'licences' ) : array( 'exists' => false, 'table' => '', 'rows' => 0, 'compatibility' => 'missing' );
    $affiliations = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::resolve_table( 'affiliations' ) : array( 'exists' => false, 'table' => '', 'rows' => 0, 'compatibility' => 'missing' );
    global $wpdb;
    $attestations_table = $wpdb->prefix . 'ufsc_attestations';
    $attestations_exists = function_exists( 'ufsc_table_exists' ) ? ufsc_table_exists( $attestations_table ) : false;

    $critical_missing = array();
    foreach ( array( 'clubs' => $clubs, 'licences' => $licences ) as $key => $info ) {
        if ( empty( $info['exists'] ) || ! in_array( $info['compatibility'], array( 'compatible', 'partial' ), true ) ) { $critical_missing[] = $key . ':' . ( $info['table'] ?? '' ); }
    }

    $optional_missing = array();
    if ( empty( $affiliations['exists'] ) ) { $optional_missing[] = $affiliations['table'] ?? $wpdb->prefix . 'ufsc_affiliations_seasons'; }
    if ( ! $attestations_exists ) { $optional_missing[] = $attestations_table; }

    $mode = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_schema_mode() : 'unknown';
    $configured = empty( $critical_missing );
    return array(
        'configured'              => $configured,
        'schema_mode'             => $mode,
        'critical_missing_tables' => $critical_missing,
        'optional_missing_tables' => $optional_missing,
        'migration_required'      => class_exists( 'UFSC_DB_Migrations' ) ? version_compare( (string) get_option( UFSC_DB_Migrations::VERSION_OPTION, '0.0.0' ), UFSC_DB_Migrations::MIGRATION_VERSION, '<' ) : false,
        'message'                 => $configured
            ? sprintf( __( 'Mode de compatibilité %1$s actif : %2$d clubs et %3$d licences retrouvés.', 'ufsc-clubs' ), $mode, (int) ( $clubs['rows'] ?? 0 ), (int) ( $licences['rows'] ?? 0 ) )
            : __( 'Aucune table clubs/licences compatible retrouvée après inventaire legacy et moderne.', 'ufsc-clubs' ),
        'diagnostic_details'      => array(
            'clubs'        => $clubs,
            'licences'     => $licences,
            'affiliations' => $affiliations,
            'attestations' => array( 'table' => $attestations_table, 'exists' => $attestations_exists, 'rows' => $attestations_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$attestations_table}`" ) : 0, 'compatibility' => $attestations_exists ? 'optional_present' : 'optional_missing' ),
        ),
        'inventory'               => class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_inventory() : array(),
    );
}
