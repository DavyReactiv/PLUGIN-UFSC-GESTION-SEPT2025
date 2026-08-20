<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalize the three identifier CREATE TABLE statements before dbDelta parses
 * them. dbDelta expects one field/index definition per line; compact definitions
 * can otherwise be misread as defaults or composite primary keys on activation.
 *
 * This compatibility layer is schema-only: it does not update or delete rows.
 */
function ufsc_production_normalize_identifier_dbdelta_queries( $queries ) {
    if ( ! is_array( $queries ) ) {
        return $queries;
    }

    global $wpdb;
    $charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
    $prefix  = (string) $wpdb->prefix;

    $canonical = array(
        $prefix . 'ufsc_identifier_sequences' => "CREATE TABLE {$prefix}ufsc_identifier_sequences (\n"
            . "identifier_type varchar(20) NOT NULL,\n"
            . "next_value bigint(20) unsigned NOT NULL DEFAULT 1,\n"
            . "updated_at datetime NOT NULL,\n"
            . "PRIMARY KEY  (identifier_type)\n"
            . ") {$charset}",
        $prefix . 'ufsc_identifiers' => "CREATE TABLE {$prefix}ufsc_identifiers (\n"
            . "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
            . "identifier_type varchar(20) NOT NULL,\n"
            . "identifier_value varchar(64) NOT NULL,\n"
            . "entity_type varchar(20) NOT NULL,\n"
            . "entity_id bigint(20) unsigned NOT NULL,\n"
            . "status varchar(20) NOT NULL DEFAULT 'active',\n"
            . "created_by bigint(20) unsigned NOT NULL DEFAULT 0,\n"
            . "created_at datetime NOT NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY uniq_identifier (identifier_value),\n"
            . "UNIQUE KEY uniq_entity_identifier (identifier_type,entity_type,entity_id),\n"
            . "KEY status (status)\n"
            . ") {$charset}",
        $prefix . 'ufsc_identifier_audit' => "CREATE TABLE {$prefix}ufsc_identifier_audit (\n"
            . "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
            . "action varchar(50) NOT NULL,\n"
            . "entity_type varchar(20) NOT NULL,\n"
            . "entity_id bigint(20) unsigned NOT NULL,\n"
            . "old_value varchar(64) NOT NULL DEFAULT '',\n"
            . "new_value varchar(64) NOT NULL DEFAULT '',\n"
            . "user_id bigint(20) unsigned NOT NULL DEFAULT 0,\n"
            . "season varchar(20) NOT NULL DEFAULT '',\n"
            . "justification varchar(255) NOT NULL DEFAULT '',\n"
            . "created_at datetime NOT NULL,\n"
            . "PRIMARY KEY  (id),\n"
            . "KEY entity (entity_type,entity_id),\n"
            . "KEY action (action)\n"
            . ") {$charset}",
    );

    foreach ( $queries as $index => $query ) {
        if ( ! is_string( $query ) ) {
            continue;
        }

        foreach ( $canonical as $table => $normalized_query ) {
            if ( preg_match( '/^\s*CREATE\s+TABLE\s+' . preg_quote( $table, '/' ) . '\s*\(/i', $query ) ) {
                $queries[ $index ] = $normalized_query;
                break;
            }
        }
    }

    return $queries;
}
add_filter( 'dbdelta_queries', 'ufsc_production_normalize_identifier_dbdelta_queries', 1 );
