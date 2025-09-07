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

        // Drop existing foreign key to allow dbDelta to run safely.
        $fk_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = "fk_licence_club"',
                $wpdb->dbname,
                $licences_table
            )
        );
        if ( $fk_exists ) {
            $wpdb->query( "ALTER TABLE {$licences_table} DROP FOREIGN KEY fk_licence_club" );
        }

        // Ensure referenced columns use the same data type before recreating the foreign key.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $licences_table ) ) === $licences_table ) {
            $wpdb->query( "ALTER TABLE {$licences_table} MODIFY club_id BIGINT(20) UNSIGNED NOT NULL" );
        }

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $clubs_table ) ) === $clubs_table ) {
            $wpdb->query( "ALTER TABLE {$clubs_table} MODIFY id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT" );
        }

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
            statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
            profile_photo_url VARCHAR(255) NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $clubs_sql );
        self::maybe_add_column( $clubs_table, 'statut', "VARCHAR(20) NOT NULL DEFAULT 'en_attente'" );
        self::ensure_statut_default( $clubs_table );

        // Recreate the foreign key if it does not exist after the migrations.
        $fk_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = "fk_licence_club"',
                $wpdb->dbname,
                $licences_table
            )
        );
        if ( ! $fk_exists ) {
            $wpdb->query( "ALTER TABLE {$licences_table} ADD CONSTRAINT fk_licence_club FOREIGN KEY (club_id) REFERENCES {$clubs_table}(id)" );
        }

        self::maybe_upgrade();
    }

    /**
     * Upgrade existing tables to match current schema.
     */
    public static function maybe_upgrade() {
        global $wpdb;

        $licences_table = $wpdb->prefix . 'ufsc_licences';
        $clubs_table    = $wpdb->prefix . 'ufsc_clubs';

        // Ensure required columns exist.
        self::maybe_add_column( $licences_table, 'status', "VARCHAR(20) NOT NULL DEFAULT 'en_attente'" );
        self::maybe_add_column( $licences_table, 'paid', 'TINYINT(1) NOT NULL DEFAULT 0' );
        self::maybe_add_column( $licences_table, 'gender', 'VARCHAR(1) NULL' );
        self::maybe_add_column( $licences_table, 'practice', 'VARCHAR(20) NULL' );
        self::maybe_add_column( $licences_table, 'birthdate', 'DATE NULL' );

        self::maybe_add_column( $clubs_table, 'statut', "VARCHAR(20) NOT NULL DEFAULT 'en_attente'" );
        self::maybe_add_column( $clubs_table, 'profile_photo_url', 'VARCHAR(255) NULL' );
        self::ensure_statut_default( $clubs_table );

        // Ensure indexes for performant queries.
        self::maybe_add_index( $licences_table, 'club_id' );
        self::maybe_add_index( $licences_table, 'status' );
        self::maybe_add_index( $licences_table, 'gender' );
        self::maybe_add_index( $licences_table, 'practice' );
        self::maybe_add_index( $licences_table, 'birthdate' );

        update_option( self::OPTION_KEY, self::VERSION );
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

    /**
     * Ensure the statut column has the correct default and normalize existing values.
     */
    private static function ensure_statut_default( $clubs_table ) {
        global $wpdb;
        $wpdb->query( "ALTER TABLE {$clubs_table} MODIFY statut VARCHAR(20) NOT NULL DEFAULT 'en_attente'" );
        $wpdb->query( "UPDATE {$clubs_table} SET statut='en_attente' WHERE statut='inactive' OR statut IS NULL" );
    }
}
