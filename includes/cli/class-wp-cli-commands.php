<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WP-CLI commands for UFSC management
 * 
 * Usage examples:
 * wp ufsc stats --club-id=5
 * wp ufsc cache purge
 * wp ufsc export csv --club-id=5
 * wp ufsc audit cleanup --days=365
 */
class UFSC_CLI_Commands {

    /**
     * Get club statistics
     * 
     * ## OPTIONS
     * 
     * [--club-id=<club_id>]
     * : Club ID to get stats for
     * 
     * [--season=<season>]
     * : Season to get stats for (default: current season)
     * 
     * [--format=<format>]
     * : Output format (table, json, csv)
     * 
     * ## EXAMPLES
     * 
     *     wp ufsc stats --club-id=5
     *     wp ufsc stats --club-id=5 --season=2025-2026 --format=json
     */
    public function stats( $args, $assoc_args ) {
        $club_id = isset( $assoc_args['club-id'] ) ? (int) $assoc_args['club-id'] : null;
        $season = isset( $assoc_args['season'] ) ? $assoc_args['season'] : null;
        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

        if ( ! $season ) {
            $wc_settings = ufsc_get_woocommerce_settings();
            $season = $wc_settings['season'];
        }

        if ( $club_id ) {
            // Get stats for specific club
            $stats = $this->get_club_stats( $club_id, $season );
            $stats['club_id'] = $club_id;
            $stats['season'] = $season;

            WP_CLI\Utils\format_items( $format, array( $stats ), array( 'club_id', 'season', 'total_licences', 'paid_licences', 'validated_licences', 'quota_remaining' ) );
        } else {
            // Get stats for all clubs
            $all_stats = $this->get_all_clubs_stats( $season );
            
            if ( empty( $all_stats ) ) {
                WP_CLI::warning( 'No club statistics found.' );
                return;
            }

            WP_CLI\Utils\format_items( $format, $all_stats, array( 'club_id', 'club_name', 'total_licences', 'paid_licences', 'validated_licences', 'quota_remaining' ) );
        }

        WP_CLI::success( "Statistics retrieved for season: {$season}" );
    }

    /**
     * Manage cache operations
     * 
     * ## OPTIONS
     * 
     * <action>
     * : Action to perform (purge, info)
     * 
     * [--club-id=<club_id>]
     * : Specific club ID to purge cache for
     * 
     * [--season=<season>]
     * : Specific season to purge cache for
     * 
     * ## EXAMPLES
     * 
     *     wp ufsc cache purge
     *     wp ufsc cache purge --club-id=5
     *     wp ufsc cache info
     */
    public function cache( $args, $assoc_args ) {
        $action = $args[0] ?? '';
        $club_id = isset( $assoc_args['club-id'] ) ? (int) $assoc_args['club-id'] : null;
        $season = isset( $assoc_args['season'] ) ? $assoc_args['season'] : null;

        switch ( $action ) {
            case 'purge':
                $this->purge_cache( $club_id, $season );
                break;

            case 'info':
                $this->cache_info();
                break;

            default:
                WP_CLI::error( 'Invalid action. Use: purge, info' );
        }
    }

    /**
     * Manage audit logs
     * 
     * ## OPTIONS
     * 
     * <action>
     * : Action to perform (cleanup, stats, list)
     * 
     * [--days=<days>]
     * : Number of days to keep logs (for cleanup)
     * 
     * [--limit=<limit>]
     * : Number of recent logs to show (for list)
     * 
     * [--format=<format>]
     * : Output format (table, json, csv)
     * 
     * ## EXAMPLES
     * 
     *     wp ufsc audit cleanup --days=365
     *     wp ufsc audit stats
     *     wp ufsc audit list --limit=20
     */
    public function audit( $args, $assoc_args ) {
        $action = $args[0] ?? '';
        $days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : 365;
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
        $format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

        if ( ! class_exists( 'UFSC_Audit_Logger' ) ) {
            WP_CLI::error( 'Audit logging not available.' );
        }

        switch ( $action ) {
            case 'cleanup':
                $deleted = UFSC_Audit_Logger::cleanup_old_logs( $days );
                WP_CLI::success( "Deleted {$deleted} old audit logs (older than {$days} days)." );
                break;

            case 'stats':
                $stats = UFSC_Audit_Logger::get_stats();
                WP_CLI\Utils\format_items( $format, array( $stats ), array( 'total_logs' ) );
                
                if ( ! empty( $stats['actions_last_30_days'] ) ) {
                    WP_CLI::log( "\nTop actions (last 30 days):" );
                    foreach ( $stats['actions_last_30_days'] as $action => $count ) {
                        WP_CLI::log( "  {$action}: {$count}" );
                    }
                }
                break;

            case 'list':
                $logs = UFSC_Audit_Logger::get_logs( array( 'posts_per_page' => $limit ) );
                
                if ( empty( $logs ) ) {
                    WP_CLI::warning( 'No audit logs found.' );
                    return;
                }

                $formatted_logs = array();
                foreach ( $logs as $log ) {
                    $formatted_logs[] = array(
                        'id' => $log->ID,
                        'date' => $log->post_date,
                        'action' => get_post_meta( $log->ID, '_ufsc_audit_action', true ),
                        'user_id' => get_post_meta( $log->ID, '_ufsc_audit_user_id', true ),
                        'club_id' => get_post_meta( $log->ID, '_ufsc_audit_club_id', true ) ?: 'N/A',
                        'ip' => get_post_meta( $log->ID, '_ufsc_audit_ip', true )
                    );
                }

                WP_CLI\Utils\format_items( $format, $formatted_logs, array( 'id', 'date', 'action', 'user_id', 'club_id', 'ip' ) );
                break;

            default:
                WP_CLI::error( 'Invalid action. Use: cleanup, stats, list' );
        }
    }

