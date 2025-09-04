<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Database Migrations Class
 * Handles database schema upgrades, indexing, and constraints
 */
class UFSC_DB_Migrations {

    /**
     * Current migration version
     */
    const MIGRATION_VERSION = '1.0.0';

    /**
     * Option key for tracking migration version
     */
    const VERSION_OPTION = 'ufsc_db_migration_version';

    /**
     * Run all pending migrations
     */
    public static function run_migrations() {
        $current_version = get_option( self::VERSION_OPTION, '0.0.0' );

        if ( version_compare( $current_version, self::MIGRATION_VERSION, '<' ) ) {
            self::migrate_to_innodb();
            self::create_indexes();
            self::create_unique_constraints();
            self::create_events_table();
            
            update_option( self::VERSION_OPTION, self::MIGRATION_VERSION );
            
            add_action( 'admin_notices', array( __CLASS__, 'migration_success_notice' ) );
        }
    }

    /**
     * Force tables to use InnoDB engine
     */
    public static function migrate_to_innodb() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $tables = array(
            $settings['table_clubs'],
            $settings['table_licences']
        );

        foreach ( $tables as $table ) {
            if ( self::table_exists( $table ) ) {
                $current_engine = self::get_table_engine( $table );
                
                if ( strtolower( $current_engine ) !== 'innodb' ) {
                    $sql = "ALTER TABLE `{$table}` ENGINE=InnoDB";
                    $result = $wpdb->query( $sql );
                    
                    if ( $result === false ) {
                        UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Failed to convert {$table} to InnoDB: " . $wpdb->last_error );
                    } else {
                        UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Successfully converted {$table} to InnoDB" );
                    }
                }
            }
        }
    }

    /**
     * Create performance indexes
     */
    public static function create_indexes() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        
        // Licences table indexes
        $licences_indexes = array(
            'idx_licences_statut' => 'statut',
            'idx_licences_payment_status' => 'payment_status',
            'idx_licences_date_creation' => 'date_creation',
            'idx_licences_nom_licence' => 'nom_licence',
            'idx_licences_club_id' => 'club_id',
            'idx_licences_numero_licence_delegataire' => 'numero_licence_delegataire'
        );

        self::create_table_indexes( $settings['table_licences'], $licences_indexes );

        // Clubs table indexes
        $clubs_indexes = array(
            'idx_clubs_statut' => 'statut',
            'idx_clubs_region' => 'region',
            'idx_clubs_date_creation' => 'date_creation',
            'idx_clubs_responsable_id' => 'responsable_id'
        );

        self::create_table_indexes( $settings['table_clubs'], $clubs_indexes );
    }

    /**
     * Create unique constraints with duplicate checking
     */
    public static function create_unique_constraints() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();

        // Check for duplicates in numero_licence_delegataire
        if ( self::table_exists( $settings['table_licences'] ) ) {
            $duplicates = $wpdb->get_results( "
                SELECT numero_licence_delegataire, COUNT(*) as count 
                FROM `{$settings['table_licences']}` 
                WHERE numero_licence_delegataire IS NOT NULL 
                AND numero_licence_delegataire != '' 
                GROUP BY numero_licence_delegataire 
                HAVING count > 1
            " );

            if ( empty( $duplicates ) ) {
                self::add_unique_constraint( 
                    $settings['table_licences'], 
                    'uniq_numero_licence_delegataire', 
                    'numero_licence_delegataire' 
                );
            } else {
                add_action( 'admin_notices', function() use ( $duplicates ) {
                    echo '<div class="notice notice-warning"><p>';
                    echo esc_html__( 'UFSC: Duplicates détectés dans numero_licence_delegataire. Contrainte unique non appliquée.', 'ufsc-clubs' );
                    echo ' (' . count( $duplicates ) . ' doublons)';
                    echo '</p></div>';
                } );
            }
        }

        // Check for duplicates in num_affiliation
        if ( self::table_exists( $settings['table_clubs'] ) ) {
            $duplicates = $wpdb->get_results( "
                SELECT num_affiliation, COUNT(*) as count 
                FROM `{$settings['table_clubs']}` 
                WHERE num_affiliation IS NOT NULL 
                AND num_affiliation != '' 
                GROUP BY num_affiliation 
                HAVING count > 1
            " );

            if ( empty( $duplicates ) ) {
                self::add_unique_constraint( 
                    $settings['table_clubs'], 
                    'uniq_num_affiliation', 
                    'num_affiliation' 
                );
            } else {
                add_action( 'admin_notices', function() use ( $duplicates ) {
                    echo '<div class="notice notice-warning"><p>';
                    echo esc_html__( 'UFSC: Duplicates détectés dans num_affiliation. Contrainte unique non appliquée.', 'ufsc-clubs' );
                    echo ' (' . count( $duplicates ) . ' doublons)';
                    echo '</p></div>';
                } );
            }
        }
    }

    /**
     * Create events table for idempotence tracking
     */
    public static function create_events_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ufsc_events';

        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `event_key` varchar(255) NOT NULL,
            `event_type` varchar(100) NOT NULL,
            `event_data` longtext,
            `status` varchar(50) DEFAULT 'pending',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_event_key` (`event_key`),
            KEY `idx_event_type` (`event_type`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        if ( $wpdb->last_error ) {
            UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Failed to create events table: " . $wpdb->last_error );
        }
    }

    /**
     * Helper: Check if table exists
     */
    private static function table_exists( $table_name ) {
        global $wpdb;
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $table_name 
        ) );
        
        return $result === $table_name;
    }

    /**
     * Helper: Get table engine
     */
    private static function get_table_engine( $table_name ) {
        global $wpdb;
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            DB_NAME,
            $table_name
        ) );
        
        return $result ?: 'unknown';
    }

    /**
     * Helper: Create indexes for a table
     */
    private static function create_table_indexes( $table_name, $indexes ) {
        global $wpdb;

        if ( ! self::table_exists( $table_name ) ) {
            return;
        }

        foreach ( $indexes as $index_name => $column ) {
            if ( ! self::index_exists( $table_name, $index_name ) ) {
                $sql = "ALTER TABLE `{$table_name}` ADD INDEX `{$index_name}` (`{$column}`)";
                $result = $wpdb->query( $sql );
                
                if ( $result === false ) {
                    UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Failed to create index {$index_name} on {$table_name}: " . $wpdb->last_error );
                }
            }
        }
    }

    /**
     * Helper: Check if index exists
     */
    private static function index_exists( $table_name, $index_name ) {
        global $wpdb;
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
            DB_NAME,
            $table_name,
            $index_name
        ) );
        
        return $result > 0;
    }

    /**
     * Helper: Add unique constraint
     */
    private static function add_unique_constraint( $table_name, $constraint_name, $column ) {
        global $wpdb;

        if ( ! self::constraint_exists( $table_name, $constraint_name ) ) {
            $sql = "ALTER TABLE `{$table_name}` ADD CONSTRAINT `{$constraint_name}` UNIQUE (`{$column}`)";
            $result = $wpdb->query( $sql );
            
            if ( $result === false ) {
                UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Failed to create unique constraint {$constraint_name} on {$table_name}: " . $wpdb->last_error );
            }
        }
    }

    /**
     * Helper: Check if constraint exists
     */
    private static function constraint_exists( $table_name, $constraint_name ) {
        global $wpdb;
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            DB_NAME,
            $table_name,
            $constraint_name
        ) );
        
        return $result > 0;
    }

    /**
     * Display migration success notice
     */
    public static function migration_success_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__( 'UFSC: Migrations de base de données appliquées avec succès.', 'ufsc-clubs' );
        echo '</p></div>';
    }

    /**
     * Get events table name
     */
    public static function get_events_table() {
        global $wpdb;
        return $wpdb->prefix . 'ufsc_events';
    }

    /**
     * Record an event for idempotence tracking
     */
    public static function record_event( $event_key, $event_type, $event_data = null, $status = 'pending' ) {
        global $wpdb;

        $table = self::get_events_table();
        
        $data = array(
            'event_key' => $event_key,
            'event_type' => $event_type,
            'event_data' => is_array( $event_data ) ? json_encode( $event_data ) : $event_data,
            'status' => $status
        );

        $result = $wpdb->insert( $table, $data );
        
        if ( $result === false ) {
            UFSC_Audit_Logger::log( "UFSC_DB_Migrations: Failed to record event {$event_key}: " . $wpdb->last_error );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Check if event exists
     */
    public static function event_exists( $event_key ) {
        global $wpdb;

        $table = self::get_events_table();
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM `{$table}` WHERE event_key = %s", 
            $event_key 
        ) );

        return ! is_null( $result );
    }

    /**
     * Update event status
     */
    public static function update_event_status( $event_key, $status ) {
        global $wpdb;

        $table = self::get_events_table();
        
        return $wpdb->update( 
            $table, 
            array( 'status' => $status ), 
            array( 'event_key' => $event_key ) 
        );
    }
}