<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST API endpoints for UFSC Frontend
 * Provides secure API access for frontend functionality
 */
class UFSC_REST_API {

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route( 'ufsc/v1', '/licences', array(
            'methods' => array( 'GET', 'POST' ),
            'callback' => array( __CLASS__, 'handle_licences' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' ),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param ) && $param > 0;
                    }
                ),
                'per_page' => array(
                    'default' => 20,
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param ) && $param > 0 && $param <= 100;
                    }
                )
            )
        ));

        register_rest_route( 'ufsc/v1', '/licences/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array( __CLASS__, 'handle_licence_update' ),
            'permission_callback' => array( __CLASS__, 'check_licence_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/club', array(
            'methods' => array( 'GET', 'PUT' ),
            'callback' => array( __CLASS__, 'handle_club' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/club/logo', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'handle_logo_upload' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_stats' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/export/(?P<format>csv|xlsx)', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_export' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/import', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'handle_import_preview' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/import/commit', array(
            'methods' => 'POST',
            'callback' => array( __CLASS__, 'handle_import_commit' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));
    }

    /**
     * Check club permissions
     */
    public static function check_club_permissions( $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'Vous devez être connecté.', 'ufsc-clubs' ), array( 'status' => 401 ) );
        }

        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );

        if ( ! $club_id ) {
            return new WP_Error( 'rest_forbidden', __( 'Aucun club associé à votre compte.', 'ufsc-clubs' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Check licence permissions
     */
    public static function check_licence_permissions( $request ) {
        $club_check = self::check_club_permissions( $request );
        if ( is_wp_error( $club_check ) ) {
            return $club_check;
        }

        $licence_id = $request->get_param( 'id' );
        
        // Check if licence belongs to user's club
        $user_id = get_current_user_id();
        $user_club_id = ufsc_get_user_club_id( $user_id );
        $licence_club_id = self::get_licence_club_id( $licence_id );

        if ( $user_club_id !== $licence_club_id ) {
            return new WP_Error( 'rest_forbidden', __( 'Vous n\'avez pas accès à cette licence.', 'ufsc-clubs' ), array( 'status' => 403 ) );
        }

        // Check if licence is validated (and thus non-editable)
        if ( ufsc_is_validated_licence( $licence_id ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Cette licence ne peut plus être modifiée car elle est validée.', 'ufsc-clubs' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Handle licences endpoint
     */
    public static function handle_licences( $request ) {
        $method = $request->get_method();
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );

        if ( $method === 'GET' ) {
            return self::get_licences( $club_id, $request );
        } elseif ( $method === 'POST' ) {
            return self::create_licence( $club_id, $request );
        }

        return new WP_Error( 'rest_invalid_method', __( 'Méthode non supportée.', 'ufsc-clubs' ), array( 'status' => 405 ) );
    }

    /**
     * Get licences for club
     */
    private static function get_licences( $club_id, $request ) {
        $page = $request->get_param( 'page' );
        $per_page = $request->get_param( 'per_page' );
        $search = $request->get_param( 'search' );
        $status = $request->get_param( 'status' );
        $sort = $request->get_param( 'sort' );

        $args = array(
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'status' => $status,
            'sort' => $sort
        );

        // STUB: Implement actual data retrieval
        $licences = self::fetch_club_licences( $club_id, $args );
        $total = self::count_club_licences( $club_id, $args );

        return new WP_REST_Response( array(
            'licences' => $licences,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil( $total / $per_page )
        ), 200 );
    }

    /**
     * Create new licence
     */
    private static function create_licence( $club_id, $request ) {
        $data = $request->get_json_params();
        
        // Validate required fields
        $required_fields = array( 'nom', 'prenom', 'email', 'date_naissance', 'sexe' );
        foreach ( $required_fields as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_field', 
                    sprintf( __( 'Le champ %s est requis.', 'ufsc-clubs' ), $field ), 
                    array( 'status' => 400 ) 
                );
            }
        }

        // Sanitize data
        $sanitized_data = array();
        foreach ( $data as $key => $value ) {
            $sanitized_data[ $key ] = sanitize_text_field( $value );
        }

        // Validate email
        if ( ! is_email( $sanitized_data['email'] ) ) {
            return new WP_Error( 'invalid_email', __( 'Adresse email invalide.', 'ufsc-clubs' ), array( 'status' => 400 ) );
        }

        // Check quota
        $quota_info = self::get_club_quota( $club_id );
        $needs_payment = $quota_info['remaining'] <= 0;

        // Create licence
        $licence_id = self::create_licence_record( $club_id, $sanitized_data );
        
        if ( ! $licence_id ) {
            return new WP_Error( 'creation_failed', __( 'Échec de création de la licence.', 'ufsc-clubs' ), array( 'status' => 500 ) );
        }

        $response_data = array(
            'licence_id' => $licence_id,
            'message' => __( 'Licence créée avec succès.', 'ufsc-clubs' )
        );

        // Handle payment if quota exceeded
        if ( $needs_payment ) {
            $order_id = self::create_payment_order( $club_id, array( $licence_id ) );
            if ( $order_id ) {
                $order = wc_get_order( $order_id );
                $response_data['payment_required'] = true;
                $response_data['payment_url'] = $order->get_checkout_payment_url();
                $response_data['message'] = __( 'Licence créée. Paiement requis.', 'ufsc-clubs' );
            }
        }

        // Log audit trail
        ufsc_audit_log( 'licence_created', array(
            'licence_id' => $licence_id,
            'club_id' => $club_id,
            'user_id' => get_current_user_id(),
            'needs_payment' => $needs_payment
        ) );

        return new WP_REST_Response( $response_data, 201 );
    }

    // STUB METHODS - To be implemented according to database schema

    private static function fetch_club_licences( $club_id, $args ) {
        // TODO: Implement actual database query
        return array();
    }

    private static function count_club_licences( $club_id, $args ) {
        // TODO: Implement count query
        return 0;
    }

    private static function get_licence_club_id( $licence_id ) {
        // TODO: Implement club ID lookup for licence
        return 0;
    }

    private static function create_licence_record( $club_id, $data ) {
        // TODO: Implement licence creation
        return 0;
    }

    private static function get_club_quota( $club_id ) {
        // TODO: Implement quota calculation
        return array( 'total' => 10, 'used' => 3, 'remaining' => 7 );
    }

    private static function create_payment_order( $club_id, $licence_ids ) {
        // TODO: Implement WooCommerce order creation
        return ufsc_create_additional_license_order( $club_id, $licence_ids, get_current_user_id() );
    }

    private static function get_cached_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats = get_transient( $cache_key );

        if ( false === $stats ) {
            // TODO: Calculate real stats
            $stats = array(
                'total_licences' => 0,
                'paid_licences' => 0,
                'validated_licences' => 0,
                'quota_remaining' => 10
            );

            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        }

        return $stats;
    }

    // Additional stub methods would be here...
    // (Truncated for brevity - other methods follow same pattern)
}

/**
 * Initialize REST API on WordPress init
 */
add_action( 'rest_api_init', array( 'UFSC_REST_API', 'register_routes' ) );

/**
 * Invalidate stats cache when needed
 */
function ufsc_invalidate_stats_cache( $club_id, $season = null ) {
    if ( ! $season ) {
        $wc_settings = ufsc_get_woocommerce_settings();
        $season = $wc_settings['season'];
    }
    
    $cache_key = "ufsc_stats_{$club_id}_{$season}";
    delete_transient( $cache_key );
}