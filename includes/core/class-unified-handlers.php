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


        add_action( 'admin_post_ufsc_add_licence', array( __CLASS__, 'handle_add_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_add_licence', array( __CLASS__, 'handle_add_licence' ) );
        add_action( 'admin_post_ufsc_update_licence', array( __CLASS__, 'handle_update_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_update_licence', array( __CLASS__, 'handle_update_licence' ) );
        add_action( 'admin_post_ufsc_save_licence', array( __CLASS__, 'handle_save_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_save_licence', array( __CLASS__, 'handle_save_licence' ) );

        add_action( 'admin_post_ufsc_delete_licence', array( __CLASS__, 'handle_delete_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_delete_licence', array( __CLASS__, 'handle_delete_licence' ) );
        add_action( 'admin_post_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );
        add_action( 'admin_post_nopriv_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );


        
        // Club handlers
        add_action( 'admin_post_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
        add_action( 'admin_post_nopriv_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
        add_action( 'admin_post_ufsc_club_affiliation_submit', array( __CLASS__, 'handle_club_affiliation_submit' ) );
        add_action( 'admin_post_nopriv_ufsc_club_affiliation_submit', array( __CLASS__, 'handle_club_affiliation_submit' ) );
        
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
     * Handle licence save (create or update based on presence of licence_id).
     */
    public static function handle_save_licence() {
        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;

        self::process_licence_request( $licence_id );
    }

    /**
     * Handle licence creation.
     */
    public static function handle_add_licence() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_add_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );

        if ( ! $club_id ) {
            self::redirect_with_error( 'Aucun club associé à votre compte' );
            return;
        }

        $data = self::process_licence_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message() );
            return;
        }

        $result = self::save_licence_data( 0, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::redirect_with_error( $result->get_error_message() );
            return;
        }

        if ( wp_doing_ajax() ) {
            return array( 'licence_id' => $result );
        }

        $redirect_url = add_query_arg(
            array(
                'created'    => 1,
                'licence_id' => $result
            ),
            wp_get_referer()
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle licence update
     */
    public static function handle_update_licence() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_update_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;


        if ( ! $licence_id ) {
            self::redirect_with_error( 'Licence ID invalide' );
            return;
        }


        $is_edit    = $licence_id > 0;

        // Basic authentication check

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté', $licence_id );
            return;
        }


        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );

        $user_id        = get_current_user_id();
        $managed_club   = ufsc_get_user_club_id( $user_id );
        $target_club_id = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : $managed_club;

        // Ensure current user can manage the target club
        if ( ! current_user_can( 'manage_options' ) && $managed_club !== $target_club_id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            wp_safe_redirect( wp_get_referer() );
            exit; // Abort processing when permission check fails
        }

        $club_id = $target_club_id;


        if ( ! $club_id ) {
            self::redirect_with_error( 'Aucun club associé à votre compte', $licence_id );
            return;
        }

        $licence_status = self::get_licence_status( $licence_id, $club_id );
        if ( ! $licence_status ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        $non_editable_statuses = array( 'payee', 'validee' );
        if ( in_array( $licence_status, $non_editable_statuses ) ) {
            wp_safe_redirect( add_query_arg( 'view_licence', $licence_id, wp_get_referer() ) );
            exit;
        }

        $data = self::process_licence_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message(), $licence_id );
            return;
        }

        $result = self::save_licence_data( $licence_id, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::redirect_with_error( $result->get_error_message(), $licence_id );
            return;
        }

        if ( wp_doing_ajax() ) {
            return array( 'licence_id' => $licence_id );
        }

        $redirect_url = add_query_arg(
            array(
                'updated'    => 1,
                'licence_id' => $licence_id
            ),
            wp_get_referer()
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle licence deletion
     */
    public static function handle_delete_licence() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_delete_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
        $user_id    = get_current_user_id();
        $club_id    = ufsc_get_user_club_id( $user_id );

        if ( ! $licence_id || ! $club_id ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        $licence_status = self::get_licence_status( $licence_id, $club_id );
        if ( ! $licence_status ) {
            self::redirect_with_error( 'Licence non trouvée' );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $wpdb->delete( $table, array( 'id' => $licence_id, 'club_id' => $club_id ) );

        $redirect_url = add_query_arg( 'deleted', 1, wp_get_referer() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle licence status update
     */
    public static function handle_update_licence_status() {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_update_licence_status' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $user_id    = get_current_user_id();
        $club_id    = ufsc_get_user_club_id( $user_id );

        if ( ! $licence_id || ! $club_id || ! $new_status ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        if ( ! self::get_licence_status( $licence_id, $club_id ) ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        $valid_statuses = array_keys( UFSC_SQL::statuses() );
        if ( ! in_array( $new_status, $valid_statuses ) ) {
            self::redirect_with_error( 'Statut invalide', $licence_id );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $wpdb->update( $table, array( 'statut' => $new_status ), array( 'id' => $licence_id, 'club_id' => $club_id ) );

        $redirect_url = add_query_arg( array(
            'updated_status' => 1,
            'licence_id'     => $licence_id
        ), wp_get_referer() );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * // UFSC: Handle club save (profile/documents)
     */
    public static function handle_save_club() {
        // Verify nonce - accept custom or default nonce field
        $has_valid_nonce = false;
        if ( isset( $_POST['ufsc_club_nonce'] ) ) {
            $has_valid_nonce = wp_verify_nonce( $_POST['ufsc_club_nonce'], 'ufsc_save_club' );
        }
        if ( ! $has_valid_nonce && isset( $_POST['_wpnonce'] ) ) {
            $has_valid_nonce = wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_save_club' );
        }

        if ( ! $has_valid_nonce ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        // Basic authentication check
        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $user_id        = get_current_user_id();
        $managed_club   = ufsc_get_user_club_id( $user_id );
        $target_club_id = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : $managed_club;

        // Ensure the current user can manage the requested club
        if ( ! current_user_can( 'manage_options' ) && $managed_club !== $target_club_id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            wp_safe_redirect( wp_get_referer() );
            exit; // Abort if user doesn't manage this club
        }

        $club_id = $target_club_id;
        
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
        $redirect_url = esc_url_raw( add_query_arg( 'updated', 1, wp_get_referer() ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle club affiliation form submission.
     *
     * Validates nonce and user capability, processes form data and required
     * documents, persists the club record and routes the user to WooCommerce
     * checkout with appropriate notices.
     */
    public static function handle_club_affiliation_submit() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_club_affiliation_submit' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( __( 'Vous devez être connecté', 'ufsc-clubs' ) );
            return;
        }

        // Validate and sanitize club data
        $data = self::validate_club_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message() );
            return;
        }

        // Handle required document uploads
        $doc_result = array();
        if ( class_exists( 'UFSC_Uploads' ) ) {
            $doc_result = UFSC_Uploads::handle_required_docs( $_FILES );
            if ( is_wp_error( $doc_result ) ) {
                self::redirect_with_error( $doc_result->get_error_message() );
                return;
            }
        }

        $data = array_merge( $data, $doc_result );

        // Persist club record
        global $wpdb;
        $settings    = UFSC_SQL::get_settings();
        $insert_data = array_merge( $data, array(
            'responsable_id' => get_current_user_id(),
            'date_creation'  => current_time( 'mysql' ),
            'statut'         => 'en_attente'
        ) );

        $result = $wpdb->insert( $settings['table_clubs'], $insert_data );
        if ( false === $result ) {
            self::redirect_with_error( __( 'Erreur lors de la création du club', 'ufsc-clubs' ) );
            return;
        }

        $club_id = (int) $wpdb->insert_id;

        // WooCommerce integration: add product to cart or create order
        $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url();

        $added = false;
        if ( function_exists( 'WC' ) ) {
            function_exists( 'wc_load_cart' ) && wc_load_cart();
            $added = WC()->cart->add_to_cart( 4823, 1, 0, array(), array( 'club_id' => $club_id ) );
        }

        if ( $added ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'Produit d\'affiliation ajouté au panier.', 'ufsc-clubs' ), 'success' );
            }
            wp_safe_redirect( $checkout_url );
            exit;
        }

        if ( function_exists( 'wc_create_order' ) ) {
            $order = wc_create_order( array( 'status' => 'pending' ) );
            if ( ! is_wp_error( $order ) ) {
                $product = wc_get_product( 4823 );
                if ( $product ) {
                    $order->add_product( $product, 1 );
                    $order->calculate_totals();
                    if ( function_exists( 'wc_add_notice' ) ) {
                        wc_add_notice( __( 'Commande d\'affiliation créée.', 'ufsc-clubs' ), 'success' );
                    }
                    wp_safe_redirect( $order->get_checkout_payment_url() );
                    exit;
                }
            }
        }

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'Impossible d\'ajouter le produit au panier.', 'ufsc-clubs' ), 'error' );
        }
        wp_safe_redirect( $checkout_url );
        exit;
    }

    /**
     * Process licence add/update request
     */
    private static function process_licence_request( $licence_id ) {
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_save_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::store_form_and_redirect( $_POST, array( __( 'Vous devez être connecté', 'ufsc-clubs' ) ), $licence_id );
        }

        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        if ( ! $club_id ) {
            self::store_form_and_redirect( $_POST, array( __( 'Aucun club associé à votre compte', 'ufsc-clubs' ) ), $licence_id );
        }

        if ( $licence_id > 0 ) {
            $licence_status = self::get_licence_status( $licence_id, $club_id );
            if ( ! $licence_status ) {
                self::store_form_and_redirect( $_POST, array( __( 'Licence non trouvée', 'ufsc-clubs' ) ), $licence_id );
            }

            $non_editable_statuses = array( 'payee', 'validee' );
            if ( in_array( $licence_status, $non_editable_statuses, true ) ) {
                self::store_form_and_redirect( $_POST, array( __( 'Modification non autorisée', 'ufsc-clubs' ) ), $licence_id );
            }
        }

        $data = self::process_licence_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::store_form_and_redirect( $_POST, array( $data->get_error_message() ), $licence_id );
        }

        $result = self::save_licence_data( $licence_id, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::store_form_and_redirect( $_POST, array( $result->get_error_message() ), $licence_id );
        }

        $new_id = $result;
        if ( isset( $_POST['ufsc_submit_action'] ) && 'add_to_cart' === $_POST['ufsc_submit_action'] ) {

            $wc_settings = ufsc_get_woocommerce_settings();
            $product_id  = $wc_settings['product_license_id'];
            $added       = false;

            if ( function_exists( 'WC' ) ) {
                function_exists( 'wc_load_cart' ) && wc_load_cart();
                $added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'licence_id' => $new_id, 'club_id' => $club_id ) );
            }

            if ( ! $added ) {
                self::store_form_and_redirect( $_POST, array( __( 'Impossible d\'ajouter le produit au panier', 'ufsc-clubs' ) ), $new_id );
            }

            if ( function_exists( 'WC' ) && defined( 'PRODUCT_ID_LICENCE' ) ) {
                $cart_item_data = array(
                    'licence_id'         => $new_id,
                    'club_id'            => $club_id,
                    'ufsc_nom'           => sanitize_text_field( $data['nom'] ),
                    'ufsc_prenom'        => sanitize_text_field( $data['prenom'] ),
                    'ufsc_date_naissance' => isset( $data['date_naissance'] ) ? sanitize_text_field( $data['date_naissance'] ) : '',
                );
                WC()->cart->add_to_cart( PRODUCT_ID_LICENCE, 1, 0, array(), $cart_item_data );

            }

            self::update_licence_status_db( $new_id, 'pending' );
            if ( function_exists( 'wc_get_cart_url' ) ) {
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }
        }

        $redirect_url = esc_url_raw( add_query_arg(
            array(
                'updated'    => 1,
                'licence_id' => $new_id,
            ),
            wp_get_referer()
        ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Store form data and errors then redirect back
     */
    private static function store_form_and_redirect( $data, $errors, $licence_id = 0 ) {
        $key = 'ufsc_licence_form_' . get_current_user_id();
        set_transient( $key, array(
            'data'   => wp_unslash( $data ),
            'errors' => (array) $errors,
        ), MINUTE_IN_SECONDS );

        $redirect = wp_get_referer() ?: home_url();
        if ( $licence_id ) {
            $redirect = add_query_arg( 'licence_id', $licence_id, $redirect );
        }
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Update licence status directly in database
     */
    private static function update_licence_status_db( $licence_id, $status ) {
        global $wpdb;
        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        $wpdb->update( $licences_table, array( 'statut' => $status ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
    }


    /**
     * // UFSC: Handle CSV export
     */
    public static function handle_export_stats() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_die( __( 'Missing nonce', 'ufsc-clubs' ) );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'ufsc_frontend_nonce' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_die( __( 'Vous devez être connecté', 'ufsc-clubs' ) );
        }

        if ( ! isset( $_POST['club_id'] ) ) {
            wp_die( __( 'Missing club ID', 'ufsc-clubs' ) );
        }

        $user_id = get_current_user_id();
        $club_id = absint( wp_unslash( $_POST['club_id'] ) );

        if ( ufsc_get_user_club_id( $user_id ) !== $club_id ) {
            wp_die( __( 'Permissions insuffisantes', 'ufsc-clubs' ) );
        }

        if ( ! isset( $_POST['filters'] ) ) {
            wp_die( __( 'Missing filters', 'ufsc-clubs' ) );
        }

        $filters_raw = wp_unslash( $_POST['filters'] );
        $filters     = json_decode( $filters_raw, true );
        if ( ! is_array( $filters ) ) {
            $filters = array();
        }
        
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
     * Sanitize and validate licence fields
     */
    private static function process_licence_data( $post_data ) {
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
        $redirect_url = remove_query_arg( 'ufsc_error', $redirect_url );

        $args = array( 'ufsc_error' => rawurlencode( $message ) );
        if ( $licence_id ) {
            $args['licence_id'] = $licence_id;
        }

        wp_safe_redirect( add_query_arg( $args, $redirect_url ) );
        exit;
    }

    /**
     * AJAX handlers
     */
    public static function ajax_save_licence() {
        if ( isset( $_POST['licence_id'] ) && intval( $_POST['licence_id'] ) > 0 ) {
            $result = self::handle_update_licence();
        } else {
            $result = self::handle_add_licence();
        }
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