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

    /** Return one annual affiliation without consulting the permanent club row. */
    public static function get_affiliation( $club_id, $season ) {
        global $wpdb;

        $club_id = absint( $club_id );
        $season  = sanitize_text_field( (string) $season );
        if ( $club_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            return null;
        }

        $table = self::get_affiliations_table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE club_id = %d AND season = %s LIMIT 1", $club_id, $season ) );
    }

    /**
     * Record a paid annual renewal. The unique (club_id, season) key and this
     * upsert make repeated WooCommerce callbacks idempotent.
     */
    public static function record_paid_renewal( $club_id, $season, $order_id, $product_id, $previous_affiliation_id = 0 ) {
        global $wpdb;

        $club_id = absint( $club_id );
        $order_id = absint( $order_id );
        $product_id = absint( $product_id );
        $previous_affiliation_id = absint( $previous_affiliation_id );
        $season = sanitize_text_field( (string) $season );
        if ( $club_id <= 0 || $order_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            return false;
        }

        $table = self::get_affiliations_table();
        $now   = current_time( 'mysql' );
        $sql   = "INSERT INTO `{$table}` (club_id, season, status, payment_status, wc_order_id, previous_affiliation_id, product_id, request_type, requested_at, paid_at, created_at, updated_at)
            VALUES (%d, %s, 'pending_validation', 'paid', %d, NULLIF(%d, 0), %d, 'renewal', %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE payment_status = 'paid', wc_order_id = VALUES(wc_order_id), product_id = VALUES(product_id), paid_at = COALESCE(paid_at, VALUES(paid_at)), updated_at = VALUES(updated_at)";

        return false !== $wpdb->query( $wpdb->prepare( $sql, $club_id, $season, $order_id, $previous_affiliation_id, $product_id, $now, $now, $now, $now ) );
    }
}
