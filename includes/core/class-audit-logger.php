<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Audit logging system for UFSC
 * Uses Custom Post Type for storing audit trail
 */
class UFSC_Audit_Logger {

    const POST_TYPE = 'ufsc_audit';

    /**
     * Initialize audit logging system
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
    }

    /**
     * Register audit CPT
     */
    public static function register_post_type() {
        $args = array(
            'labels' => array(
                'name' => __( 'Journal d\'Audit', 'ufsc-clubs' ),
                'singular_name' => __( 'Entrée d\'Audit', 'ufsc-clubs' ),
                'menu_name' => __( 'Audit', 'ufsc-clubs' ),
                'all_items' => __( 'Toutes les entrées', 'ufsc-clubs' ),
                'search_items' => __( 'Rechercher', 'ufsc-clubs' ),
                'not_found' => __( 'Aucune entrée trouvée', 'ufsc-clubs' ),
            ),
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it manually to the UFSC menu
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options',
            ),
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => array( 'title' ),
            'menu_icon' => 'dashicons-list-view',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add admin menu under UFSC Gestion
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'ufsc-gestion-dashboard',
            __( 'Journal d\'Audit', 'ufsc-clubs' ),
            __( 'Audit', 'ufsc-clubs' ),
            'manage_options',
            'edit.php?post_type=' . self::POST_TYPE
        );
    }

    /**
     * Log an audit event
     * 
     * @param string $action Action performed
     * @param array $context Context information
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public static function log( $action, $context = array() ) {
        $user_id = get_current_user_id();
        $timestamp = current_time( 'mysql' );
        
        // Create title
        $title = sprintf( 
            '[%s] %s - User %d', 
            $timestamp, 
            $action, 
            $user_id 
        );

        // Prepare post data
        $post_data = array(
            'post_type' => self::POST_TYPE,
            'post_title' => $title,
            'post_content' => wp_json_encode( $context, JSON_PRETTY_PRINT ),
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => array(
                '_ufsc_audit_action' => sanitize_text_field( $action ),
                '_ufsc_audit_user_id' => $user_id,
                '_ufsc_audit_timestamp' => $timestamp,
                '_ufsc_audit_ip' => self::get_user_ip(),
                '_ufsc_audit_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
            )
        );

        // Add context as individual meta fields for easier querying
        if ( ! empty( $context ) ) {
            foreach ( $context as $key => $value ) {
                $meta_key = '_ufsc_audit_' . sanitize_key( $key );
                $post_data['meta_input'][ $meta_key ] = sanitize_text_field( $value );
            }
        }

        // Insert post
        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            error_log( 'UFSC Audit Log Error: ' . $post_id->get_error_message() );
            return $post_id;
        }

        // Also log to error_log for immediate debugging
        $log_message = sprintf(
            'UFSC Audit: %s by user %d with context: %s',
            $action,
            $user_id,
            wp_json_encode( $context )
        );
        error_log( $log_message );

        return $post_id;
    }

    /**
     * Get user IP address
     * 
     * @return string IP address
     */
    private static function get_user_ip() {
        $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        
        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                $ip = $_SERVER[ $key ];
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = explode( ',', $ip )[0];
                }
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }
        
        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }

    /**
     * Get audit logs with filters
     * 
     * @param array $args Query arguments
     * @return array Array of audit log posts
     */
    public static function get_logs( $args = array() ) {
        $defaults = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array()
        );

        $args = wp_parse_args( $args, $defaults );

        // Add filters
        if ( ! empty( $args['action'] ) ) {
            $args['meta_query'][] = array(
                'key' => '_ufsc_audit_action',
                'value' => $args['action'],
                'compare' => '='
            );
        }

        if ( ! empty( $args['user_id'] ) ) {
            $args['meta_query'][] = array(
                'key' => '_ufsc_audit_user_id',
                'value' => $args['user_id'],
                'compare' => '='
            );
        }

        if ( ! empty( $args['club_id'] ) ) {
            $args['meta_query'][] = array(
                'key' => '_ufsc_audit_club_id',
                'value' => $args['club_id'],
                'compare' => '='
            );
        }

        if ( ! empty( $args['date_from'] ) ) {
            $args['date_query'][] = array(
                'after' => $args['date_from'],
                'inclusive' => true
            );
        }

        if ( ! empty( $args['date_to'] ) ) {
            $args['date_query'][] = array(
                'before' => $args['date_to'],
                'inclusive' => true
            );
        }

        // Remove custom args that WP_Query doesn't understand
        unset( $args['action'], $args['club_id'], $args['date_from'], $args['date_to'] );

        return get_posts( $args );
    }

    /**
     * Clean old audit logs
     * 
     * @param int $days Number of days to keep (default: 365)
     * @return int Number of deleted logs
     */
    public static function cleanup_old_logs( $days = 365 ) {
        $cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        
        $old_logs = get_posts( array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'before' => $cutoff_date,
                    'inclusive' => false
                )
            ),
            'fields' => 'ids'
        ) );

        $deleted_count = 0;
        foreach ( $old_logs as $log_id ) {
            if ( wp_delete_post( $log_id, true ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Get audit statistics
     * 
     * @return array Statistics array
     */
    public static function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total logs
        $stats['total_logs'] = wp_count_posts( self::POST_TYPE )->publish;
        
        // Logs by action (last 30 days)
        $thirty_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );
        
        $action_counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT pm.meta_value as action, COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND p.post_date >= %s
             AND pm.meta_key = '_ufsc_audit_action'
             GROUP BY pm.meta_value
             ORDER BY count DESC",
            self::POST_TYPE,
            $thirty_days_ago
        ) );
        
        $stats['actions_last_30_days'] = array();
        foreach ( $action_counts as $row ) {
            $stats['actions_last_30_days'][ $row->action ] = (int) $row->count;
        }
        
        // Top users (last 30 days)
        $user_counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT pm.meta_value as user_id, COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND p.post_date >= %s
             AND pm.meta_key = '_ufsc_audit_user_id'
             GROUP BY pm.meta_value
             ORDER BY count DESC
             LIMIT 10",
            self::POST_TYPE,
            $thirty_days_ago
        ) );
        
        $stats['top_users_last_30_days'] = array();
        foreach ( $user_counts as $row ) {
            $user = get_user_by( 'id', $row->user_id );
            $stats['top_users_last_30_days'][] = array(
                'user_id' => (int) $row->user_id,
                'user_login' => $user ? $user->user_login : 'Unknown',
                'count' => (int) $row->count
            );
        }
        
        return $stats;
    }
}

