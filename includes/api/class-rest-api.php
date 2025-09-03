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

        // Attestation download route with nonce security
        register_rest_route( 'ufsc/v1', '/attestation/(?P<type>[a-z_]+)/(?P<nonce>[a-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_attestation_download' ),
            'permission_callback' => '__return_true' // Public with nonce verification
        ));

        // // UFSC: Enhanced dashboard endpoints
        register_rest_route( 'ufsc/v1', '/dashboard/kpis', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_dashboard_kpis' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/dashboard/recent-licences', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_recent_licences' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/dashboard/documents', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_club_documents' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/dashboard/detailed-stats', array(
            'methods' => 'GET',
            'callback' => array( __CLASS__, 'handle_detailed_stats' ),
            'permission_callback' => array( __CLASS__, 'check_club_permissions' )
        ));

        register_rest_route( 'ufsc/v1', '/licences/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array( __CLASS__, 'handle_licence_delete' ),
            'permission_callback' => array( __CLASS__, 'check_licence_permissions' )
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
     * Handle club endpoint (GET/PUT)
     */
    public static function handle_club( $request ) {
        $method = $request->get_method();
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );

        if ( $method === 'GET' ) {
            return self::get_club_info( $club_id, $request );
        } elseif ( $method === 'PUT' ) {
            return self::update_club_info( $club_id, $request );
        }

        return new WP_Error( 'rest_invalid_method', __( 'Méthode non supportée.', 'ufsc-clubs' ), array( 'status' => 405 ) );
    }

    /**
     * Handle stats endpoint
     */
    public static function handle_stats( $request ) {
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        $season = $request->get_param( 'season' );

        if ( ! $season ) {
            $wc_settings = ufsc_get_woocommerce_settings();
            $season = $wc_settings['season'];
        }

        $stats = self::get_cached_club_stats( $club_id, $season );
        
        return new WP_REST_Response( array(
            'stats' => $stats,
            'season' => $season,
            'club_id' => $club_id,
            'cached_at' => time()
        ), 200 );
    }

    /**
     * Handle attestation download
     */
    public static function handle_attestation_download( $request ) {
        $type = $request->get_param( 'type' );
        $nonce = $request->get_param( 'nonce' );

        // Verify nonce and extract data
        $attestation_data = self::verify_attestation_nonce( $type, $nonce );
        if ( ! $attestation_data ) {
            return new WP_Error( 'invalid_nonce', __( 'Lien d\'attestation invalide ou expiré.', 'ufsc-clubs' ), array( 'status' => 403 ) );
        }

        // Generate attestation file
        $file_path = self::generate_attestation_file( $type, $attestation_data );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return new WP_Error( 'generation_failed', __( 'Erreur lors de la génération de l\'attestation.', 'ufsc-clubs' ), array( 'status' => 500 ) );
        }

        // Log download
        ufsc_audit_log( 'attestation_downloaded', array(
            'type' => $type,
            'club_id' => $attestation_data['club_id'],
            'user_id' => $attestation_data['user_id'],
            'nonce' => $nonce
        ) );

        // Serve file
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
        header( 'Content-Length: ' . filesize( $file_path ) );
        readfile( $file_path );
        
        // Clean up temporary file
        unlink( $file_path );
        exit;
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

    /**
     * Get club information with caching
     */
    private static function get_club_info( $club_id, $request ) {
        $cache_key = "ufsc_club_info_{$club_id}";
        $club_info = get_transient( $cache_key );

        if ( false === $club_info ) {
            global $wpdb;
            $settings = UFSC_SQL::get_settings();
            $clubs_table = $settings['table_clubs'];

            $club = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$clubs_table} WHERE id = %d",
                $club_id
            ) );

            if ( ! $club ) {
                return new WP_Error( 'club_not_found', __( 'Club non trouvé.', 'ufsc-clubs' ), array( 'status' => 404 ) );
            }

            // Get additional info
            $quota_info = self::get_club_quota( $club_id );
            $licence_count = self::count_club_licences( $club_id, array() );

            $club_info = array(
                'id' => $club->id,
                'nom' => $club->nom,
                'region' => $club->region,
                'statut' => $club->statut,
                'adresse' => $club->adresse,
                'complement_adresse' => $club->complement_adresse,
                'code_postal' => $club->code_postal,
                'ville' => $club->ville,
                'email' => $club->email,
                'telephone' => $club->telephone,
                'num_affiliation' => $club->num_affiliation,
                'quota' => $quota_info,
                'licence_count' => $licence_count,
                'is_validated' => ufsc_is_validated_club( $club_id ),
                'can_edit' => ! ufsc_is_validated_club( $club_id ),
                'responsable_id' => $club->responsable_id
            );

            // Cache for 1 hour
            set_transient( $cache_key, $club_info, HOUR_IN_SECONDS );
        }

        return new WP_REST_Response( $club_info, 200 );
    }

    /**
     * Update club information
     */
    private static function update_club_info( $club_id, $request ) {
        $data = $request->get_json_params();

        // Check if club is validated (restrictions apply)
        if ( ufsc_is_validated_club( $club_id ) ) {
            // Only allow limited fields for validated clubs
            $allowed_fields = array( 'email', 'telephone', 'precision_distribution' );
            $data = array_intersect_key( $data, array_flip( $allowed_fields ) );
            
            if ( empty( $data ) ) {
                return new WP_Error( 'club_validated', __( 'Ce club est validé, seuls certains champs peuvent être modifiés.', 'ufsc-clubs' ), array( 'status' => 403 ) );
            }
        }

        // Sanitize data
        $sanitized_data = array();
        foreach ( $data as $key => $value ) {
            if ( $key === 'email' && ! is_email( $value ) ) {
                return new WP_Error( 'invalid_email', __( 'Adresse email invalide.', 'ufsc-clubs' ), array( 'status' => 400 ) );
            }
            $sanitized_data[ $key ] = sanitize_text_field( $value );
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

        $result = $wpdb->update(
            $clubs_table,
            $sanitized_data,
            array( 'id' => $club_id ),
            array_fill( 0, count( $sanitized_data ), '%s' ),
            array( '%d' )
        );

        if ( $result === false ) {
            return new WP_Error( 'update_failed', __( 'Échec de la mise à jour.', 'ufsc-clubs' ), array( 'status' => 500 ) );
        }

        // Invalidate cache
        delete_transient( "ufsc_club_info_{$club_id}" );

        // Log update
        ufsc_audit_log( 'club_updated', array(
            'club_id' => $club_id,
            'updated_fields' => array_keys( $sanitized_data ),
            'user_id' => get_current_user_id()
        ) );

        return new WP_REST_Response( array(
            'message' => __( 'Club mis à jour avec succès.', 'ufsc-clubs' ),
            'updated_fields' => array_keys( $sanitized_data )
        ), 200 );
    }

    /**
     * Generate attestation nonce with expiration
     */
    public static function generate_attestation_nonce( $type, $club_id, $user_id, $expiry_hours = 24 ) {
        $data = array(
            'type' => $type,
            'club_id' => $club_id,
            'user_id' => $user_id,
            'expires' => time() + ( $expiry_hours * HOUR_IN_SECONDS )
        );

        $nonce = wp_hash( serialize( $data ) . wp_create_nonce( 'ufsc_attestation' ) );
        
        // Store nonce data temporarily
        set_transient( "ufsc_attestation_nonce_{$nonce}", $data, $expiry_hours * HOUR_IN_SECONDS );

        return $nonce;
    }

    /**
     * Verify attestation nonce
     */
    private static function verify_attestation_nonce( $type, $nonce ) {
        $data = get_transient( "ufsc_attestation_nonce_{$nonce}" );
        
        if ( ! $data || $data['type'] !== $type ) {
            return false;
        }

        if ( time() > $data['expires'] ) {
            delete_transient( "ufsc_attestation_nonce_{$nonce}" );
            return false;
        }

        return $data;
    }

    /**
     * Generate attestation file
     */
    private static function generate_attestation_file( $type, $data ) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ufsc_temp/';

        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        $filename  = "attestation_{$type}_{$data['club_id']}_" . time() . '.pdf';
        $file_path = $temp_dir . $filename;

        $lines = array(
            sprintf( __( 'Type: %s', 'ufsc-clubs' ), ucfirst( $type ) ),
            sprintf( __( 'Club ID: %s', 'ufsc-clubs' ), $data['club_id'] ),
            sprintf( __( 'Généré le: %s', 'ufsc-clubs' ), current_time( 'mysql' ) ),
        );

        // Generate PDF using FPDF library
        require_once UFSC_CL_DIR . 'includes/lib/fpdf.php';
        $pdf = new FPDF();
        $pdf->SetTitle( __( 'Attestation UFSC', 'ufsc-clubs' ) );
        $pdf->AddPage();
        $pdf->SetFont( 'Arial', '', 12 );
        foreach ( $lines as $line ) {
            $pdf->Cell( 0, 10, $line, 0, 1 );
        }
        $pdf->Output( 'F', $file_path );

        return $file_path;
    }

    private static function fetch_club_licences( $club_id, $args ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        $where = array( "club_id = %d" );
        $where_values = array( $club_id );

        // Add search filter
        if ( ! empty( $args['search'] ) ) {
            $where[] = "(nom LIKE %s OR prenom LIKE %s OR email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        // Add status filter
        if ( ! empty( $args['status'] ) ) {
            $where[] = "statut = %s";
            $where_values[] = $args['status'];
        }

        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $order = ! empty( $args['sort'] ) ? $args['sort'] : 'nom ASC';

        $sql = "SELECT * FROM {$licences_table} WHERE " . implode( ' AND ', $where ) . 
               " ORDER BY {$order} LIMIT %d OFFSET %d";

        $where_values[] = $args['per_page'];
        $where_values[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
    }

    private static function count_club_licences( $club_id, $args ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        $where = array( "club_id = %d" );
        $where_values = array( $club_id );

        // Add search filter
        if ( ! empty( $args['search'] ) ) {
            $where[] = "(nom LIKE %s OR prenom LIKE %s OR email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        // Add status filter
        if ( ! empty( $args['status'] ) ) {
            $where[] = "statut = %s";
            $where_values[] = $args['status'];
        }

        $sql = "SELECT COUNT(*) FROM {$licences_table} WHERE " . implode( ' AND ', $where );

        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
    }

    private static function get_licence_club_id( $licence_id ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT club_id FROM {$licences_table} WHERE id = %d",
            $licence_id
        ) );
    }

    private static function create_licence_record( $club_id, $data ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        // Add club_id and timestamps
        $insert_data = array_merge( $data, array(
            'club_id' => $club_id,
            'date_creation' => current_time( 'mysql' ),
            'statut' => 'en_attente'
        ) );

        $result = $wpdb->insert( $licences_table, $insert_data );
        
        if ( $result === false ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    private static function get_club_quota( $club_id ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $licences_table = $settings['table_licences'];

        // Get club quota
        $quota_total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT quota_licences FROM {$clubs_table} WHERE id = %d",
            $club_id
        ) );

        // Count current licences
        $used = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$licences_table} WHERE club_id = %d",
            $club_id
        ) );

        return array( 
            'total' => $quota_total, 
            'used' => $used, 
            'remaining' => max( 0, $quota_total - $used ) 
        );
    }

    private static function create_payment_order( $club_id, $licence_ids ) {
        if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
            return false;
        }

        $wc_settings       = ufsc_get_woocommerce_settings();
        $license_product_id = $wc_settings['product_license_id'];
        $product            = wc_get_product( $license_product_id );

        if ( ! $product || ! $product->exists() ) {
            return false;
        }

        $quantity = max( 1, count( $licence_ids ) );

        try {
            $order = wc_create_order();
            if ( ! $order ) {
                return false;
            }

            $user_id = get_current_user_id();
            if ( $user_id > 0 ) {
                $order->set_customer_id( $user_id );
            }

            $item_id = $order->add_product( $product, $quantity );
            if ( ! $item_id ) {
                $order->delete( true );
                return false;
            }

            if ( ! empty( $licence_ids ) ) {
                wc_add_order_item_meta( $item_id, '_ufsc_licence_ids', $licence_ids );
            }
            wc_add_order_item_meta( $item_id, '_ufsc_club_id', $club_id );

            $order->calculate_totals();
            $order->update_status( 'pending', __( 'Commande créée pour licences UFSC additionnelles', 'ufsc-clubs' ) );
            $order->add_order_note( sprintf( __( 'Commande créée automatiquement pour %d licence(s) additionnelle(s) - Club ID: %d', 'ufsc-clubs' ), $quantity, $club_id ) );

            return $order->get_id();
        } catch ( Exception $e ) {
            error_log( 'UFSC: Error creating additional license order: ' . $e->getMessage() );
            return false;
        }
    }

    private static function get_cached_club_stats( $club_id, $season ) {
        $cache_key = "ufsc_stats_{$club_id}_{$season}";
        $stats = get_transient( $cache_key );

        if ( false === $stats ) {
            global $wpdb;
            $settings = UFSC_SQL::get_settings();
            $licences_table = $settings['table_licences'];

            // Calculate real stats
            $total_licences = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$licences_table} WHERE club_id = %d",
                $club_id
            ) );

            $paid_licences = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$licences_table} WHERE club_id = %d AND (statut = 'valide' OR statut = 'a_regler')",
                $club_id
            ) );

            $validated_licences = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$licences_table} WHERE club_id = %d AND statut = 'valide'",
                $club_id
            ) );

            $quota_info = self::get_club_quota( $club_id );

            $stats = array(
                'total_licences' => $total_licences,
                'paid_licences' => $paid_licences,
                'validated_licences' => $validated_licences,
                'quota_remaining' => $quota_info['remaining'],
                'quota_total' => $quota_info['total'],
                'quota_used' => $quota_info['used'],
                'season' => $season,
                'last_updated' => current_time( 'mysql' )
            );

            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
        }

        return $stats;
    }

    /**
     * // UFSC: Handle dashboard KPIs request
     */
    public static function handle_dashboard_kpis( $request ) {
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        $filters = $request->get_param( 'filters' ) ?: array();
        $cache_key = "ufsc_dashboard_kpis_{$club_id}_" . md5( serialize( $filters ) );
        
        // Check cache first
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Build WHERE clause with filters
        $where_conditions = array( "club_id = %d" );
        $where_values = array( $club_id );
        
        // Apply filters
        if ( ! empty( $filters['periode'] ) && is_numeric( $filters['periode'] ) ) {
            $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = intval( $filters['periode'] );
        }
        
        if ( ! empty( $filters['genre'] ) ) {
            $where_conditions[] = "sexe = %s";
            $where_values[] = sanitize_text_field( $filters['genre'] );
        }
        
        if ( ! empty( $filters['role'] ) ) {
            $where_conditions[] = "role = %s";
            $where_values[] = sanitize_text_field( $filters['role'] );
        }
        
        if ( isset( $filters['competition'] ) && $filters['competition'] !== '' ) {
            $where_conditions[] = "competition = %d";
            $where_values[] = intval( $filters['competition'] );
        }
        
        $where_clause = " WHERE " . implode( " AND ", $where_conditions );
        
        // Count by status
        $sql = "SELECT 
                    statut,
                    COUNT(*) as count
                FROM {$licences_table}
                {$where_clause}
                GROUP BY statut";
        
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        
        // Initialize counts
        $kpis = array(
            'licences_validees' => 0,
            'licences_payees' => 0,
            'licences_attente' => 0,
            'licences_refusees' => 0
        );
        
        // Map results to KPIs
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
                case 'refusee':
                    $kpis['licences_refusees'] = intval( $result->count );
                    break;
            }
        }
        
        // Cache for 10 minutes
        set_transient( $cache_key, $kpis, 10 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $kpis, 200 );
    }

    /**
     * // UFSC: Handle recent licences request
     */
    public static function handle_recent_licences( $request ) {
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        $limit = intval( $request->get_param( 'limit' ) ) ?: 5;
        $filters = $request->get_param( 'filters' ) ?: array();
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Build WHERE clause
        $where_conditions = array( "club_id = %d" );
        $where_values = array( $club_id );
        
        // Apply filters (same logic as KPIs)
        if ( ! empty( $filters['periode'] ) && is_numeric( $filters['periode'] ) ) {
            $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = intval( $filters['periode'] );
        }
        
        if ( ! empty( $filters['genre'] ) ) {
            $where_conditions[] = "sexe = %s";
            $where_values[] = sanitize_text_field( $filters['genre'] );
        }
        
        if ( ! empty( $filters['role'] ) ) {
            $where_conditions[] = "role = %s";
            $where_values[] = sanitize_text_field( $filters['role'] );
        }
        
        $where_clause = " WHERE " . implode( " AND ", $where_conditions );
        
        $sql = "SELECT id, prenom, nom, statut, role, date_creation
                FROM {$licences_table}
                {$where_clause}
                ORDER BY date_creation DESC
                LIMIT %d";
        
        $where_values[] = $limit;
        $licences = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        
        return new WP_REST_Response( $licences, 200 );
    }

    /**
     * // UFSC: Handle club documents status request
     */
    public static function handle_club_documents( $request ) {
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        $sql = "SELECT doc_statuts, doc_recepisse, doc_jo, doc_pv_ag, doc_cer, doc_attestation_cer
                FROM {$clubs_table}
                WHERE id = %d";
        
        $documents = $wpdb->get_row( $wpdb->prepare( $sql, $club_id ), ARRAY_A );
        
        return new WP_REST_Response( $documents ?: array(), 200 );
    }

    /**
     * // UFSC: Handle detailed statistics request
     */
    public static function handle_detailed_stats( $request ) {
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        $filters = $request->get_param( 'filters' ) ?: array();
        $cache_key = "ufsc_detailed_stats_{$club_id}_" . md5( serialize( $filters ) );
        
        // Check cache first
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Build base WHERE clause
        $where_conditions = array( "club_id = %d" );
        $where_values = array( $club_id );
        
        // Apply filters
        if ( ! empty( $filters['periode'] ) && is_numeric( $filters['periode'] ) ) {
            $where_conditions[] = "date_creation >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $where_values[] = intval( $filters['periode'] );
        }
        
        $where_clause = " WHERE " . implode( " AND ", $where_conditions );
        
        $stats = array();
        
        // Sex distribution
        $sql = "SELECT sexe, COUNT(*) as count, 
                       (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$licences_table} {$where_clause})) as percentage
                FROM {$licences_table} 
                {$where_clause} AND sexe IS NOT NULL
                GROUP BY sexe";
        
        $sexe_stats = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $where_values, $where_values ) ) );
        $stats['sexe'] = array();
        foreach ( $sexe_stats as $stat ) {
            $stats['sexe'][$stat->sexe] = array(
                'count' => intval( $stat->count ),
                'percentage' => floatval( $stat->percentage )
            );
        }
        
        // Age distribution
        $sql = "SELECT 
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) <= 12 THEN '≤12'
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 13 AND 17 THEN '13-17'
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 26 AND 39 THEN '26-39'
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) BETWEEN 40 AND 54 THEN '40-54'
                        WHEN TIMESTAMPDIFF(YEAR, date_naissance, CURDATE()) >= 55 THEN '≥55'
                        ELSE 'Non défini'
                    END as tranche_age,
                    COUNT(*) as count
                FROM {$licences_table}
                {$where_clause} AND date_naissance IS NOT NULL
                GROUP BY tranche_age";
        
        $age_stats = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        $stats['age'] = array();
        foreach ( $age_stats as $stat ) {
            $stats['age'][$stat->tranche_age] = intval( $stat->count );
        }
        
        // Competition vs Leisure
        $sql = "SELECT competition, COUNT(*) as count
                FROM {$licences_table}
                {$where_clause}
                GROUP BY competition";
        
        $comp_stats = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        $stats['competition'] = array( 'competition' => 0, 'loisir' => 0 );
        foreach ( $comp_stats as $stat ) {
            if ( $stat->competition == 1 ) {
                $stats['competition']['competition'] = intval( $stat->count );
            } else {
                $stats['competition']['loisir'] = intval( $stat->count );
            }
        }
        
        // Roles distribution
        $sql = "SELECT role, COUNT(*) as count
                FROM {$licences_table}
                {$where_clause} AND role IS NOT NULL
                GROUP BY role";
        
        $role_stats = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
        $stats['roles'] = array();
        foreach ( $role_stats as $stat ) {
            $stats['roles'][$stat->role] = intval( $stat->count );
        }
        
        // Evolution 30 days
        $sql_30days = str_replace( $where_clause, $where_clause . " AND date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)", $sql );
        $sql = "SELECT 
                    SUM(CASE WHEN statut = 'brouillon' THEN 1 ELSE 0 END) as nouveaux_brouillons,
                    SUM(CASE WHEN statut = 'payee' THEN 1 ELSE 0 END) as nouveaux_payes,
                    SUM(CASE WHEN statut = 'validee' THEN 1 ELSE 0 END) as nouveaux_valides
                FROM {$licences_table}
                {$where_clause} AND date_creation >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $evolution = $wpdb->get_row( $wpdb->prepare( $sql, $where_values ), ARRAY_A );
        $stats['evolution'] = $evolution;
        
        // Alerts
        $alerts = array();
        
        // Check for licenses pending > X days
        $sql = "SELECT COUNT(*) as count
                FROM {$licences_table}
                WHERE club_id = %d AND statut IN ('brouillon', 'non_payee') 
                AND date_creation <= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $pending_count = $wpdb->get_var( $wpdb->prepare( $sql, $club_id ) );
        if ( $pending_count > 0 ) {
            $alerts[] = array(
                'type' => 'warning',
                'message' => sprintf( 'Vous avez %d licence(s) en attente depuis plus de 7 jours', $pending_count )
            );
        }
        
        // Check for missing documents
        $sql = "SELECT 
                    CASE WHEN doc_statuts IS NULL OR doc_statuts = '' THEN 1 ELSE 0 END +
                    CASE WHEN doc_recepisse IS NULL OR doc_recepisse = '' THEN 1 ELSE 0 END +
                    CASE WHEN doc_jo IS NULL OR doc_jo = '' THEN 1 ELSE 0 END +
                    CASE WHEN doc_pv_ag IS NULL OR doc_pv_ag = '' THEN 1 ELSE 0 END +
                    CASE WHEN doc_cer IS NULL OR doc_cer = '' THEN 1 ELSE 0 END +
                    CASE WHEN doc_attestation_cer IS NULL OR doc_attestation_cer = '' THEN 1 ELSE 0 END
                    as missing_docs
                FROM {$settings['table_clubs']}
                WHERE id = %d";
        
        $missing_docs = $wpdb->get_var( $wpdb->prepare( $sql, $club_id ) );
        if ( $missing_docs > 0 ) {
            $alerts[] = array(
                'type' => 'info',
                'message' => sprintf( 'Il vous manque %d document(s) obligatoire(s)', $missing_docs )
            );
        }
        
        $stats['alerts'] = $alerts;
        
        // Cache for 10 minutes
        set_transient( $cache_key, $stats, 10 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $stats, 200 );
    }

    /**
     * // UFSC: Handle licence deletion
     */
    public static function handle_licence_delete( $request ) {
        $licence_id = $request->get_param( 'id' );
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Get licence details
        $licence = $wpdb->get_row( $wpdb->prepare(
            "SELECT statut FROM {$licences_table} WHERE id = %d AND club_id = %d",
            $licence_id, $club_id
        ) );
        
        if ( ! $licence ) {
            return new WP_Error( 'licence_not_found', 'Licence not found', array( 'status' => 404 ) );
        }
        
        // Check if deletion is allowed
        $deletable_statuses = array( 'brouillon', 'non_payee' );
        if ( ! in_array( $licence->statut, $deletable_statuses ) ) {
            return new WP_Error( 'delete_not_allowed', 'Delete not allowed for this status', array( 'status' => 403 ) );
        }
        
        // Soft delete (set deleted flag)
        $result = $wpdb->update(
            $licences_table,
            array( 'ufsc_deleted' => 1 ),
            array( 'id' => $licence_id ),
            array( '%d' ),
            array( '%d' )
        );
        
        if ( $result === false ) {
            return new WP_Error( 'delete_failed', 'Failed to delete licence', array( 'status' => 500 ) );
        }
        
        // Clear cache
        ufsc_invalidate_stats_cache( $club_id );
        
        return new WP_REST_Response( array( 'message' => 'Licence deleted successfully' ), 200 );
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