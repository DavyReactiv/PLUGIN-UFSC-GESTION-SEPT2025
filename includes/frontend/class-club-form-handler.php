<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Club Form Handler for processing form submissions
 */
class UFSC_CL_Club_Form_Handler {
    
    /**
     * Initialize form handlers
     */
    public static function init() {
        add_action( 'admin_post_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
        add_action( 'admin_post_nopriv_ufsc_save_club', array( __CLASS__, 'handle_save_club' ) );
    }
    
    /**
     * Handle club form submission
     */
    public static function handle_save_club() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['ufsc_club_nonce'] ?? '', 'ufsc_save_club' ) ) {
            wp_die( __( 'Erreur de sécurité. Veuillez réessayer.', 'ufsc-clubs' ) );
        }
        
        $club_id = (int) ( $_POST['club_id'] ?? 0 );
        $affiliation = (bool) ( $_POST['affiliation'] ?? false );
        $is_edit = $club_id > 0;
        
        // Permission checks
        if ( $is_edit && ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            self::redirect_with_error( __( 'Vous n\'avez pas les permissions pour éditer ce club.', 'ufsc-clubs' ), $club_id, $affiliation );
            return;
        }
        
        if ( ! $is_edit && ! UFSC_CL_Permissions::ufsc_user_can_create_club() ) {
            self::redirect_with_error( __( 'Vous devez être connecté pour créer un club.', 'ufsc-clubs' ), $club_id, $affiliation );
            return;
        }
        
        // Collect and sanitize input data
        $data = self::collect_and_sanitize_data();
        
        // Validate required fields
        $validation_errors = UFSC_CL_Utils::validate_club_data( $data, $affiliation );
        
        if ( ! empty( $validation_errors ) ) {
            $error_message = implode( ', ', $validation_errors );
            self::redirect_with_error( $error_message, $club_id, $affiliation );
            return;
        }
        
        try {
            // Handle file uploads
            $upload_result = self::handle_file_uploads( $data, $club_id );
            if ( is_wp_error( $upload_result ) ) {
                self::redirect_with_error( $upload_result->get_error_message(), $club_id, $affiliation );
                return;
            }
            
            // Merge upload results into data
            $data = array_merge( $data, $upload_result );
            
            // Handle user association for new club creation
            if ( ! $is_edit ) {
                $responsable_id = self::handle_user_association( $affiliation );
                if ( is_wp_error( $responsable_id ) ) {
                    self::redirect_with_error( $responsable_id->get_error_message(), $club_id, $affiliation );
                    return;
                }
                $data['responsable_id'] = $responsable_id;
            }
            
            // Set default status for new clubs if not admin
            if ( ! $is_edit && ! current_user_can( 'manage_options' ) ) {
                $statuses = UFSC_SQL::statuses();
                if ( isset( $statuses['en_attente'] ) ) {
                    $data['statut'] = 'en_attente';
                } else {
                    $keys = array_keys( $statuses );
                    $data['statut'] = $keys[0] ?? '';
                }
            }
            
            // Save to database
            $result_club_id = self::save_club_data( $data, $club_id );

            if ( is_wp_error( $result_club_id ) ) {
                self::redirect_with_error( $result_club_id->get_error_message(), $club_id, $affiliation );
                return;
            }

            // Update document meta for new clubs
            if ( $result_club_id && ! empty( $upload_result ) ) {
                foreach ( $upload_result as $doc_key => $url ) {
                    if ( empty( $url ) ) {
                        continue;
                    }
                    update_post_meta( $result_club_id, $doc_key, $url );
                    if ( strpos( $doc_key, 'doc_' ) === 0 ) {
                        update_post_meta( $result_club_id, $doc_key . '_status', 'pending' );
                    }
                }
            }

            // Post-save actions
            self::handle_post_save_actions( $result_club_id, $affiliation, $is_edit );

            // Handle WooCommerce cart redirection when in affiliation mode
            if ( $affiliation ) {
                self::handle_affiliation_redirect( $result_club_id, $affiliation );
            }

            // Build success message
            $success_message = $is_edit
                ? __( 'Club mis à jour avec succès.', 'ufsc-clubs' )
                : __( 'Club créé avec succès.', 'ufsc-clubs' );

            // Append cart link if WooCommerce is active and not in affiliation mode
            if ( ! $affiliation && function_exists( 'wc_get_cart_url' ) ) {
                $cart_url = wc_get_cart_url();
                $success_message .= ' <a href="' . esc_url( $cart_url ) . '" class="button ufsc-view-cart">' . __( 'Voir le panier', 'ufsc-clubs' ) . '</a>';
            }

            self::redirect_with_success( $success_message, $result_club_id, $affiliation );

        } catch ( Exception $e ) {
            UFSC_CL_Utils::log( 'Erreur lors de la sauvegarde du club: ' . $e->getMessage(), 'error' );
            self::redirect_with_error( __( 'Une erreur est survenue lors de la sauvegarde.', 'ufsc-clubs' ), $club_id, $affiliation );
        }
    }

    /**
     * Add affiliation product to cart and redirect user to the configured URL.
     *
     * @param int  $club_id     Saved club ID.
     * @param bool $affiliation Whether affiliation mode is active.
     */
    private static function handle_affiliation_redirect( $club_id, $affiliation ) {
        if ( function_exists( 'ufsc_add_affiliation_to_cart' ) ) {
            ufsc_add_affiliation_to_cart( $club_id );
        }

        $redirect_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';

        /**
         * Filter the redirect URL after a club is saved in affiliation mode.
         *
         * @param string $redirect_url Default redirect URL.
         * @param int    $club_id      Saved club ID.
         * @param bool   $affiliation  Whether affiliation mode is active.
         */
        $redirect_url = apply_filters( 'ufsc_club_affiliation_redirect_url', $redirect_url, $club_id, $affiliation );

        if ( $redirect_url ) {
            wp_safe_redirect( ufsc_redirect_with_notice( $redirect_url, 'affiliation_added' ) );
            exit;
        }
    }

    /**
     * Collect and sanitize form data
     *
     * @return array Sanitized form data
     */
    private static function collect_and_sanitize_data() {
        $data = array();
        $club_fields = UFSC_SQL::get_club_fields();
        
        foreach ( $club_fields as $field_key => $field_config ) {
            if ( isset( $_POST[$field_key] ) ) {
                $value = wp_unslash( $_POST[$field_key] );
                
                // Sanitize based on field type
                switch ( $field_config[1] ?? 'text' ) {
                    case 'email':
                        $data[$field_key] = sanitize_email( $value );
                        break;
                    case 'url':
                        $data[$field_key] = esc_url_raw( $value );
                        break;
                    case 'number':
                        $data[$field_key] = (int) $value;
                        break;
                    case 'date':
                        $data[$field_key] = sanitize_text_field( $value );
                        break;
                    default:
                        $data[$field_key] = sanitize_text_field( $value );
                        break;
                }
            }
        }
        
        return $data;
    }
    
    /**

     * Handle file uploads for documents and logo

     * Handle file uploads for club documents with validation.

     *
     * @param array $data Current form data
     * @param int $club_id Club ID for meta updates
     * @return array|WP_Error Upload results or error
     */
    private static function handle_file_uploads( $data, $club_id = 0 ) {
        $upload_results = array();
        

        // Handle logo upload
        if ( ! empty( $_FILES['logo_upload']['name'] ) ) {
            $logo_id = UFSC_Uploads::handle_single_upload_field( 'logo_upload', $club_id, UFSC_Uploads::get_logo_mime_types(), UFSC_Uploads::get_logo_max_size() );
            
            if ( is_wp_error( $logo_id ) ) {
                return $logo_id;
            }
            
            $logo_url = wp_get_attachment_url( $logo_id );
            $upload_results['logo_url'] = $logo_url;
            if ( $club_id ) {
                update_post_meta( $club_id, 'logo_url', $logo_id );
            }
        }
        
        // Handle required document uploads
        $doc_results = UFSC_Uploads::handle_required_docs( $club_id );
        if ( is_wp_error( $doc_results ) ) {
            return $doc_results;
        }
        foreach ( $doc_results as $meta => $attach_id ) {
            if ( $club_id ) {
                update_post_meta( $club_id, $meta, $attach_id );
                update_post_meta( $club_id, $meta . '_status', 'pending' );
            }
        }


        $upload_results = array_merge( $upload_results, $doc_results );

        return $upload_results;
    }
    
    /**
     * Handle user association for new clubs
     * 
     * @param bool $affiliation Whether this is for affiliation
     * @return int|WP_Error User ID or error
     */
    private static function handle_user_association( $affiliation ) {
        if ( ! $affiliation ) {
            // For non-affiliation, just use current user
            return get_current_user_id();
        }
        
        $association = sanitize_text_field( $_POST['user_association'] ?? 'current' );
        
        switch ( $association ) {
            case 'current':
                return get_current_user_id();
                
            case 'create':
                return self::create_new_user();
                
            case 'existing':
                if ( ! current_user_can( 'manage_options' ) ) {
                    return new WP_Error( 'permission_denied', __( 'Permissions insuffisantes pour associer un utilisateur existant.', 'ufsc-clubs' ) );
                }
                
                $user_id = (int) ( $_POST['existing_user_id'] ?? 0 );
                if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
                    return new WP_Error( 'invalid_user', __( 'Utilisateur inexistant.', 'ufsc-clubs' ) );
                }
                
                return $user_id;
                
            default:
                return get_current_user_id();
        }
    }
    
    /**
     * Create a new user for club association
     * 
     * @return int|WP_Error User ID or error
     */
    private static function create_new_user() {
        $login = sanitize_user( $_POST['new_user_login'] ?? '' );
        $email = sanitize_email( $_POST['new_user_email'] ?? '' );
        $display_name = sanitize_text_field( $_POST['new_user_display_name'] ?? '' );
        
        if ( empty( $login ) || empty( $email ) ) {
            return new WP_Error( 'missing_user_data', __( 'Nom d\'utilisateur et email requis pour créer un compte.', 'ufsc-clubs' ) );
        }
        
        if ( username_exists( $login ) ) {
            return new WP_Error( 'username_exists', __( 'Ce nom d\'utilisateur existe déjà.', 'ufsc-clubs' ) );
        }
        
        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', __( 'Cet email est déjà utilisé.', 'ufsc-clubs' ) );
        }
        
        // Generate password
        $password = wp_generate_password( 12, false );
        
        $user_id = wp_create_user( $login, $password, $email );
        
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }
        
        // Update display name
        if ( ! empty( $display_name ) ) {
            wp_update_user( array(
                'ID' => $user_id,
                'display_name' => $display_name
            ) );
        }
        
        // Send password to user
        wp_new_user_notification( $user_id, null, 'user' );
        
        return $user_id;
    }
    
    /**
     * Save club data to database
     * 
     * @param array $data Club data
     * @param int $club_id Club ID for update (0 for insert)
     * @return int|WP_Error Club ID or error
     */
    private static function save_club_data( $data, $club_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = $settings['pk_club'];
        
        try {
            if ( $club_id > 0 ) {
                // Update existing club
                $result = $wpdb->update( $table, $data, array( $pk => $club_id ) );
                
                if ( $result === false ) {
                    throw new Exception( __( 'Erreur lors de la mise à jour du club.', 'ufsc-clubs' ) );
                }
                
                UFSC_CL_Utils::log( 'Club mis à jour: ID ' . $club_id, 'info' );
                return $club_id;
                
            } else {
                // Insert new club
                $data['date_creation'] = current_time( 'mysql' );
                
                $result = $wpdb->insert( $table, $data );
                
                if ( $result === false ) {
                    throw new Exception( __( 'Erreur lors de la création du club.', 'ufsc-clubs' ) );
                }
                
                $new_club_id = $wpdb->insert_id;
                UFSC_CL_Utils::log( 'Nouveau club créé: ID ' . $new_club_id, 'info' );
                return $new_club_id;
            }
            
        } catch ( Exception $e ) {
            UFSC_CL_Utils::log( 'Erreur base de données: ' . $e->getMessage(), 'error' );
            return new WP_Error( 'db_error', $e->getMessage() );
        }
    }
    
    /**
     * Handle post-save actions (notifications, etc.)
     * 
     * @param int $club_id Club ID
     * @param bool $affiliation Whether this is affiliation
     * @param bool $is_edit Whether this is an edit
     */
    private static function handle_post_save_actions( $club_id, $affiliation, $is_edit ) {
        // Send admin notification for new non-affiliation clubs
        if ( ! $is_edit && ! $affiliation ) {
            self::send_admin_notification( $club_id );
        }
        
        // TODO: Add hooks for extensibility
        do_action( 'ufsc_club_saved', $club_id, $affiliation, $is_edit );
    }
    
    /**
     * Send admin notification email for new club
     * 
     * @param int $club_id Club ID
     */
    private static function send_admin_notification( $club_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $pk = $settings['pk_club'];
        
        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT nom, region, email FROM `{$table}` WHERE `{$pk}` = %d",
            $club_id
        ) );
        
        if ( ! $club ) {
            return;
        }
        
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( __( '[%s] Nouveau club créé: %s', 'ufsc-clubs' ), get_bloginfo( 'name' ), $club->nom );
        
        $message = sprintf(
            __( "Un nouveau club a été créé:\n\nNom: %s\nRégion: %s\nEmail: %s\n\nVeuillez vous connecter à l'administration pour le valider.", 'ufsc-clubs' ),
            $club->nom,
            $club->region,
            $club->email
        );
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Redirect with error message
     * 
     * @param string $message Error message
     * @param int $club_id Club ID
     * @param bool $affiliation Affiliation mode
     */
    private static function redirect_with_error( $message, $club_id, $affiliation ) {
        $redirect_url = wp_get_referer() ?: home_url();
        $redirect_url = add_query_arg( array(
            'ufsc_error' => urlencode( $message )
        ), $redirect_url );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Redirect with success message
     * 
     * @param string $message Success message
     * @param int $club_id Club ID
     * @param bool $affiliation Affiliation mode
     */
    private static function redirect_with_success( $message, $club_id, $affiliation ) {
        $redirect_url = wp_get_referer() ?: home_url();
        $redirect_url = add_query_arg( array(
            'ufsc_success' => urlencode( $message )
        ), $redirect_url );
        
        wp_safe_redirect( $redirect_url );
        exit;
    }
}

// Initialize the handler
add_action( 'init', array( 'UFSC_CL_Club_Form_Handler', 'init' ) );
