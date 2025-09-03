<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Affiliation Form Class
 * Handles club affiliation form with transactional processing
 */
class UFSC_Affiliation_Form {

    /**
     * Initialize affiliation form functionality
     */
    public static function init() {
        add_shortcode( 'ufsc_affiliation_form', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'admin_post_ufsc_create_club', array( __CLASS__, 'handle_form_submission' ) );
        add_action( 'admin_post_nopriv_ufsc_create_club', array( __CLASS__, 'handle_form_submission' ) );
        add_action( 'wp_ajax_ufsc_affiliation_pay', array( __CLASS__, 'ajax_affiliation_pay' ) );
        add_action( 'wp_ajax_nopriv_ufsc_affiliation_pay', array( __CLASS__, 'ajax_affiliation_pay' ) );
        add_action( 'admin_post_ufsc_affiliation_pay', array( __CLASS__, 'handle_affiliation_pay' ) );
        add_action( 'admin_post_nopriv_ufsc_affiliation_pay', array( __CLASS__, 'handle_affiliation_pay' ) );
    }

    /**
     * Enqueue affiliation payment script
     */
    private static function enqueue_scripts() {
        if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
            return;
        }

        wp_enqueue_script(
            'ufsc-affiliation',
            UFSC_CL_URL . 'assets/js/ufsc-affiliation.js',
            array( 'jquery' ),
            UFSC_CL_VERSION,
            true
        );

