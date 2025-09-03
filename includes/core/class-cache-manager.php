<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * // UFSC: Cache and Statistics Management
 * Handles cache warming, statistics refresh, and cron jobs
 */
class UFSC_Cache_Manager {

    /**
     * Initialize cache management
     */
    public static function init() {
        // Register cron job
        add_action( 'wp', array( __CLASS__, 'schedule_cache_refresh' ) );
        add_action( 'ufsc_refresh_stats', array( __CLASS__, 'refresh_all_stats' ) );
        
        // Clear cache on relevant actions
        add_action( 'ufsc_licence_updated', array( __CLASS__, 'clear_club_cache' ) );
        add_action( 'ufsc_licence_created', array( __CLASS__, 'clear_club_cache' ) );
        add_action( 'ufsc_licence_deleted', array( __CLASS__, 'clear_club_cache' ) );
        add_action( 'ufsc_club_updated', array( __CLASS__, 'clear_club_cache' ) );
    }

    /**
     * Schedule daily cache refresh
     */
    public static function schedule_cache_refresh() {
        if ( ! wp_next_scheduled( 'ufsc_refresh_stats' ) ) {
            wp_schedule_event( time(), 'daily', 'ufsc_refresh_stats' );
        }
    }

    /**
     * Refresh all club statistics
     */
    public static function refresh_all_stats() {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        // Get all active clubs
        $clubs = $wpdb->get_col( "SELECT id FROM {$clubs_table} WHERE statut = 'active'" );
        
        foreach ( $clubs as $club_id ) {
            self::warm_club_cache( $club_id );
        }
    }

    /**
     * Warm cache for a specific club
     */
    public static function warm_club_cache( $club_id ) {
        // Base filters to pre-cache
        $filter_sets = array(
            array(), // No filters
            array( 'periode' => 30 ), // Last 30 days
            array( 'periode' => 90 ), // Last 90 days
            array( 'periode' => 365 ), // Last year
        );
        
        foreach ( $filter_sets as $filters ) {
            $cache_key_kpis = "ufsc_dashboard_kpis_{$club_id}_" . md5( serialize( $filters ) );
            $cache_key_stats = "ufsc_detailed_stats_{$club_id}_" . md5( serialize( $filters ) );
            
            // Pre-generate KPIs
            self::generate_kpis_data( $club_id, $filters, $cache_key_kpis );
            
            // Pre-generate detailed stats
            self::generate_detailed_stats_data( $club_id, $filters, $cache_key_stats );
        }
        
        // Warm documents cache
        $cache_key_docs = "ufsc_documents_{$club_id}";
        self::generate_documents_data( $club_id, $cache_key_docs );
    }

    /**
     * Clear all cache for a club
     */
    public static function clear_club_cache( $club_id ) {
        // Clear all cached data for this club
        global $wpdb;
        
        $cache_patterns = array(
            "ufsc_dashboard_kpis_{$club_id}_%",
            "ufsc_detailed_stats_{$club_id}_%",
            "ufsc_documents_{$club_id}",
            "ufsc_club_info_{$club_id}",
            "ufsc_stats_{$club_id}_%"
        );
        
        foreach ( $cache_patterns as $pattern ) {
            $transients = $wpdb->get_col( $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . str_replace( '%', '%%', $pattern )
            ) );
            
            foreach ( $transients as $transient ) {
                $key = str_replace( '_transient_', '', $transient );
                delete_transient( $key );
            }
        }
    }

    /**
     * Generate and cache KPIs data
     */
    private static function generate_kpis_data( $club_id, $filters, $cache_key ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Build WHERE clause
        $where_conditions = array( "club_id = %d" );
        $where_values = array( $club_id );
        
        if ( ! empty( $filters['periode'] ) && is_numeric( $filters['periode'] ) ) {
            $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = intval( $filters['periode'] );
        }
        
        if ( ! empty( $filters['genre'] ) ) {
            $where_conditions[] = "sexe = %s";
            $where_values[] = sanitize_text_field( $filters['genre'] );
        }
        
        $where_clause = " WHERE " . implode( " AND ", $where_conditions );
        
        // Count by status
        $sql = "SELECT statut, COUNT(*) as count
                FROM {$licences_table}
                {$where_clause}
                GROUP BY statut";
        
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        
        // Map to KPIs structure
        $kpis = array(
            'licences_validees' => 0,
            'licences_payees' => 0,
            'licences_attente' => 0,
            'licences_rejected' => 0
        );
        
        foreach ( $results as $result ) {
            switch ( $result->statut ) {
                case 'validee':
                    $kpis['licences_validees'] = intval( $result->count );
                    break;
                case 'payee':
                    $kpis['licences_payees'] = intval( $result->count );
                    break;
                case 'brouillon':
                case 'non_payee':
                    $kpis['licences_attente'] += intval( $result->count );
                    break;
                case 'rejected':
                    $kpis['licences_rejected'] = intval( $result->count );
                    break;
            }
        }
        
        // Cache for 10 minutes
        set_transient( $cache_key, $kpis, 10 * MINUTE_IN_SECONDS );
        
        return $kpis;
    }

    /**
     * Generate and cache detailed statistics
     */
    private static function generate_detailed_stats_data( $club_id, $filters, $cache_key ) {
        // This would be similar to the REST API handler but for caching
        // Implementation similar to handle_detailed_stats method
        $stats = array(
            'sexe' => array(),
            'age' => array(), 
            'competition' => array(),
            'roles' => array(),
            'evolution' => array(),
            'alerts' => array()
        );
        
        // Cache for 10 minutes
        set_transient( $cache_key, $stats, 10 * MINUTE_IN_SECONDS );
        
        return $stats;
    }

    /**
     * Generate and cache documents data
     */
    private static function generate_documents_data( $club_id, $cache_key ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        $sql = "SELECT doc_statuts, doc_recepisse, doc_jo, doc_pv_ag, doc_cer, doc_attestation_cer
                FROM {$clubs_table}
                WHERE id = %d";
        
        $documents = $wpdb->get_row( $wpdb->prepare( $sql, $club_id ), ARRAY_A );
        
        // Cache for 30 minutes (documents don't change often)
        set_transient( $cache_key, $documents ?: array(), 30 * MINUTE_IN_SECONDS );
        
        return $documents;
    }

    /**
     * Get cache statistics
     */
    public static function get_cache_stats() {
        global $wpdb;
        
        $total_transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_ufsc_%'"
        );
        
        $ufsc_transients = $wpdb->get_results(
            "SELECT option_name, 
                    CHAR_LENGTH(option_value) as size_bytes,
                    FROM_UNIXTIME(option_value) as expires
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_ufsc_%'
             ORDER BY expires DESC",
            ARRAY_A
        );
        
        return array(
            'total_count' => intval( $total_transients ),
            'details' => $ufsc_transients
        );
    }

    /**
     * Clear all UFSC caches
     */
    public static function clear_all_cache() {
        global $wpdb;
        
        // Delete all UFSC transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_ufsc_%' 
             OR option_name LIKE '_transient_timeout_ufsc_%'"
        );
        
        return true;
    }
}