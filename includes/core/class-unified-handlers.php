<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * // UFSC: Unified Form Handlers
 * Handles license and club form submissions with proper security and validation
 */
class UFSC_Unified_Handlers {

    /**
     * Initialize handlers
     */
    public static function init() {
        // License handlers
        add_action( 'admin_post_ufsc_save_licence', array( __CLASS__, 'handle_save_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_save_licence', array( __CLASS__, 'handle_save_licence' ) );
        
        // Club handlers  
        add_action( 'admin_post_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
        add_action( 'admin_post_nopriv_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
        
        // AJAX alternatives
        add_action( 'wp_ajax_ufsc_save_licence', array( __CLASS__, 'ajax_save_licence' ) );
        add_action( 'wp_ajax_nopriv_ufsc_save_licence', array( __CLASS__, 'ajax_save_licence' ) );
        add_action( 'wp_ajax_ufsc_save_club', array( __CLASS__, 'ajax_save_club' ) );
        add_action( 'wp_ajax_nopriv_ufsc_save_club', array( __CLASS__, 'ajax_save_club' ) );
        
        // CSV Export handler
        add_action( 'admin_post_ufsc_export_stats', array( __CLASS__, 'handle_export_stats' ) );
        add_action( 'admin_post_nopriv_ufsc_export_stats', array( __CLASS__, 'handle_export_stats' ) );
        add_action( 'wp_ajax_ufsc_export_stats', array( __CLASS__, 'ajax_export_stats' ) );
        add_action( 'wp_ajax_nopriv_ufsc_export_stats', array( __CLASS__, 'ajax_export_stats' ) );
    }

    /**
     * // UFSC: Handle license save (create/update)
     */
    public static function handle_save_licence() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_save_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
        $is_edit = $licence_id > 0;
        
        // Check permissions
        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté', $licence_id );
            return;
        }
        
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        if ( ! $club_id ) {
            self::redirect_with_error( 'Aucun club associé à votre compte', $licence_id );
            return;
        }
        
        // Check if editing is allowed
        if ( $is_edit ) {
            $licence_status = self::get_licence_status( $licence_id, $club_id );
            if ( ! $licence_status ) {
                self::redirect_with_error( 'Licence non trouvée', $licence_id );
                return;
            }
            
            // Status gating - prevent editing paid/validated licenses
            $non_editable_statuses = array( 'payee', 'validee' );
            if ( in_array( $licence_status, $non_editable_statuses ) ) {
                // Redirect to read-only view
                wp_redirect( add_query_arg( 'view_licence', $licence_id, wp_get_referer() ) );
                exit;
            }
        }
        
        // Validate and sanitize data
        $data = self::validate_licence_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message(), $licence_id );
            return;
        }
        
        // Save licence
        $result = self::save_licence_data( $licence_id, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::redirect_with_error( $result->get_error_message(), $licence_id );
            return;
        }
        
