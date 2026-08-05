<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Central read-only resolver for UFSC legacy/modern storage.
 *
 * It never creates, migrates, truncates or updates data. It discovers the
 * populated historical tables first, then falls back to configured names so
 * legacy installations remain readable while modern installs still work.
 */
class UFSC_Storage_Resolver {
    const MODE_OPTION = 'ufsc_storage_schema_mode';

    private static $cache = array();

    public static function reset_cache() { self::$cache = array(); }

    public static function get_schema_mode() {
        $configured = get_option( self::MODE_OPTION, '' );
        if ( in_array( $configured, array( 'legacy', 'hybrid', 'modern' ), true ) ) {
            return $configured;
        }
        $clubs    = self::resolve_table( 'clubs' );
        $licences = self::resolve_table( 'licences' );
        if ( ! empty( $clubs['source'] ) && ! empty( $licences['source'] ) && ( false !== strpos( $clubs['source'], 'legacy' ) || false !== strpos( $licences['source'], 'legacy' ) ) ) {
            return 'hybrid';
        }
        return 'modern';
    }

    public static function get_clubs_table() { $r = self::resolve_table( 'clubs' ); return $r['table']; }
    public static function get_licences_table() { $r = self::resolve_table( 'licences' ); return $r['table']; }
    public static function get_annual_affiliations_table() { $r = self::resolve_table( 'affiliations' ); return $r['table']; }

