<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only helpers for season archives.
 *
 * Schema ownership intentionally remains in UFSC_DB_Migrations so this manager
 * does not create or rename licence columns used by UFSC Gestion or by the
 * separate Licence & Compétition plugin.
 */
class UFSC_Season_Archive_Manager {

    /**
     * Return the annual affiliations table name.
     *
     * @return string
     */
    public static function get_affiliations_table() {
        global $wpdb;

        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'get_affiliation_seasons_table_name' ) ) {
            return UFSC_DB_Migrations::get_affiliation_seasons_table_name();
        }

        // Safe bootstrap fallback; the migration class remains the schema owner.
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    /**
     * Ensure archive storage exists by delegating to the migration owner.
     *
     * @return void
     */
    public static function maybe_migrate() {
        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'ensure_season_archive_tables' ) ) {
            UFSC_DB_Migrations::ensure_season_archive_tables();
        }
    }

    /**
     * Licence lineage columns are owned by UFSC_DB_Migrations.
     *
     * @return string[] Existing lineage columns for compatibility reads.
     */
    public static function get_existing_licence_lineage_columns() {
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return array();
        }

        $table   = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        return array_values( array_intersect( array( 'previous_licence_id', 'renewed_from_licence_id' ), $columns ) );
    }
}