// Initialize audit logging
UFSC_Audit_Logger::init();

/**
 * Helper function for logging audit events
 * 
 * @param string $action Action performed
 * @param array $context Context information
 * @return int|WP_Error Post ID on success, WP_Error on failure
 */
function ufsc_audit_log( $action, $context = array() ) {
    return UFSC_Audit_Logger::log( $action, $context );
}

/**
 * Schedule cleanup of old audit logs
 */
if ( ! wp_next_scheduled( 'ufsc_audit_cleanup' ) ) {
    wp_schedule_event( time(), 'weekly', 'ufsc_audit_cleanup' );
}

add_action( 'ufsc_audit_cleanup', function() {
    $deleted = UFSC_Audit_Logger::cleanup_old_logs( 365 );
    error_log( "UFSC Audit Cleanup: Deleted {$deleted} old audit logs" );
} );

/**
 * Add custom columns to audit list table
 */
add_filter( 'manage_ufsc_audit_posts_columns', function( $columns ) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = __( 'Événement', 'ufsc-clubs' );
    $new_columns['action'] = __( 'Action', 'ufsc-clubs' );
    $new_columns['user'] = __( 'Utilisateur', 'ufsc-clubs' );
    $new_columns['club'] = __( 'Club', 'ufsc-clubs' );
    $new_columns['ip'] = __( 'IP', 'ufsc-clubs' );
    $new_columns['date'] = __( 'Date', 'ufsc-clubs' );
    
    return $new_columns;
} );

/**
 * Populate custom columns
 */
add_action( 'manage_ufsc_audit_posts_custom_column', function( $column, $post_id ) {
    switch ( $column ) {
        case 'action':
            echo esc_html( get_post_meta( $post_id, '_ufsc_audit_action', true ) );
            break;
            
        case 'user':
            $user_id = get_post_meta( $post_id, '_ufsc_audit_user_id', true );
            if ( $user_id ) {
                $user = get_user_by( 'id', $user_id );
                if ( $user ) {
                    echo esc_html( $user->user_login );
                } else {
                    echo esc_html( "User #{$user_id}" );
                }
            }
            break;
            
        case 'club':
            $club_id = get_post_meta( $post_id, '_ufsc_audit_club_id', true );
            if ( $club_id ) {
                echo esc_html( "Club #{$club_id}" );
            } else {
                echo '—';
            }
            break;
            
        case 'ip':
            echo esc_html( get_post_meta( $post_id, '_ufsc_audit_ip', true ) );
            break;
    }
}, 10, 2 );

/**
 * Make columns sortable
 */
add_filter( 'manage_edit-ufsc_audit_sortable_columns', function( $columns ) {
    $columns['action'] = 'action';
    $columns['user'] = 'user';
    $columns['date'] = 'date';
    
    return $columns;
} );

/**
 * Handle column sorting
 */
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( $query->get( 'post_type' ) !== 'ufsc_audit' ) {
        return;
    }

    $orderby = $query->get( 'orderby' );

    switch ( $orderby ) {
        case 'action':
            $query->set( 'meta_key', '_ufsc_audit_action' );
            $query->set( 'orderby', 'meta_value' );
            break;
            
        case 'user':
            $query->set( 'meta_key', '_ufsc_audit_user_id' );
            $query->set( 'orderby', 'meta_value_num' );
            break;
    }
} );