    public static function table_exists( $table ) {
        global $wpdb;
        $table = self::sanitize_table_name( $table );
        return '' !== $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    public static function column_exists( $table, $column ) {
        return in_array( (string) $column, self::get_columns( $table ), true );
    }

    public static function get_columns( $table ) {
        global $wpdb;
        $table = self::sanitize_table_name( $table );
        if ( '' === $table || ! self::table_exists( $table ) ) { return array(); }
        $key = 'columns:' . $table;
        if ( isset( self::$cache[ $key ] ) ) { return self::$cache[ $key ]; }
        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        self::$cache[ $key ] = is_array( $columns ) ? $columns : array();
        return self::$cache[ $key ];
    }

    public static function resolve_table( $type ) {
        global $wpdb;
        $type = sanitize_key( (string) $type );
        if ( isset( self::$cache[ 'resolve:' . $type ] ) ) { return self::$cache[ 'resolve:' . $type ]; }

        $expected = self::expected_table_name( $type );
        $candidates = self::candidate_tables( $type, $expected );
        $best = array( 'table' => $expected, 'exists' => false, 'rows' => 0, 'source' => 'expected_missing', 'compatibility' => 'missing' );
        foreach ( $candidates as $candidate ) {
            $table = self::sanitize_table_name( $candidate['table'] );
            if ( '' === $table || ! self::table_exists( $table ) ) { continue; }
            $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
            $compatibility = self::table_compatibility( $type, $table );
            $score = ( 'compatible' === $compatibility ? 100 : ( 'partial' === $compatibility ? 50 : 0 ) ) + min( $rows, 999999 );
            $current_score = ( 'compatible' === $best['compatibility'] ? 100 : ( 'partial' === $best['compatibility'] ? 50 : 0 ) ) + min( (int) $best['rows'], 999999 );
            if ( ! $best['exists'] || $score > $current_score ) {
                $best = array( 'table' => $table, 'exists' => true, 'rows' => $rows, 'source' => $candidate['source'], 'compatibility' => $compatibility );
            }
        }
        self::$cache[ 'resolve:' . $type ] = $best;
        return $best;
    }

    public static function get_club_user_relation_source( $user_id = 0 ) {
        $result = self::resolve_club_for_user( $user_id );
        return $result['source'];
    }

    public static function resolve_club_for_user( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $empty = array( 'found' => false, 'club_id' => 0, 'source' => 'none', 'confidence' => 'none', 'diagnostic_code' => 'no_relation' );
        if ( $user_id <= 0 ) { return array_merge( $empty, array( 'diagnostic_code' => 'invalid_user' ) ); }

        $clubs_table = self::get_clubs_table();
        if ( ! self::table_exists( $clubs_table ) ) { return array_merge( $empty, array( 'diagnostic_code' => 'clubs_table_missing' ) ); }
        $pk = self::first_existing_column( $clubs_table, array( 'id', 'club_id', 'ID' ) );
        if ( '' === $pk ) { return array_merge( $empty, array( 'diagnostic_code' => 'club_pk_missing' ) ); }

        foreach ( array( 'responsable_id', 'user_id', 'owner_id', 'contact_user_id', 'wp_user_id', 'responsable_user_id' ) as $column ) {
            if ( ! self::column_exists( $clubs_table, $column ) ) { continue; }
            $club_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `{$pk}` FROM `{$clubs_table}` WHERE `{$column}` = %d AND " . self::not_deleted_sql( $clubs_table ) . " LIMIT 1", $user_id ) );
            if ( $club_id > 0 ) {
                return array( 'found' => true, 'club_id' => $club_id, 'source' => 'club_column:' . $column, 'confidence' => 'high', 'diagnostic_code' => 'explicit_column_match' );
            }
        }

        foreach ( array( 'ufsc_club_id', 'club_id', 'ufsc_user_club_id', '_ufsc_club_id', 'ufsc_managed_club_id' ) as $meta_key ) {
            $club_id = absint( get_user_meta( $user_id, $meta_key, true ) );
            if ( $club_id > 0 && self::club_exists( $club_id ) ) {
                return array( 'found' => true, 'club_id' => $club_id, 'source' => 'user_meta:' . $meta_key, 'confidence' => 'high', 'diagnostic_code' => 'explicit_user_meta_match' );
            }
        }

        $user = get_user_by( 'id', $user_id );
        if ( $user && ! empty( $user->user_email ) ) {
            foreach ( array( 'email', 'email_contact', 'contact_email' ) as $column ) {
                if ( ! self::column_exists( $clubs_table, $column ) ) { continue; }
                $club_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `{$pk}` FROM `{$clubs_table}` WHERE LOWER(`{$column}`) = LOWER(%s) AND " . self::not_deleted_sql( $clubs_table ) . " LIMIT 1", $user->user_email ) );
                if ( $club_id > 0 ) {
                    return array( 'found' => true, 'club_id' => $club_id, 'source' => 'diagnostic_email:' . $column, 'confidence' => 'diagnostic_only', 'diagnostic_code' => 'email_match_requires_admin_confirmation' );
                }
            }
        }

        return $empty;
    }

    public static function club_exists( $club_id ) {
        global $wpdb;
        $table = self::get_clubs_table();
        $pk = self::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) );
        if ( '' === $pk ) { return false; }
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT `{$pk}` FROM `{$table}` WHERE `{$pk}` = %d AND " . self::not_deleted_sql( $table ) . ' LIMIT 1', absint( $club_id ) ) );
    }

    public static function first_existing_column( $table, $columns ) {
        foreach ( (array) $columns as $column ) { if ( self::column_exists( $table, $column ) ) { return $column; } }
        return '';
    }

    public static function not_deleted_sql( $table, $alias = '' ) {
        $prefix = '' !== $alias ? self::sanitize_identifier( $alias ) . '.' : '';
        $parts = array( '1=1' );
        if ( self::column_exists( $table, 'deleted_at' ) ) { $parts[] = "({$prefix}`deleted_at` IS NULL OR {$prefix}`deleted_at` = '' OR {$prefix}`deleted_at` = '0000-00-00 00:00:00')"; }
        if ( self::column_exists( $table, 'deleted' ) ) { $parts[] = "COALESCE({$prefix}`deleted`, 0) = 0"; }
        return implode( ' AND ', $parts );
    }

    public static function get_inventory() {
        global $wpdb;
        $patterns = array( '%ufsc%club%', '%ufsc%licence%', '%ufsc%license%', '%affiliation%', '%attestation%', '%document%' );
        $tables = array();
        foreach ( $patterns as $pattern ) {
            $found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
            foreach ( (array) $found as $table ) { $tables[ $table ] = true; }
        }
        foreach ( array( self::get_clubs_table(), self::get_licences_table(), self::get_annual_affiliations_table() ) as $table ) { if ( self::table_exists( $table ) ) { $tables[ $table ] = true; } }
        $rows = array();
        foreach ( array_keys( $tables ) as $table ) {
            $columns = self::get_columns( $table );
            $rows[] = array(
                'table' => $table,
                'engine' => (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) ),
                'rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ),
                'columns' => $columns,
                'primary_key' => self::primary_key( $table ),
                'unique_indexes' => self::unique_indexes( $table ),
                'club_columns' => array_values( array_intersect( $columns, array( 'club_id', 'id_club', 'club' ) ) ),
                'user_columns' => array_values( array_intersect( $columns, array( 'user_id', 'owner_id', 'responsable_id', 'contact_user_id', 'wp_user_id' ) ) ),
                'season_columns' => array_values( array_intersect( $columns, array( 'season', 'saison', 'paid_season', 'season_end_year', 'annee' ) ) ),
                'status_columns' => array_values( array_intersect( $columns, array( 'status', 'statut', 'payment_status' ) ) ),
                'deleted_columns' => array_values( array_intersect( $columns, array( 'deleted_at', 'deleted' ) ) ),
            );
        }
        return $rows;
    }

    public static function normalize_season_reference( $value ) {
        $value = trim( str_replace( '/', '-', (string) $value ) );
        if ( '' === $value ) { return ''; }
        if ( preg_match( '/^\d{4}$/', $value ) ) { $end = (int) $value; return sprintf( '%d-%d', $end - 1, $end ); }
        if ( preg_match( '/^(\d{4})\s*-\s*(\d{2})$/', $value, $m ) ) { $start = (int) $m[1]; $end = (int) substr( (string) $start, 0, 2 ) . $m[2]; return $end === $start + 1 ? sprintf( '%d-%d', $start, $end ) : ''; }
        if ( preg_match( '/^(\d{4})-(\d{4})$/', $value, $m ) && (int) $m[2] === (int) $m[1] + 1 ) { return $m[1] . '-' . $m[2]; }
        return '';
    }

    private static function expected_table_name( $type ) {
        global $wpdb;
        $settings = get_option( 'ufsc_sql_settings', array() );
        $legacy = get_option( 'ufsc_gestion_settings', array() );
        if ( 'clubs' === $type ) { return self::prefix_table( $settings['table_clubs'] ?? ( $legacy['clubs_table'] ?? $wpdb->prefix . 'ufsc_clubs' ) ); }
        if ( 'licences' === $type ) { return self::prefix_table( $settings['table_licences'] ?? ( $legacy['licences_table'] ?? $wpdb->prefix . 'ufsc_licences' ) ); }
        if ( 'affiliations' === $type ) { return $wpdb->prefix . 'ufsc_affiliations_seasons'; }
        return $wpdb->prefix . 'ufsc_' . $type;
    }

    private static function candidate_tables( $type, $expected ) {
        global $wpdb;
        $p = $wpdb->prefix;
        $common = array( array( 'table' => $expected, 'source' => 'configured' ) );
        if ( 'clubs' === $type ) { $common = array_merge( $common, array( array( 'table' => $p . 'ufsc_clubs', 'source' => 'legacy:ufsc_clubs' ), array( 'table' => $p . 'ufsc_club', 'source' => 'legacy:ufsc_club' ), array( 'table' => $p . 'clubs', 'source' => 'legacy:clubs' ), array( 'table' => 'clubs', 'source' => 'legacy:bare_clubs' ) ) ); }
        if ( 'licences' === $type ) { $common = array_merge( $common, array( array( 'table' => $p . 'ufsc_licences', 'source' => 'legacy:ufsc_licences' ), array( 'table' => $p . 'ufsc_licenses', 'source' => 'legacy:ufsc_licenses' ), array( 'table' => $p . 'licences', 'source' => 'legacy:licences' ), array( 'table' => $p . 'licenses', 'source' => 'legacy:licenses' ), array( 'table' => 'licences', 'source' => 'legacy:bare_licences' ) ) ); }
        if ( 'affiliations' === $type ) { $common = array_merge( $common, array( array( 'table' => $p . 'ufsc_affiliations_seasons', 'source' => 'modern:annual_affiliations' ), array( 'table' => $p . 'ufsc_affiliation_seasons', 'source' => 'legacy:singular_affiliations' ), array( 'table' => $p . 'ufsc_affiliations', 'source' => 'legacy:affiliations' ) ) ); }
        return $common;
    }

    private static function table_compatibility( $type, $table ) {
        $columns = self::get_columns( $table );
        if ( 'clubs' === $type ) { return in_array( 'id', $columns, true ) && count( array_intersect( $columns, array( 'nom', 'name', 'club_name' ) ) ) ? 'compatible' : 'partial'; }
        if ( 'licences' === $type ) { return count( array_intersect( $columns, array( 'club_id', 'id_club' ) ) ) && count( array_intersect( $columns, array( 'nom', 'last_name', 'prenom' ) ) ) ? 'compatible' : 'partial'; }
        if ( 'affiliations' === $type ) { return count( array_intersect( $columns, array( 'club_id', 'id_club' ) ) ) && count( array_intersect( $columns, array( 'season', 'saison', 'season_end_year' ) ) ) ? 'compatible' : 'partial'; }
        return 'partial';
    }

    private static function prefix_table( $table ) { global $wpdb; $table = (string) $table; if ( '' !== $table && 0 !== strpos( $table, $wpdb->prefix ) && 0 !== strpos( $table, 'wp_' ) ) { $table = $wpdb->prefix . $table; } return self::sanitize_table_name( $table ); }
    private static function sanitize_table_name( $table ) { return preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table ); }
    private static function sanitize_identifier( $identifier ) { return preg_replace( '/[^A-Za-z0-9_]/', '', (string) $identifier ); }
    private static function primary_key( $table ) { global $wpdb; $rows = $wpdb->get_results( "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'" ); return array_map( static function( $r ) { return $r->Column_name; }, (array) $rows ); }
    private static function unique_indexes( $table ) { global $wpdb; $rows = $wpdb->get_results( "SHOW KEYS FROM `{$table}` WHERE Non_unique = 0 AND Key_name != 'PRIMARY'" ); $out = array(); foreach ( (array) $rows as $r ) { $out[ $r->Key_name ][] = $r->Column_name; } return $out; }
}