        // Success redirect
        $redirect_url = add_query_arg( 
            array( 
                'updated' => 1,
                'licence_id' => $result 
            ), 
            wp_get_referer() 
        );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * // UFSC: Handle club save (profile/documents)
     */
    public static function handle_save_club() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_save_club' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        // Check permissions
        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }
        
        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        
        if ( ! $club_id || ! self::user_can_manage_club( $user_id, $club_id ) ) {
            self::redirect_with_error( 'Permissions insuffisantes' );
            return;
        }
        
        // Validate and sanitize data
        $data = self::validate_club_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message() );
            return;
        }
        
        // Handle file uploads
        $upload_result = self::handle_club_uploads();
        if ( is_wp_error( $upload_result ) ) {
            self::redirect_with_error( $upload_result->get_error_message() );
            return;
        }
        
        $data = array_merge( $data, $upload_result );
        
        // Save club data
        $result = self::save_club_data( $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::redirect_with_error( $result->get_error_message() );
            return;
        }
        
        // Success redirect
        $redirect_url = add_query_arg( 'updated', 1, wp_get_referer() );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * // UFSC: Handle CSV export
     */
    public static function handle_export_stats() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ufsc_frontend_nonce' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_die( __( 'Vous devez être connecté', 'ufsc-clubs' ) );
        }
        
        $user_id = get_current_user_id();
        $club_id = intval( $_POST['club_id'] );
        
        if ( ufsc_get_user_club_id( $user_id ) !== $club_id ) {
            wp_die( __( 'Permissions insuffisantes', 'ufsc-clubs' ) );
        }
        
        $filters = json_decode( stripslashes( $_POST['filters'] ), true );
        
        // Generate CSV
        $csv_data = self::generate_stats_csv( $club_id, $filters );
        
        // Output CSV
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="stats-club-' . $club_id . '-' . date('Y-m-d') . '.csv"' );
        
        // Add BOM for UTF-8
        echo "\xEF\xBB\xBF";
        echo $csv_data;
        exit;
    }

    /**
     * Validate license data
     */
    private static function validate_licence_data( $post_data ) {
        $errors = array();
        $data = array();
        
        // Required fields
        $required_fields = array( 'prenom', 'nom', 'email' );
        foreach ( $required_fields as $field ) {
            if ( empty( $post_data[$field] ) ) {
                $errors[] = sprintf( __( 'Le champ %s est requis', 'ufsc-clubs' ), $field );
            } else {
                $data[$field] = sanitize_text_field( $post_data[$field] );
            }
        }
        
        // Email validation
        if ( ! empty( $post_data['email'] ) && ! is_email( $post_data['email'] ) ) {
            $errors[] = __( 'Adresse email invalide', 'ufsc-clubs' );
        } else {
            $data['email'] = sanitize_email( $post_data['email'] );
        }
        
        // Optional fields with sanitization
        $optional_fields = array(
            'telephone' => 'sanitize_text_field',
            'adresse' => 'sanitize_textarea_field',
            'date_naissance' => 'sanitize_text_field',
            'sexe' => 'sanitize_text_field',
            'role' => 'sanitize_text_field',
            'competition' => 'absint',
            'statut' => 'sanitize_text_field',
            'note' => 'sanitize_textarea_field'
        );

        foreach ( $optional_fields as $field => $sanitizer ) {
            if ( ! empty( $post_data[$field] ) ) {
                $data[ $field ] = call_user_func( $sanitizer, $post_data[$field] );
            }
        }

        // Boolean fields
        $boolean_fields = array(
            'reduction_benevole',
            'reduction_postier',
            'identifiant_laposte_flag',
            'fonction_publique',
            'licence_delegataire',
            'diffusion_image',
            'infos_fsasptt',
            'infos_asptt',
            'infos_cr',
            'infos_partenaires',
            'honorabilite',
            'assurance_dommage_corporel',
            'assurance_assistance'
        );

        foreach ( $boolean_fields as $field ) {
            $data[ $field ] = empty( $post_data[ $field ] ) ? 0 : 1;
        }

        // Conditional fields tied to flags
        $data['reduction_benevole_num'] = $data['reduction_benevole'] && ! empty( $post_data['reduction_benevole_num'] )
            ? sanitize_text_field( $post_data['reduction_benevole_num'] )
            : '';

        $data['reduction_postier_num'] = $data['reduction_postier'] && ! empty( $post_data['reduction_postier_num'] )
            ? sanitize_text_field( $post_data['reduction_postier_num'] )
            : '';

        $data['identifiant_laposte'] = $data['identifiant_laposte_flag'] && ! empty( $post_data['identifiant_laposte'] )
            ? sanitize_text_field( $post_data['identifiant_laposte'] )
            : '';

        $data['numero_licence_delegataire'] = $data['licence_delegataire'] && ! empty( $post_data['numero_licence_delegataire'] )
            ? sanitize_text_field( $post_data['numero_licence_delegataire'] )
            : '';

        // Conditional fields - clear if toggle is off
        if ( empty( $post_data['has_license_number'] ) ) {
            $data['numero_licence'] = '';
        } elseif ( ! empty( $post_data['numero_licence'] ) ) {
            $data['numero_licence'] = sanitize_text_field( $post_data['numero_licence'] );
        }
        
        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ', ', $errors ) );
        }
        
        return $data;
    }

    /**
     * Validate club data
     */
    private static function validate_club_data( $post_data ) {
        $errors = array();
        $data = array();
        
        // Allowed fields whitelist
        $allowed_fields = array(
            'nom' => 'sanitize_text_field',
            'adresse' => 'sanitize_textarea_field', 
            'code_postal' => 'sanitize_text_field',
            'ville' => 'sanitize_text_field',
            'email' => 'sanitize_email',
            'telephone' => 'sanitize_text_field',
            'iban' => 'sanitize_text_field',
            'region' => 'sanitize_text_field'
        );
        
        foreach ( $allowed_fields as $field => $sanitizer ) {
            if ( ! empty( $post_data[$field] ) ) {
                $data[$field] = call_user_func( $sanitizer, $post_data[$field] );
            }
        }
        
        // Specific validations
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Adresse email invalide', 'ufsc-clubs' );
        }
        
        if ( ! empty( $data['code_postal'] ) && ! preg_match( '/^\d{5}$/', $data['code_postal'] ) ) {
            $errors[] = __( 'Code postal invalide', 'ufsc-clubs' );
        }
        
        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ', ', $errors ) );
        }
        
        return $data;
    }

    /**
     * Handle club document uploads
     */
    private static function handle_club_uploads() {
        $upload_results = array();
        
        // Document types
        $document_fields = array(
            'doc_statuts' => 'statuts_upload',
            'doc_recepisse' => 'recepisse_upload', 
            'doc_jo' => 'jo_upload',
            'doc_pv_ag' => 'pv_ag_upload',
            'doc_cer' => 'cer_upload',
            'doc_attestation_cer' => 'attestation_cer_upload'
        );
        
        foreach ( $document_fields as $db_field => $upload_field ) {
            if ( ! empty( $_FILES[$upload_field]['name'] ) ) {
                $upload_result = wp_handle_upload( $_FILES[$upload_field], array(
                    'test_form' => false,
                    'mimes' => array(
                        'pdf' => 'application/pdf',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg', 
                        'png' => 'image/png'
                    )
                ) );
                
                if ( is_wp_error( $upload_result ) || isset( $upload_result['error'] ) ) {
                    return new WP_Error( 'upload_failed', 
                        sprintf( __( 'Erreur upload %s: %s', 'ufsc-clubs' ), 
                            $upload_field, 
                            isset( $upload_result['error'] ) ? $upload_result['error'] : 'Erreur inconnue' 
                        ) 
                    );
                }
                
                // Check file size (5MB max)
                if ( $_FILES[$upload_field]['size'] > 5 * 1024 * 1024 ) {
                    return new WP_Error( 'file_too_large', __( 'Fichier trop volumineux (max 5MB)', 'ufsc-clubs' ) );
                }
                
                $upload_results[$db_field] = $upload_result['url'];
            }
        }
        
        return $upload_results;
    }

    /**
     * Save license data to database
     */
    private static function save_licence_data( $licence_id, $club_id, $data ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        // Whitelist fields against known licence columns
        $fields = UFSC_SQL::get_licence_fields();
        $data   = array_intersect_key( $data, $fields );
        $data['club_id'] = $club_id;
        
        if ( $licence_id > 0 ) {
            // Update
            $data['date_modification'] = current_time( 'mysql' );
            $result = $wpdb->update( $licences_table, $data, array( 'id' => $licence_id ) );
            if ( $result === false ) {
                return new WP_Error( 'update_failed', __( 'Erreur lors de la mise à jour', 'ufsc-clubs' ) );
            }
            return $licence_id;
        } else {
            // Create
            $data['date_creation'] = current_time( 'mysql' );
            $data['statut'] = 'brouillon';
            $result = $wpdb->insert( $licences_table, $data );
            if ( $result === false ) {
                return new WP_Error( 'insert_failed', __( 'Erreur lors de la création', 'ufsc-clubs' ) );
            }
            return $wpdb->insert_id;
        }
    }

    /**
     * Save club data to database
     */
    private static function save_club_data( $club_id, $data ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        
        $result = $wpdb->update( $clubs_table, $data, array( 'id' => $club_id ) );
        if ( $result === false ) {
            return new WP_Error( 'update_failed', __( 'Erreur lors de la mise à jour du club', 'ufsc-clubs' ) );
        }
        
        // Clear cache
        delete_transient( "ufsc_club_info_{$club_id}" );
        
        return true;
    }

    /**
     * Generate CSV for statistics export
     */
    private static function generate_stats_csv( $club_id, $filters ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        // Build WHERE clause with filters
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
        
        if ( ! empty( $filters['role'] ) ) {
            $where_conditions[] = "role = %s";
            $where_values[] = sanitize_text_field( $filters['role'] );
        }
        
        if ( isset( $filters['competition'] ) && $filters['competition'] !== '' ) {
            $where_conditions[] = "competition = %d";
            $where_values[] = intval( $filters['competition'] );
        }
        
        $where_clause = " WHERE " . implode( " AND ", $where_conditions );
        
        // Get data
        $sql = "SELECT prenom, nom, email, telephone, sexe, date_naissance, role, statut, 
                       competition, date_creation
                FROM {$licences_table}
                {$where_clause}
                ORDER BY date_creation DESC";
        
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ), ARRAY_A );
        
        // Generate CSV
        $output = fopen( 'php://temp', 'w' );
        
        // Headers
        $headers = array(
            'Prénom', 'Nom', 'Email', 'Téléphone', 'Sexe', 'Date Naissance', 
            'Rôle', 'Statut', 'Compétition', 'Date Création'
        );
        fputcsv( $output, $headers, ';' );
        
        // Data rows
        foreach ( $results as $row ) {
            $row['competition'] = $row['competition'] ? 'Oui' : 'Non';
            fputcsv( $output, $row, ';' );
        }
        
        rewind( $output );
        $csv_data = stream_get_contents( $output );
        fclose( $output );
        
        return $csv_data;
    }

    /**
     * Helper functions
     */
    private static function get_licence_status( $licence_id, $club_id ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT statut FROM {$licences_table} WHERE id = %d AND club_id = %d",
            $licence_id, $club_id
        ) );
    }

    private static function user_can_manage_club( $user_id, $club_id ) {
        // Simple check - could be enhanced with more complex permissions
        return ufsc_get_user_club_id( $user_id ) === $club_id;
    }

    private static function redirect_with_error( $message, $licence_id = null ) {
        $redirect_url = wp_get_referer() ?: home_url();
        $args = array( 'error' => urlencode( $message ) );
        if ( $licence_id ) {
            $args['licence_id'] = $licence_id;
        }
        wp_redirect( add_query_arg( $args, $redirect_url ) );
        exit;
    }

    /**
     * AJAX handlers
     */
    public static function ajax_save_licence() {
        $result = self::handle_save_licence();
        // Handle AJAX response format
        wp_send_json_success( $result );
    }

    public static function ajax_save_club() {
        $result = self::handle_save_club();
        wp_send_json_success( $result );
    }

    public static function ajax_export_stats() {
        self::handle_export_stats();
    }
}