    // Helper methods -----------------------------------------------------

    /**
     * Compute and cache statistics for a single club.
     *
     * Stats are cached in a transient for one hour so repeated calls are
     * inexpensive when running a batch command across all clubs.
     */
    private function get_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats     = get_transient( $cache_key );

        if ( false === $stats ) {
            global $wpdb;

            // If the helper functions are not available we safely return
            // default counters so the CLI command still works in a minimal
            // environment.
            if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
                $stats = array(
                    'total_licences'     => 0,
                    'paid_licences'      => 0,
                    'validated_licences' => 0,
                    'quota_remaining'    => 10,
                );
            } else {
                $licences_table = ufsc_get_licences_table();
                $columns        = $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

                // Base WHERE clause for club and optional season
                $where        = array( 'club_id = %d' );
                $where_values = array( (int) $club_id );

                $season_column = null;
                foreach ( array( 'season', 'saison', 'paid_season' ) as $col ) {
                    if ( in_array( $col, $columns, true ) ) {
                        $season_column = $col;
                        break;
                    }
                }

                if ( $season_column ) {
                    $where[]        = "`{$season_column}` = %s";
                    $where_values[] = $season;
                }

                $where_sql = implode( ' AND ', $where );

                // --- Total licences ------------------------------------
                $total_licences = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$licences_table}` WHERE {$where_sql}",
                        $where_values
                    )
                );

                // --- Paid licences -------------------------------------
                $paid_conditions = array();
                $paid_values     = array();

                if ( in_array( 'paid_season', $columns, true ) ) {
                    $paid_conditions[] = 'paid_season = %s';
                    $paid_values[]     = $season;
                }
                if ( in_array( 'is_paid', $columns, true ) ) {
                    $paid_conditions[] = 'is_paid = 1';
                }

                $paid_licences = 0;
                if ( ! empty( $paid_conditions ) ) {
                    $paid_query   = "SELECT COUNT(*) FROM `{$licences_table}` WHERE {$where_sql} AND (" . implode( ' OR ', $paid_conditions ) . ')';
                    $paid_values  = array_merge( $where_values, $paid_values );
                    $paid_licences = (int) $wpdb->get_var( $wpdb->prepare( $paid_query, $paid_values ) );
                }

                // --- Validated licences --------------------------------
                $validated_licences = 0;
                $status_column      = null;
                foreach ( array( 'status', 'statut' ) as $col ) {
                    if ( in_array( $col, $columns, true ) ) {
                        $status_column = $col;
                        break;
                    }
                }

                if ( $status_column ) {
                    $validated_statuses = array( 'valide', 'validée', 'validé', 'validated', 'approved' );
                    $placeholders       = implode( ',', array_fill( 0, count( $validated_statuses ), '%s' ) );
                    $validated_query    = "SELECT COUNT(*) FROM `{$licences_table}` WHERE {$where_sql} AND `{$status_column}` IN ({$placeholders})";
                    $validated_values   = array_merge( $where_values, $validated_statuses );
                    $validated_licences = (int) $wpdb->get_var( $wpdb->prepare( $validated_query, $validated_values ) );
                }

                $stats = array(
                    'total_licences'     => $total_licences,
                    'paid_licences'      => $paid_licences,
                    'validated_licences' => $validated_licences,
                    'quota_remaining'    => max( 0, 50 - $total_licences ),
                );
            }

            // Cache for one hour so repeated requests are fast.
            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        }

        return $stats;
    }

    /**
     * Get statistics for all clubs.
     */
    private function get_all_clubs_stats( $season ) {
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return array();
        }

        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();
        $clubs       = $wpdb->get_results( "SELECT id, nom FROM `{$clubs_table}`" );

        $all_stats = array();
        foreach ( $clubs as $club ) {
            $stats              = $this->get_club_stats( $club->id, $season );
            $stats['club_id']   = (int) $club->id;
            $stats['club_name'] = $club->nom;
            $all_stats[]        = $stats;
        }

        return $all_stats;
    }

    private function purge_cache( $club_id = null, $season = null ) {
        if ( $club_id && $season ) {
            // Purge specific club/season
            ufsc_invalidate_stats_cache( $club_id, $season );
            WP_CLI::success( "Cache purged for club {$club_id}, season {$season}." );
        } elseif ( $club_id ) {
            // Purge all seasons for club
            $wc_settings = ufsc_get_woocommerce_settings();
            ufsc_invalidate_stats_cache( $club_id, $wc_settings['season'] );
            WP_CLI::success( "Cache purged for club {$club_id}." );
        } else {
            // Purge all cache
            wp_cache_flush();
            WP_CLI::success( "All cache purged." );
        }
    }

    /**
     * Display information about the WordPress and UFSC caches.
     */
    private function cache_info() {
        WP_CLI::log( 'Cache information:' );
        WP_CLI::log( '  Object cache enabled: ' . ( wp_using_ext_object_cache() ? 'Yes' : 'No' ) );

        if ( class_exists( 'UFSC_Cache_Manager' ) ) {
            $stats = UFSC_Cache_Manager::get_cache_stats();
            WP_CLI::log( '  UFSC transients: ' . $stats['total_count'] );
            if ( ! empty( $stats['details'] ) ) {
                WP_CLI::log( '  Transient details:' );
                foreach ( $stats['details'] as $detail ) {
                    WP_CLI::log( sprintf( '    %s - %d bytes - expires %s', $detail['option_name'], $detail['size_bytes'], $detail['expires'] ) );
                }
            }
        } else {
            WP_CLI::log( '  Cache manager not available.' );
        }
    }
}

// Register WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'ufsc', 'UFSC_CLI_Commands' );
}