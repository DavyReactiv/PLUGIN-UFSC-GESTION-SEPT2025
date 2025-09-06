<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles database table creation and upgrades.
 */
class UFSC_DB_Migrations {
    /**
     * Current plugin database schema version.
     */
    const VERSION = '1.0.0';

    /**
     * Option key used to store the installed DB version.
     */
    const OPTION_KEY = 'ufsc_db_version';

    /**
     * Create initial tables and run upgrades.
     */
    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $licences_table = $wpdb->prefix . 'ufsc_licences';
        $clubs_table    = $wpdb->prefix . 'ufsc_clubs';

        $licences_sql = "CREATE TABLE {$licences_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            club_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'en_attente',
            paid TINYINT(1) NOT NULL DEFAULT 0,
            gender VARCHAR(1) NULL,
            practice VARCHAR(20) NULL,
            birthdate DATE NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $licences_sql );

        $clubs_sql = "CREATE TABLE {$clubs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            statut VARCHAR(20) NOT NULL DEFAULT 'inactive',
            profile_photo_url VARCHAR(255) NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $clubs_sql );

        self::maybe_upgrade();

        update_option( self::OPTION_KEY, self::VERSION );
    }

    /**
     * Upgrade existing tables to match current schema.
     */
    public static function maybe_upgrade() {
        global $wpdb;

        $installed = get_option( self::OPTION_KEY );

        $licences_table = $wpdb->prefix . 'ufsc_licences';
        $clubs_table    = $wpdb->prefix . 'ufsc_clubs';

        // Ensure required columns exist.
        self::maybe_add_column( $licences_table, 'status', "VARCHAR(20) NOT NULL DEFAULT 'en_attente'" );
        self::maybe_add_column( $licences_table, 'paid', 'TINYINT(1) NOT NULL DEFAULT 0' );
        self::maybe_add_column( $licences_table, 'gender', 'VARCHAR(1) NULL' );
        self::maybe_add_column( $licences_table, 'practice', 'VARCHAR(20) NULL' );
        self::maybe_add_column( $licences_table, 'birthdate', 'DATE NULL' );

        self::maybe_add_column( $clubs_table, 'statut', "VARCHAR(20) NOT NULL DEFAULT 'inactive'" );
        self::maybe_add_column( $clubs_table, 'profile_photo_url', 'VARCHAR(255) NULL' );

        // Ensure indexes for performant queries.
        self::maybe_add_index( $licences_table, 'club_id' );
        self::maybe_add_index( $licences_table, 'status' );
        self::maybe_add_index( $licences_table, 'gender' );
        self::maybe_add_index( $licences_table, 'practice' );
        self::maybe_add_index( $licences_table, 'birthdate' );

        if ( version_compare( $installed, self::VERSION, '<' ) ) {
            update_option( self::OPTION_KEY, self::VERSION );
        }
    }

    /**
     * Add a column to a table if it does not already exist.
     */
    private static function maybe_add_column( $table, $column, $ddl ) {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) );
        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE `$table` ADD `$column` $ddl" );
        }
    }

    /**
     * Add an index to a table if it does not already exist.
     */
    private static function maybe_add_index( $table, $column ) {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM `$table` WHERE Key_name = %s", $column ) );
        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE `$table` ADD INDEX `$column` (`$column`)" );
        }
    }
}