        wp_localize_script(
            'ufsc-affiliation',
            'ufscAffiliation',
            array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
                'nonce'        => wp_create_nonce( 'ufsc_affiliation_pay' ),
            )
        );
    }

    /**
     * Render the affiliation form shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Form HTML
     */
    public static function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'redirect_to' => '',
            'show_title' => '1'
        ), $atts, 'ufsc_affiliation_form' );

        if ( ! is_user_logged_in() ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Vous devez être connecté pour créer un club.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $user_id = get_current_user_id();
        
        // Check if user already has a club
        if ( self::user_has_club( $user_id ) ) {
            $dashboard_url = self::get_dashboard_url();
            return '<div class="ufsc-message ufsc-info">' . 
                   esc_html__( 'Vous avez déjà un club associé à votre compte.', 'ufsc-clubs' ) . 
                   ( $dashboard_url ? ' <a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Voir le tableau de bord', 'ufsc-clubs' ) . '</a>' : '' ) .
                   '</div>';
        }

        // Handle success message
        $success_message = '';
        if ( isset( $_GET['created'] ) && $_GET['created'] == '1' ) {
            $success_message = '<div class="ufsc-message ufsc-success">' . 
                              esc_html__( 'Votre club a été créé avec succès ! Il sera examiné par nos équipes.', 'ufsc-clubs' ) . 
                              '</div>';
        }

        // Handle error message
        $error_message = '';
        if ( isset( $_GET['error'] ) ) {
            $error_message = '<div class="ufsc-message ufsc-error">' .
                            esc_html( sanitize_text_field( $_GET['error'] ) ) .
                            '</div>';
        }

        self::enqueue_scripts();

        ob_start();
        ?>
        <div class="ufsc-affiliation-form">
            <?php if ( $atts['show_title'] ) : ?>
            <h2><?php echo esc_html__( 'Créer un club', 'ufsc-clubs' ); ?></h2>
            <?php endif; ?>

            <div id="ufsc-form-status" aria-live="polite">
                <?php echo $success_message; ?>
                <?php echo $error_message; ?>
            </div>


            <div class="ufsc-notices" aria-live="polite"></div>

            <div class="ufsc-notices" aria-live="polite"></div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-form">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-form ufsc-grid" enctype="multipart/form-data">

                <?php wp_nonce_field( 'ufsc_create_club', 'ufsc_nonce' ); ?>
                <input type="hidden" name="action" value="ufsc_create_club">
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $atts['redirect_to'] ); ?>">

                <div class="ufsc-form-section">
                    <h3><?php echo esc_html__( 'Informations du club', 'ufsc-clubs' ); ?></h3>

                    <div class="ufsc-field">
                        <label for="club_nom" class="required"><?php echo esc_html__( 'Nom du club', 'ufsc-clubs' ); ?> *</label>
                        <input type="text" id="club_nom" name="club_nom" required maxlength="255" aria-describedby="club_nom_error"
                               value="<?php echo esc_attr( self::get_form_value( 'club_nom' ) ); ?>">
                        <p id="club_nom_error" class="ufsc-error-message"></p>
                    </div>

                    <div class="ufsc-field">
                        <label for="club_email" class="required"><?php echo esc_html__( 'Email du club', 'ufsc-clubs' ); ?> *</label>
                        <input type="email" id="club_email" name="club_email" required aria-describedby="club_email_error"
                               value="<?php echo esc_attr( self::get_form_value( 'club_email' ) ); ?>">
                        <p id="club_email_error" class="ufsc-error-message"></p>
                    </div>

                    <div class="ufsc-field">
                        <label for="club_region" class="required"><?php echo esc_html__( 'Région', 'ufsc-clubs' ); ?> *</label>
                        <select id="club_region" name="club_region" required aria-describedby="club_region_error">
                            <option value=""><?php echo esc_html__( '-- Sélectionnez une région --', 'ufsc-clubs' ); ?></option>
                            <?php foreach ( self::get_regions() as $region ) : ?>
                            <option value="<?php echo esc_attr( $region ); ?>" <?php selected( self::get_form_value( 'club_region' ), $region ); ?>>
                                <?php echo esc_html( $region ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="club_region_error" class="ufsc-error-message"></p>
                    </div>

                    <div class="ufsc-field">
                        <label for="club_statuts"><?php echo esc_html__( 'Statuts du club', 'ufsc-clubs' ); ?></label>
                        <input type="file" id="club_statuts" name="club_statuts" aria-describedby="club_statuts_help club_statuts_error">
                        <p id="club_statuts_help" class="ufsc-help"><?php echo esc_html__( 'Format PDF, 2 Mo max.', 'ufsc-clubs' ); ?></p>
                        <p id="club_statuts_error" class="ufsc-error-message"></p>
                    </div>

                    <div class="ufsc-field">
                        <label for="responsable_id_doc"><?php echo esc_html__( 'Pièce d\'identité du responsable', 'ufsc-clubs' ); ?></label>
                        <input type="file" id="responsable_id_doc" name="responsable_id_doc" aria-describedby="responsable_id_doc_help responsable_id_doc_error">
                        <p id="responsable_id_doc_help" class="ufsc-help"><?php echo esc_html__( 'PDF ou image, 2 Mo max.', 'ufsc-clubs' ); ?></p>
                        <p id="responsable_id_doc_error" class="ufsc-error-message"></p>
                    </div>
                </div>

                <div class="ufsc-form-section">
                    <h3><?php echo esc_html__( 'Adresse', 'ufsc-clubs' ); ?></h3>

                    <div class="ufsc-field">
                        <label for="club_adresse"><?php echo esc_html__( 'Adresse', 'ufsc-clubs' ); ?></label>
                        <input type="text" id="club_adresse" name="club_adresse" aria-describedby="club_adresse_error"
                               value="<?php echo esc_attr( self::get_form_value( 'club_adresse' ) ); ?>">
                        <p id="club_adresse_error" class="ufsc-error-message"></p>
                    </div>

                    <div class="ufsc-field-group">
                        <div class="ufsc-field">
                            <label for="club_code_postal"><?php echo esc_html__( 'Code postal', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="club_code_postal" name="club_code_postal" maxlength="10" aria-describedby="club_code_postal_error"
                                   value="<?php echo esc_attr( self::get_form_value( 'club_code_postal' ) ); ?>">
                            <p id="club_code_postal_error" class="ufsc-error-message"></p>
                        </div>
                        <div class="ufsc-field">
                            <label for="club_ville"><?php echo esc_html__( 'Ville', 'ufsc-clubs' ); ?></label>
                            <input type="text" id="club_ville" name="club_ville" aria-describedby="club_ville_error"
                                   value="<?php echo esc_attr( self::get_form_value( 'club_ville' ) ); ?>">
                            <p id="club_ville_error" class="ufsc-error-message"></p>
                        </div>
                    </div>
                </div>

                <div class="ufsc-form-section">
                    <h3><?php echo esc_html__( 'Contact', 'ufsc-clubs' ); ?></h3>

                    <div class="ufsc-field">
                        <label for="club_telephone"><?php echo esc_html__( 'Téléphone', 'ufsc-clubs' ); ?></label>
                        <input type="tel" id="club_telephone" name="club_telephone" aria-describedby="club_telephone_error"
                               value="<?php echo esc_attr( self::get_form_value( 'club_telephone' ) ); ?>">
                        <p id="club_telephone_error" class="ufsc-error-message"></p>
                    </div>
                </div>

                <div class="ufsc-form-section">
                    <div class="ufsc-field ufsc-checkbox-row">
                        <label for="accept_cgu">
                            <input type="checkbox" id="accept_cgu" name="accept_cgu" value="1" required aria-describedby="accept_cgu_error"
                                   <?php checked( self::get_form_value( 'accept_cgu' ), '1' ); ?>>
                            <?php echo esc_html__( 'J\'accepte les conditions générales d\'utilisation', 'ufsc-clubs' ); ?> *
                        </label>
                        <p id="accept_cgu_error" class="ufsc-error-message"></p>
                    </div>
                </div>

                <div class="ufsc-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__( 'Créer le club', 'ufsc-clubs' ); ?>
                    </button>
                </div>
            </form>
            <?php if ( function_exists( 'ufsc_is_woocommerce_active' ) && ufsc_is_woocommerce_active() ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-affiliation-pay-form">
                <?php wp_nonce_field( 'ufsc_affiliation_pay', 'ufsc_affiliation_nonce' ); ?>
                <input type="hidden" name="action" value="ufsc_affiliation_pay">
                <button type="submit" id="ufsc-pay-affiliation" class="button button-secondary">
                    <?php echo esc_html__( 'Payer mon affiliation', 'ufsc-clubs' ); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php

        return ob_get_clean();
    }

    /**
     * AJAX handler to add affiliation product to cart
     */
    public static function ajax_affiliation_pay() {
        check_ajax_referer( 'ufsc_affiliation_pay', 'nonce' );

        if ( ! function_exists( 'ufsc_is_woocommerce_active' ) || ! ufsc_is_woocommerce_active() ) {
            wp_send_json_error();
        }

        $settings   = ufsc_get_woocommerce_settings();
        $product_id = absint( $settings['product_affiliation_id'] );
        $added      = $product_id ? WC()->cart->add_to_cart( $product_id ) : false;

        if ( $added ) {
            wp_send_json_success( array( 'redirect' => wc_get_checkout_url() ) );
        }

        wp_send_json_error();
    }

    /**
     * Handle non-AJAX affiliation payment form
     */
    public static function handle_affiliation_pay() {
        if ( ! isset( $_POST['ufsc_affiliation_nonce'] ) || ! wp_verify_nonce( $_POST['ufsc_affiliation_nonce'], 'ufsc_affiliation_pay' ) ) {
            wp_die( esc_html__( 'Erreur de sécurité. Veuillez réessayer.', 'ufsc-clubs' ) );
        }

        if ( function_exists( 'ufsc_is_woocommerce_active' ) && ufsc_is_woocommerce_active() ) {
            $settings   = ufsc_get_woocommerce_settings();
            $product_id = absint( $settings['product_affiliation_id'] );

            if ( $product_id ) {
                WC()->cart->add_to_cart( $product_id );
            }
        }

        wp_safe_redirect( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url() );
        exit;
    }

    /**
     * Handle form submission
     */
    public static function handle_form_submission() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['ufsc_nonce'], 'ufsc_create_club' ) ) {
            wp_die( esc_html__( 'Erreur de sécurité. Veuillez réessayer.', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Vous devez être connecté pour créer un club.', 'ufsc-clubs' ) );
        }

        $user_id = get_current_user_id();
        $redirect_url = self::get_redirect_url();

        // Check if user already has a club
        if ( self::user_has_club( $user_id ) ) {
            wp_safe_redirect( add_query_arg( 'error', urlencode( __( 'Vous avez déjà un club associé.', 'ufsc-clubs' ) ), $redirect_url ) );
            exit;
        }

        // Validate and sanitize input
        $club_data = self::validate_and_sanitize_input( $_POST );
        
        if ( is_wp_error( $club_data ) ) {
            wp_safe_redirect( add_query_arg( 'error', urlencode( $club_data->get_error_message() ), $redirect_url ) );
            exit;
        }

        // Create club with transaction
        $result = UFSC_Transaction::with_lock( "club_creation_{$user_id}", function() use ( $club_data, $user_id ) {
            return self::create_club_transactional( $club_data, $user_id );
        } );

        if ( $result ) {
            $success_url = ! empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : $redirect_url;
            wp_safe_redirect( add_query_arg( 'created', '1', $success_url ) );
        } else {
            wp_safe_redirect( add_query_arg( 'error', urlencode( __( 'Erreur lors de la création du club. Veuillez réessayer.', 'ufsc-clubs' ) ), $redirect_url ) );
        }
        exit;
    }

    /**
     * Create club with transactional safety and idempotence
     * 
     * @param array $club_data Club data
     * @param int $user_id User ID
     * @return int|false Club ID on success, false on failure
     */
    private static function create_club_transactional( $club_data, $user_id ) {
        global $wpdb;

        // Create idempotence key
        $event_key = "club_creation_{$user_id}_" . md5( $club_data['nom'] );

        // Check if this operation already exists
        if ( UFSC_DB_Migrations::event_exists( $event_key ) ) {
            // Find existing club
            $settings = UFSC_SQL::get_settings();
            $existing_club = $wpdb->get_var( $wpdb->prepare( 
                "SELECT id FROM `{$settings['table_clubs']}` WHERE responsable_id = %d AND nom = %s",
                $user_id,
                $club_data['nom']
            ) );
            
            if ( $existing_club ) {
                return (int) $existing_club;
            }
        }

        // Record event for idempotence
        UFSC_DB_Migrations::record_event( $event_key, 'club_creation', array(
            'user_id' => $user_id,
            'club_name' => $club_data['nom']
        ) );

        // Prepare club data for insertion
        $insert_data = array_merge( $club_data, array(
            'responsable_id' => $user_id,
            'date_creation' => current_time( 'mysql' ),
            'statut' => 'en_attente' // Default status
        ) );

        $settings = UFSC_SQL::get_settings();
        $result = $wpdb->insert( $settings['table_clubs'], $insert_data );

        if ( $result === false ) {
            UFSC_DB_Migrations::update_event_status( $event_key, 'failed' );
            throw new Exception( 'Database insertion failed: ' . $wpdb->last_error );
        }

        $club_id = $wpdb->insert_id;
        UFSC_DB_Migrations::update_event_status( $event_key, 'completed' );

        // Log creation
        if ( class_exists( 'UFSC_CL_Utils' ) ) {
            UFSC_CL_Utils::log( "Club créé via formulaire d'affiliation: ID {$club_id}, User {$user_id}", 'info' );
        }

        return $club_id;
    }

    /**
     * Validate and sanitize form input
     * 
     * @param array $input Raw POST data
     * @return array|WP_Error Sanitized data or error
     */
    private static function validate_and_sanitize_input( $input ) {
        $errors = array();
        $data = array();

        // Required fields
        $required_fields = array(
            'club_nom' => 'nom',
            'club_email' => 'email',
            'club_region' => 'region'
        );

        foreach ( $required_fields as $input_key => $db_key ) {
            if ( empty( $input[ $input_key ] ) ) {
                $errors[] = sprintf( __( 'Le champ %s est requis.', 'ufsc-clubs' ), $input_key );
            } else {
                $data[ $db_key ] = sanitize_text_field( $input[ $input_key ] );
            }
        }

        // Optional fields
        $optional_fields = array(
            'club_adresse' => 'adresse',
            'club_code_postal' => 'code_postal',
            'club_ville' => 'ville',
            'club_telephone' => 'telephone'
        );

        foreach ( $optional_fields as $input_key => $db_key ) {
            if ( ! empty( $input[ $input_key ] ) ) {
                $data[ $db_key ] = sanitize_text_field( $input[ $input_key ] );
            }
        }

        // Validate email
        if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Adresse email invalide.', 'ufsc-clubs' );
        }

        // Check CGU acceptance
        if ( empty( $input['accept_cgu'] ) ) {
            $errors[] = __( 'Vous devez accepter les conditions générales d\'utilisation.', 'ufsc-clubs' );
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ' ', $errors ) );
        }

        return $data;
    }

    /**
     * Check if user already has a club
     * 
     * @param int $user_id User ID
     * @return bool
     */
    private static function user_has_club( $user_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM `{$settings['table_clubs']}` WHERE responsable_id = %d LIMIT 1", 
            $user_id 
        ) );
        
        return ! is_null( $result );
    }

    /**
     * Get available regions
     * 
     * @return array
     */
    private static function get_regions() {
        // This should match the regions defined in your system
        return array(
            'Auvergne-Rhône-Alpes',
            'Bourgogne-Franche-Comté',
            'Bretagne',
            'Centre-Val de Loire',
            'Corse',
            'Grand Est',
            'Hauts-de-France',
            'Île-de-France',
            'Normandie',
            'Nouvelle-Aquitaine',
            'Occitanie',
            'Pays de la Loire',
            'Provence-Alpes-Côte d\'Azur',
            'DOM-TOM'
        );
    }

    /**
     * Get form value for repopulation
     * 
     * @param string $key Field key
     * @return string
     */
    private static function get_form_value( $key ) {
        return isset( $_POST[ $key ] ) ? sanitize_text_field( $_POST[ $key ] ) : '';
    }

    /**
     * Get redirect URL
     * 
     * @return string
     */
    private static function get_redirect_url() {
        return wp_get_referer() ?: home_url();
    }

    /**
     * Get dashboard URL
     * 
     * @return string|null
     */
    private static function get_dashboard_url() {
        $dashboard_page = get_option( 'ufsc_dashboard_page' );
        if ( $dashboard_page ) {
            $url = get_permalink( $dashboard_page );
            if ( $url ) {
                return $url;
            }
        }

        if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
            return wc_get_account_endpoint_url( 'ufsc-tableau-de-bord' );
        }

        return home_url( '/tableau-de-bord/' );
    }
}