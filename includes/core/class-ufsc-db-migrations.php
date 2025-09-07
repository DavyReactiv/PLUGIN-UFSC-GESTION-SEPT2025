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

        $licences_table = $wpdb->prefix . 'ufsc_licences';
        $clubs_table    = $wpdb->prefix . 'ufsc_clubs';

        UFSC_SQL::update_settings([
            'table_clubs'    => $clubs_table,
            'table_licences' => $licences_table,
        ]);

        $missing_tables = [];
        if ( ! ufsc_table_exists( $clubs_table ) ) {
            $missing_tables[] = $clubs_table;
        }
        if ( ! ufsc_table_exists( $licences_table ) ) {
            $missing_tables[] = $licences_table;
        }

        if ( ! empty( $missing_tables ) ) {
            $message = sprintf(
                'UFSC – Missing required database tables: %s',
                implode( ', ', $missing_tables )
            );
            error_log( $message );

            if ( is_admin() ) {
                add_action( 'admin_notices', function() use ( $message ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
                } );
            }

            return;
        }

        self::maybe_upgrade();
        self::migrate_settings();
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
        self::migrate_settings();
    }

    /**
     * Migrate legacy settings option to the unified one and remove leftovers.
     */
    public static function migrate_settings() {
        $legacy  = get_option( 'ufsc_gestion_settings', array() );
        $current = get_option( 'ufsc_sql_settings', array() );

        if ( ! empty( $legacy ) ) {
            $current = wp_parse_args( $current, $legacy );
            update_option( 'ufsc_sql_settings', $current );
        }

        delete_option( 'ufsc_gestion_settings' );
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
