<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Safety guard for additive FFST club-profile columns.
 *
 * This module is deliberately limited to schema/form safety. It does not touch
 * WooCommerce, licence quotas, renewals, cart behaviour or affiliation logic.
 */
final class UFSC_FFST_Club_Profile_Schema_Guard {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'repair_missing_columns' ), 6 );
        // Second production-safe pass on admin requests. Some installs may reach
        // the club field registry before a previous schema repair has taken effect.
        add_action( 'admin_init', array( __CLASS__, 'repair_missing_columns' ), 1 );
        add_filter( 'ufsc_club_fields', array( __CLASS__, 'filter_unavailable_fields' ), 1000 );
    }

    private static function table_name() {
        if ( ! class_exists( 'UFSC_SQL' ) ) { return ''; }
        $settings = UFSC_SQL::get_settings();
        return isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
    }

    private static function actual_columns( $table ) {
        global $wpdb;
        if ( '' === $table ) { return array(); }
        return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function definitions() {
        $defs = array(
            'numero_affiliation_ffst'  => "varchar(100) NULL DEFAULT NULL",
            'date_agrement_js'         => "date NULL DEFAULT NULL",
            'numero_agrement_js'       => "varchar(120) NULL DEFAULT NULL",
            'adresse_salle'            => "varchar(255) NULL DEFAULT NULL",
            'complement_adresse_salle' => "varchar(255) NULL DEFAULT NULL",
            'code_postal_salle'        => "varchar(20) NULL DEFAULT NULL",
            'ville_salle'              => "varchar(120) NULL DEFAULT NULL",
            'disciplines_ffst'         => "text NULL",
            'codes_disciplines_ffst'   => "varchar(255) NULL DEFAULT NULL",
            'correspondant_nom'        => "varchar(120) NULL DEFAULT NULL",
            'correspondant_prenom'     => "varchar(120) NULL DEFAULT NULL",
            'correspondant_tel'        => "varchar(60) NULL DEFAULT NULL",
            'correspondant_email'      => "varchar(190) NULL DEFAULT NULL",
            'signataire_nom'           => "varchar(120) NULL DEFAULT NULL",
            'signataire_prenom'        => "varchar(120) NULL DEFAULT NULL",
            'signataire_qualite'       => "varchar(120) NULL DEFAULT NULL",
        );

        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur' ) as $prefix ) {
            if ( 'entraineur' === $prefix ) {
                $defs[ $prefix . '_adresse' ] = "varchar(255) NULL DEFAULT NULL";
            }
            $defs[ $prefix . '_complement_adresse' ] = "varchar(255) NULL DEFAULT NULL";
            $defs[ $prefix . '_code_postal' ]        = "varchar(20) NULL DEFAULT NULL";
            $defs[ $prefix . '_ville' ]              = "varchar(120) NULL DEFAULT NULL";
            $defs[ $prefix . '_pere_nom_prenom' ]    = "varchar(255) NULL DEFAULT NULL";
            $defs[ $prefix . '_mere_nom_prenom' ]    = "varchar(255) NULL DEFAULT NULL";
        }

        return $defs;
    }

    public static function repair_missing_columns() {
        global $wpdb;
        $table = self::table_name();
        if ( '' === $table ) { return; }

        $known = self::actual_columns( $table );
        if ( ! $known ) { return; }

        $changed = false;
        foreach ( self::definitions() as $column => $definition ) {
            if ( in_array( $column, $known, true ) ) { continue; }
            $result = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( false !== $result ) {
                $known[] = $column;
                $changed = true;
            }
        }

        if ( $changed && function_exists( 'ufsc_flush_table_columns_cache' ) ) {
            ufsc_flush_table_columns_cache();
        }
    }

    public static function filter_unavailable_fields( $fields ) {
        $fields = is_array( $fields ) ? $fields : array();

        // Last-chance repair immediately before the canonical field registry is
        // filtered. This guarantees that a missing additive column such as
        // tresorier_ville is created before the admin form decides whether to
        // render it. The repair is idempotent and never alters existing values.
        self::repair_missing_columns();

        $table  = self::table_name();
        $known  = self::actual_columns( $table );
        if ( ! $known ) { return $fields; }

        foreach ( array_keys( self::definitions() ) as $field ) {
            if ( ! in_array( $field, $known, true ) ) {
                unset( $fields[ $field ] );
            }
        }

        return $fields;
    }
}
