<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * // UFSC: Unified Form Handlers
 * Handles license and club form submissions with proper security and validation
 */
class UFSC_Unified_Handlers {

    /** Normalize the only supported licence workflow intentions. */
    public static function normalize_licence_intent( $action ) {
        $action = sanitize_key( (string) $action );
        return in_array( $action, array( 'save_draft', 'continue', 'verify', 'submit_for_validation', 'add_to_cart' ), true ) ? $action : 'continue';
    }

    /** Decide whether an explicit form intent may mutate the WooCommerce cart. */
    public static function should_add_licence_to_cart( $action ) { return 'add_to_cart' === self::normalize_licence_intent( $action ); }

    /** Normalize a declared former licence number without assigning a UFSC number. */
    public static function normalize_previous_licence_number( $enabled, $number ) {
        if ( ! $enabled ) { return ''; }
        $number = strtoupper( trim( (string) $number ) );
        return preg_match( '/^[A-Z0-9]{1,10}$/', $number ) ? $number : false;
    }

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
        add_action( 'admin_post_ufsc_update_licence_weight', array( __CLASS__, 'handle_update_licence_weight' ) );
        add_action( 'admin_post_ufsc_renew_affiliation', array( __CLASS__, 'handle_renew_affiliation' ) );
        add_action( 'admin_post_ufsc_renew_licence', array( __CLASS__, 'handle_renew_licence' ) );

        add_action( 'admin_post_ufsc_delete_licence', array( __CLASS__, 'handle_delete_licence' ) );
        add_action( 'admin_post_nopriv_ufsc_delete_licence', array( __CLASS__, 'handle_delete_licence' ) );
        add_action( 'admin_post_ufsc_cancel_licence', array( __CLASS__, 'handle_cancel_licence' ) );
        add_action( 'admin_post_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );
        add_action( 'admin_post_nopriv_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );
        add_action( 'admin_post_ufsc_assign_bureau_role', array( __CLASS__, 'handle_assign_bureau_role' ) );
        add_action( 'admin_post_nopriv_ufsc_assign_bureau_role', array( __CLASS__, 'handle_assign_bureau_role' ) );
        add_action( 'admin_post_ufsc_sync_licence_statuses', array( __CLASS__, 'handle_sync_licence_statuses' ) );

        // UFSC PATCH: Licence document handlers
        add_action( 'admin_post_ufsc_upload_licence_document', array( __CLASS__, 'handle_upload_licence_document' ) );
        add_action( 'admin_post_ufsc_remove_licence_document', array( __CLASS__, 'handle_remove_licence_document' ) );
        add_action( 'admin_post_ufsc_upload_honorability_attestation', array( __CLASS__, 'handle_upload_honorability_attestation' ) );
        add_action( 'admin_post_ufsc_decide_honorability_attestation', array( __CLASS__, 'handle_decide_honorability_attestation' ) );


        
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
     * Handle licence save (create or update based on presence of licence_id).
     */
    public static function handle_save_licence() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_save_licence' );

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;

        self::process_licence_request( $licence_id );
    }

    /**
     * Handle licence creation.
     */
    public static function handle_add_licence() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_add_licence' );

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

        $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id ) : array( 'allowed' => false, 'message' => __( 'Votre club doit renouveler et faire activer son affiliation avant de souscrire ou renouveler des licences.', 'ufsc-clubs' ) );
        if ( empty( $gate['allowed'] ) ) {
            if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'front_add_licence' ); }
            self::redirect_with_error( $gate['message'] );
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
        self::maybe_redirect( $redirect_url );
        return;
    }

    /**
     * Handle licence update
     */
    public static function handle_update_licence() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;

        if ( $licence_id <= 0 ) {
            self::handle_save_licence();
            return;
        }

        check_admin_referer( 'ufsc_update_licence' );

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


        $user_id        = get_current_user_id();
        $managed_club   = ufsc_get_user_club_id( $user_id );
        $target_club_id = isset( $_POST['club_id'] ) ? intval( $_POST['club_id'] ) : $managed_club;
        $can_manage_all = self::can_manage_all_clubs();

        if ( $target_club_id <= 0 && $can_manage_all ) {
            $target_club_id = self::resolve_licence_club_id( $licence_id );
        }

        // Ensure current user can manage the target club
        if ( ! $can_manage_all && $managed_club !== $target_club_id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            self::maybe_redirect( wp_get_referer() );
            return; // Abort processing when permission check fails
        }

        $club_id = $target_club_id;


        if ( ! $club_id ) {
            self::redirect_with_error( 'Aucun club associé à votre compte', $licence_id );
            return;
        }

        $licence = self::get_licence_row( $licence_id, $club_id );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        if ( function_exists( 'ufsc_is_licence_locked_for_club' ) && ufsc_is_licence_locked_for_club( $licence ) ) {
            self::maybe_redirect( add_query_arg( 'view_licence', $licence_id, wp_get_referer() ) );
            return;
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
        self::maybe_redirect( $redirect_url );
        return;
    }

    /**
     * Handle a club front-office update limited to athlete weight and detected categories.
     */
    public static function handle_update_licence_weight() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
        check_admin_referer( 'ufsc_update_licence_weight_' . $licence_id );

        if ( ! is_user_logged_in() || ! $licence_id ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        $user_id        = get_current_user_id();
        $club_id        = function_exists( 'ufsc_get_user_club_id' ) ? (int) ufsc_get_user_club_id( $user_id ) : 0;
        $can_manage_all = self::can_manage_all_clubs();
        if ( $can_manage_all && $club_id <= 0 ) {
            $club_id = self::resolve_licence_club_id( $licence_id );
        }

        if ( ! $club_id ) {
            self::redirect_with_error( 'Aucun club associé à votre compte' );
            return;
        }

        $licence = self::get_licence_row( $licence_id, $club_id );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée' );
            return;
        }

        global $wpdb;
        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        $columns        = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $licences_table ) : (array) $wpdb->get_col( "DESCRIBE `{$licences_table}`" );

        if ( ! in_array( 'poids', $columns, true ) || ! class_exists( 'UFSC_Category_Repository' ) ) {
            self::redirect_with_error( 'La mise à jour du poids n’est pas disponible.' );
            return;
        }

        $raw_weight = isset( $_POST['poids'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['poids'] ) ) : '';
        $weight     = UFSC_Category_Repository::normalize_weight( $raw_weight );
        if ( null === $weight && '' !== trim( $raw_weight ) ) {
            self::redirect_with_error( 'Poids invalide' );
            return;
        }

        $update = array( 'poids' => $weight );
        if ( null === $weight ) {
            $update['poids'] = null;
        }

        $season  = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $licence ) : UFSC_Category_Repository::DEFAULT_SEASON;
        $summary = UFSC_Category_Repository::detect_for_athlete(
            array(
                'date_naissance' => $licence->date_naissance ?? '',
                'sexe'           => $licence->sexe ?? '',
                'poids'          => $weight,
            ),
            UFSC_Category_Repository::DEFAULT_DISCIPLINE,
            $season
        );

        if ( in_array( 'categorie_age_detectee', $columns, true ) ) {
            $update['categorie_age_detectee'] = $summary['age_category_label'];
        }
        if ( in_array( 'categorie_poids_detectee', $columns, true ) ) {
            $update['categorie_poids_detectee'] = $summary['weight_category_label'];
        }
        if ( in_array( 'categorie_updated_at', $columns, true ) ) {
            $update['categorie_updated_at'] = current_time( 'mysql' );
        }

        $result = $wpdb->update( $licences_table, $update, array( 'id' => $licence_id, 'club_id' => $club_id ) );
        if ( false === $result ) {
            self::redirect_with_error( 'Mise à jour du poids impossible' );
            return;
        }

        do_action( 'ufsc_licence_updated', (int) $club_id );
        self::redirect_with_success( 'Poids mis à jour. Catégorie recalculée automatiquement.' );
    }



    /**
     * Fail closed for legacy direct affiliation renewal; WooCommerce cart is authoritative.
     */
    public static function handle_renew_affiliation() {
        if ( ! current_user_can( 'read' ) || ! is_user_logged_in() ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_renew_affiliation' );

        $club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
        if ( $club_id <= 0 ) {
            self::redirect_with_error( 'Aucun club associé à votre compte' );
            return;
        }

        $target_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        if ( '' === $target_season ) {
            self::redirect_with_error( 'Saison de renouvellement indisponible' );
            return;
        }

        self::redirect_with_error( 'Veuillez utiliser le renouvellement via panier WooCommerce.' );
        return;
    }

    /**
     * Fail closed for legacy direct licence renewal; WooCommerce cart is authoritative.
     */
    public static function handle_renew_licence() {
        if ( ! current_user_can( 'read' ) || ! is_user_logged_in() ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
        check_admin_referer( 'ufsc_renew_licence_' . $licence_id );

        if ( $licence_id <= 0 ) {
            self::redirect_with_error( 'Licence invalide' );
            return;
        }

        $club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
        if ( $club_id <= 0 ) {
            self::redirect_with_error( 'Aucun club associé à votre compte' );
            return;
        }

        $licence = self::get_licence_row( $licence_id, $club_id );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée' );
            return;
        }

        $target_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        if ( '' === $target_season ) {
            self::redirect_with_error( 'Saison de renouvellement indisponible' );
            return;
        }

        if ( function_exists( 'ufsc_is_club_affiliated_for_season' ) && ! ufsc_is_club_affiliated_for_season( $club_id, $target_season ) ) {
            self::redirect_with_error( 'Vous devez renouveler votre affiliation avant de renouveler vos licences.' );
            return;
        }

        self::redirect_with_error( 'Veuillez utiliser le renouvellement via panier WooCommerce.' );
        return;
    }

    /**
     * Handle licence deletion
     */
    public static function handle_delete_licence() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_delete_licence' );

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $licence_id = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
        $user_id    = get_current_user_id();
        $club_id    = ufsc_get_user_club_id( $user_id );
        $can_manage_all = self::can_manage_all_clubs();

        if ( $licence_id && $can_manage_all && $club_id <= 0 ) {
            $club_id = self::resolve_licence_club_id( $licence_id );
        }

        if ( ! $licence_id || ! $club_id ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        $licence = self::get_licence_row( $licence_id, $club_id );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée' );
            return;
        }

        $delete_block_reason = self::get_licence_delete_block_reason( $licence );
        if ( $delete_block_reason ) {
            self::redirect_with_error( $delete_block_reason );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $deleted = $wpdb->delete( $table, array( 'id' => $licence_id, 'club_id' => $club_id ) );
        if ( false === $deleted ) {
            self::redirect_with_error( 'Suppression impossible pour le moment' );
            return;
        }

        self::sync_current_officers_to_club_legacy( $club_id );
        do_action( 'ufsc_licence_deleted', (int) $club_id );

        $redirect_url = wp_get_referer() ?: home_url();
        $redirect_url = remove_query_arg(
            array( 'licence_id', 'view_licence', 'edit_licence', 'licence', 'id', 'licenceId', 'license_id' ),
            $redirect_url
        );

        self::redirect_with_success( 'Licence supprimée.', $redirect_url );
        return;
    }

    /**
     * Handle admin-only licence cancellation (soft status update).
     */
    public static function handle_cancel_licence() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_cancel_licence' );

        $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        $reason     = isset( $_POST['cancel_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['cancel_reason'] ) ) : '';

        if ( ! $licence_id ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        if ( '' === $reason ) {
            self::redirect_with_error( 'Le motif d\'annulation est requis.' );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];
        $columns  = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();

        $licence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $licence_id ) );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée' );
            return;
        }

        $allowed_statuses = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::allowed() : array();
        $target_status    = in_array( 'annulee', $allowed_statuses, true ) ? 'annulee' : 'refuse';

        if ( class_exists( 'UFSC_Licence_Status' ) ) {
            UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id ), $target_status, array( '%d' ) );
        } else {
            $status_col = in_array( 'statut', $columns, true ) ? 'statut' : ( in_array( 'status', $columns, true ) ? 'status' : '' );
            if ( ! $status_col ) {
                self::redirect_with_error( 'Annulation impossible avec le schéma actuel.' );
                return;
            }
            $wpdb->update( $table, array( $status_col => $target_status ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
        }

        if ( in_array( 'note', $columns, true ) ) {
            $existing_note = isset( $licence->note ) ? (string) $licence->note : '';
            $prefix        = '[Annulation] ' . $reason;
            $new_note      = trim( $existing_note . ( $existing_note ? "\n" : '' ) . $prefix );
            $wpdb->update( $table, array( 'note' => $new_note ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'licence_cancelled', array(
                'licence_id' => $licence_id,
                'club_id'    => (int) ( $licence->club_id ?? 0 ),
                'user_id'    => get_current_user_id(),
                'reason'     => $reason,
                'status'     => $target_status,
            ) );
        } else {
            error_log( sprintf( 'UFSC licence cancelled #%d by user #%d (%s)', $licence_id, get_current_user_id(), $reason ) );
        }

        do_action( 'ufsc_licence_cancelled', $licence_id, (int) ( $licence->club_id ?? 0 ), $reason );
        do_action( 'ufsc_licence_updated', (int) ( $licence->club_id ?? 0 ) );

        self::redirect_with_success( 'Licence annulée.', admin_url( 'admin.php?page=ufsc-sql-licences' ) );
        return;
    }

    /**
     * Handle licence status update
     */
    public static function handle_update_licence_status() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_update_licence_status' );

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $licence_id     = isset( $_POST['licence_id'] ) ? intval( $_POST['licence_id'] ) : 0;
        $new_status_raw = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $new_status     = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::normalize( $new_status_raw ) : strtolower( trim( $new_status_raw ) );
        $user_id        = get_current_user_id();
        $club_id        = ufsc_get_user_club_id( $user_id );

        if ( ! $licence_id || ! $club_id || ! $new_status ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        if ( ! self::get_licence_status( $licence_id, $club_id ) ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        $valid_statuses = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::allowed() : array_keys( UFSC_SQL::statuses() );
        if ( ! in_array( $new_status, $valid_statuses, true ) ) {
            self::redirect_with_error( 'Statut invalide', $licence_id );
            return;
        }
		$target_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        if ( in_array( $new_status, array( 'valide', 'validated', 'active' ), true ) && function_exists( 'ufsc_club_can_manage_licences_for_season' ) ) {
            $gate = ufsc_club_can_manage_licences_for_season( $club_id, $target_season );
            if ( empty( $gate['allowed'] ) ) { if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'admin_validate_licence', $licence_id ); } self::redirect_with_error( $gate['message'], $licence_id ); return; }
        }
		if ( in_array( $new_status, array( 'valide', 'validated', 'active' ), true ) && function_exists( 'ufsc_can_validate_licence' ) ) {
			$reasons = array();
			if ( ! ufsc_can_validate_licence( $licence_id, $reasons ) ) { self::redirect_with_error( implode( ' ', $reasons ), $licence_id ); return; }
		}

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        if ( class_exists( 'UFSC_Licence_Status' ) ) {
            UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id, 'club_id' => $club_id ), $new_status, array( '%d', '%d' ) );
        } else {
            $wpdb->update( $table, array( 'statut' => $new_status ), array( 'id' => $licence_id, 'club_id' => $club_id ) );
        }

        $redirect_url = add_query_arg( array(
            'updated_status' => 1,
            'licence_id'     => $licence_id
        ), wp_get_referer() );
        self::maybe_redirect( $redirect_url );
        return;
    }

    /**
     * Handle admin sync for legacy licence status column.
     */
    public static function handle_sync_licence_statuses() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_sync_licence_statuses' );

        $updated = class_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::sync_legacy_status_column() : 0;

        $redirect_url = add_query_arg(
            array(
                'ufsc_status_sync' => $updated,
            ),
            wp_get_referer()
        );

        self::maybe_redirect( $redirect_url );
    }

    /**
     * Assign or remove bureau role for an existing licence from front-end.
     * This remains allowed even if the licence is locked/validated.
     */
    public static function handle_assign_bureau_role() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_assign_bureau_role' );

        if ( ! is_user_logged_in() ) {
            self::redirect_with_error( 'Vous devez être connecté' );
            return;
        }

        $licence_id       = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        $requested_role   = isset( $_POST['bureau_role'] ) ? sanitize_key( wp_unslash( $_POST['bureau_role'] ) ) : '';
        $requested_club   = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
        $allowed_roles    = array( '', 'president', 'secretaire', 'tresorier', 'adherent' );

        if ( ! in_array( $requested_role, $allowed_roles, true ) || $licence_id <= 0 ) {
            self::redirect_with_error( 'Paramètres invalides' );
            return;
        }

        $user_id      = get_current_user_id();
        $managed_club = ufsc_get_user_club_id( $user_id );
        $can_manage_all = self::can_manage_all_clubs();

        if ( $requested_club <= 0 ) {
            $requested_club = $managed_club;
        }
        if ( $requested_club <= 0 && $can_manage_all ) {
            $requested_club = self::resolve_licence_club_id( $licence_id );
        }

        if ( $requested_club <= 0 ) {
            self::redirect_with_error( 'Aucun club associé à votre compte' );
            return;
        }
        if ( ! $can_manage_all && $managed_club !== $requested_club ) {
            self::redirect_with_error( 'Permissions insuffisantes' );
            return;
        }

        $licence = self::get_licence_row( $licence_id, $requested_club );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        if ( ! empty( $columns ) && ! in_array( 'role', $columns, true ) ) {
            self::redirect_with_error( 'Colonne rôle indisponible' );
            return;
        }

        $new_role = $requested_role;
        $replaced_licence_ids = array();
        if ( in_array( $new_role, array( 'president', 'secretaire', 'tresorier' ), true ) ) {
            $season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence_id ) : '';
            $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
            if ( ! $season_column || ! preg_match( '/^\d{4}-\d{4}$/', (string) $season ) ) {
                self::redirect_with_error( __( 'La saison de cette licence ne peut pas être vérifiée.', 'ufsc-clubs' ), $licence_id );
                return;
            }
            $season_sql = 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $season_matches )
                ? $wpdb->prepare( 'season_end_year = %d', (int) $season_matches[1] )
                : $wpdb->prepare( "REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', $season ) );
            $replaced_licence_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE club_id = %d AND role = %s AND id <> %d AND {$season_sql}",
                    $requested_club,
                    $new_role,
                    $licence_id
                )
            );
            $replaced_licence_ids = array_filter( array_map( 'absint', (array) $replaced_licence_ids ) );
            if ( $replaced_licence_ids ) {
                self::redirect_with_error( __( 'Cette fonction est déjà attribuée pour cette saison. Consultez la licence liée avant de changer le dirigeant.', 'ufsc-clubs' ), $licence_id );
                return;
            }
        }

        $updated = $wpdb->update(
            $table,
            array( 'role' => $new_role ),
            array(
                'id'      => $licence_id,
                'club_id' => $requested_club,
            ),
            array( '%s' ),
            array( '%d', '%d' )
        );

        if ( false === $updated ) {
            self::redirect_with_error( 'Mise à jour du bureau impossible', $licence_id );
            return;
        }

        self::sync_current_officers_to_club_legacy( $requested_club );
        do_action( 'ufsc_bureau_assignment_updated', $requested_club, $licence_id, $new_role );
        do_action( 'ufsc_licence_updated', (int) $requested_club );

        $success_message = '' === $new_role
            ? __( 'Rôle bureau retiré.', 'ufsc-clubs' )
            : sprintf( __( 'Rôle bureau mis à jour : %s.', 'ufsc-clubs' ), self::get_bureau_role_label( $new_role ) );
        self::redirect_with_success( $success_message );
    }

    /**
     * UFSC PATCH: Handle licence document upload (PDF).
     */
    public static function handle_upload_licence_document() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        check_admin_referer( 'ufsc_upload_licence_document_' . $licence_id );

        if ( ! is_user_logged_in() || ! $licence_id ) {
            self::redirect_with_error( 'Paramètres invalides', $licence_id );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $licence = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, club_id FROM {$table} WHERE id = %d", $licence_id )
        );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        if ( function_exists( 'ufsc_can_manage_licence_document' ) && ! ufsc_can_manage_licence_document( $licence_id, $licence->club_id ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        if ( empty( $_FILES['licence_document']['name'] ) ) {
            self::redirect_with_error( 'Aucun fichier fourni', $licence_id );
            return;
        }

        $upload = wp_handle_upload(
            $_FILES['licence_document'],
            array(
                'test_form' => false,
                'mimes'     => array( 'pdf' => 'application/pdf' ),
            )
        );

        if ( isset( $upload['error'] ) ) {
            self::redirect_with_error( $upload['error'], $licence_id );
            return;
        }

        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( $_FILES['licence_document']['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
        if ( $attachment_id ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
        }

        if ( ! $attachment_id ) {
            self::redirect_with_error( 'Erreur lors de l\'enregistrement du document', $licence_id );
            return;
        }

        update_option( 'ufsc_licence_document_' . $licence_id, $attachment_id );

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns   = ufsc_table_columns( $table );
            $doc_url   = wp_get_attachment_url( $attachment_id );
            $doc_field = '';
            if ( in_array( 'certificat_url', $columns, true ) ) {
                $doc_field = 'certificat_url';
            } elseif ( in_array( 'attestation_url', $columns, true ) ) {
                $doc_field = 'attestation_url';
            }

            if ( $doc_field ) {
                $wpdb->update(
                    $table,
                    array( $doc_field => $doc_url ),
                    array( 'id' => $licence_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }

        $redirect_url = add_query_arg( 'doc_updated', 1, wp_get_referer() );
        self::maybe_redirect( $redirect_url );
        return;
    }

    /**
     * UFSC PATCH: Handle licence document removal.
     */
    public static function handle_remove_licence_document() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $licence_id = isset( $_POST['licence_id'] ) ? absint( $_POST['licence_id'] ) : 0;
        check_admin_referer( 'ufsc_remove_licence_document_' . $licence_id );

        if ( ! is_user_logged_in() || ! $licence_id ) {
            self::redirect_with_error( 'Paramètres invalides', $licence_id );
            return;
        }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        $licence = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, club_id FROM {$table} WHERE id = %d", $licence_id )
        );
        if ( ! $licence ) {
            self::redirect_with_error( 'Licence non trouvée', $licence_id );
            return;
        }

        if ( function_exists( 'ufsc_can_manage_licence_document' ) && ! ufsc_can_manage_licence_document( $licence_id, $licence->club_id ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $attachment_id = (int) get_option( 'ufsc_licence_document_' . $licence_id );
        delete_option( 'ufsc_licence_document_' . $licence_id );

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns   = ufsc_table_columns( $table );
            $doc_field = '';
            if ( in_array( 'certificat_url', $columns, true ) ) {
                $doc_field = 'certificat_url';
            } elseif ( in_array( 'attestation_url', $columns, true ) ) {
                $doc_field = 'attestation_url';
            }

            if ( $doc_field ) {
                $wpdb->update(
                    $table,
                    array( $doc_field => '' ),
                    array( 'id' => $licence_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }

        if ( $attachment_id && ! empty( $_POST['delete_attachment'] ) ) {
            $usage_count = 0;
            $patterns    = array(
                $wpdb->esc_like( 'ufsc_licence_document_' ) . '%',
                $wpdb->esc_like( 'ufsc_club_doc_attestation_' ) . '%',
                $wpdb->esc_like( 'ufsc_attestation_' ) . '%',
            );

            foreach ( $patterns as $pattern ) {
                $usage_count += (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value = %s AND option_name LIKE %s",
                        (string) $attachment_id,
                        $pattern
                    )
                );
            }

            if ( $usage_count <= 1 ) {
                wp_delete_attachment( $attachment_id, true );
            }
        }

        $redirect_url = add_query_arg( 'doc_removed', 1, wp_get_referer() );
        self::maybe_redirect( $redirect_url );
        return;
    }

	public static function handle_upload_honorability_attestation() {
		$licence_id = absint( $_POST['licence_id'] ?? 0 );
		check_admin_referer( 'ufsc_honorability_attestation_' . $licence_id );
		$licence = self::get_licence_row( $licence_id, absint( $_POST['club_id'] ?? 0 ) );
		if ( ! $licence || ! ufsc_can_manage_licence_document( $licence_id, $licence->club_id ) ) { wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) ); }
		$role = $licence->role ?? ( $licence->fonction ?? 'pratiquant' );
		if ( ! ufsc_role_requires_honorability( $role ) ) { self::redirect_with_error( __( 'Ce rôle ne requiert pas d’attestation.', 'ufsc-clubs' ), $licence_id ); return; }
		$file = $_FILES['honorability_attestation'] ?? array();
		$max = (int) apply_filters( 'ufsc_honorability_document_max_bytes', 5 * MB_IN_BYTES );
		if ( empty( $file['name'] ) || ! empty( $file['error'] ) || (int) ( $file['size'] ?? 0 ) > $max ) { self::redirect_with_error( __( 'Fichier absent, invalide ou trop volumineux.', 'ufsc-clubs' ), $licence_id ); return; }
		$allowed = array( 'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
		if ( empty( $checked['type'] ) ) { self::redirect_with_error( __( 'Format de fichier non autorisé.', 'ufsc-clubs' ), $licence_id ); return; }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_handle_upload( 'honorability_attestation', 0, array( 'post_title' => sanitize_file_name( $file['name'] ) ), array( 'test_form' => false, 'mimes' => $allowed ) );
		if ( is_wp_error( $attachment_id ) ) { self::redirect_with_error( $attachment_id->get_error_message(), $licence_id ); return; }
		$season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence_id ) : UFSC_Season_Service::get_current_season();
		$result = ufsc_save_honorability_document( $licence_id, $licence->club_id, $season, $role, $attachment_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) { self::redirect_with_error( $result->get_error_message(), $licence_id ); return; }
		self::maybe_redirect( add_query_arg( 'honorability_updated', 1, wp_get_referer() ) );
	}

	public static function handle_decide_honorability_attestation() {
		if ( ! current_user_can( 'manage_options' ) && ! ufsc_user_can( UFSC_Permissions::CAP_LICENCES_MANAGE ) ) { wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) ); }
		$licence_id = absint( $_POST['licence_id'] ?? 0 ); check_admin_referer( 'ufsc_decide_honorability_' . $licence_id );
		$season = sanitize_text_field( wp_unslash( $_POST['season'] ?? '' ) );
		$result = ufsc_decide_honorability_document( $licence_id, $season, sanitize_key( $_POST['document_status'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ), get_current_user_id() );
		if ( is_wp_error( $result ) ) { self::redirect_with_error( $result->get_error_message(), $licence_id ); return; }
		self::maybe_redirect( add_query_arg( 'honorability_decided', 1, wp_get_referer() ) );
	}

    /**
     * // UFSC: Handle club save (profile/documents)
     */
    public static function handle_save_club() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        if ( isset( $_POST['ufsc_club_nonce'] ) ) {
            check_admin_referer( 'ufsc_save_club', 'ufsc_club_nonce' );
        } else {
            check_admin_referer( 'ufsc_save_club' );
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
            self::maybe_redirect( wp_get_referer() );
            return; // Abort if user doesn't manage this club
        }

        $club_id = $target_club_id;
        
        // Validate and sanitize data
        $data = self::validate_club_data( $_POST );
        if ( is_wp_error( $data ) ) {
            self::redirect_with_error( $data->get_error_message() );
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            // Club accounts may update only their public contact details. The
            // target club was resolved from the authenticated user above.
            $data = array_intersect_key( $data, array_flip( array( 'email', 'telephone' ) ) );
        }
        
        // Handle required document uploads
        $upload_result = UFSC_Uploads::handle_required_docs( $club_id );
        if ( is_wp_error( $upload_result ) ) {
            self::redirect_with_error( $upload_result->get_error_message() );
            return;
        }
        foreach ( $upload_result as $meta => $attach_id ) {
            if ( $club_id ) {
                update_post_meta( $club_id, $meta, $attach_id );
                update_post_meta( $club_id, $meta . '_status', 'pending' );
            }
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
        self::maybe_redirect( $redirect_url );
        return;
    }

    /**

     * Handle club affiliation form submission.
     *
     * Validates nonce and user capability, processes form data and required
     * documents, persists the club record and routes the user to WooCommerce
     * checkout with appropriate notices.
     */
    public static function handle_club_affiliation_submit() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_club_affiliation_submit' );

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

        // Handle required document uploads using secure handler
        $upload_results = UFSC_Uploads::handle_required_docs();
        if ( is_wp_error( $upload_results ) ) {
            self::redirect_with_error( $upload_results->get_error_message() );
            return;
        }

        $data = array_merge( $data, $upload_results );

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

        foreach ( $upload_results as $db_field => $attachment_id ) {
            update_post_meta( $club_id, $db_field, $attachment_id );
            update_post_meta( $club_id, $db_field . '_status', 'pending' );
        }

        // WooCommerce integration: add product to cart or create order
        $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url();

        $added = false;
        if ( function_exists( 'WC' ) ) {
            function_exists( 'wc_load_cart' ) && wc_load_cart();
			$affiliation_product_id = function_exists( 'ufsc_get_affiliation_product_id' ) ? ufsc_get_affiliation_product_id() : 0;
			$added = $affiliation_product_id > 0 ? WC()->cart->add_to_cart( $affiliation_product_id, 1, 0, array(), array( 'ufsc_club_id' => $club_id ) ) : false;
        }

        if ( $added ) {
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'Produit d\'affiliation ajouté au panier.', 'ufsc-clubs' ), 'success' );
            }
            self::maybe_redirect( $checkout_url );
            return;
        }

        if ( function_exists( 'wc_create_order' ) ) {
            $order = wc_create_order( array( 'status' => 'pending' ) );
            if ( ! is_wp_error( $order ) ) {
				$product = wc_get_product( $affiliation_product_id );
                if ( $product ) {
                    $order->add_product( $product, 1 );
                    $order->calculate_totals();
                    if ( function_exists( 'wc_add_notice' ) ) {
                        wc_add_notice( __( 'Commande d\'affiliation créée.', 'ufsc-clubs' ), 'success' );
                    }
                    self::maybe_redirect( $order->get_checkout_payment_url() );
                    return;
                }
            }
        }

        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'Impossible d\'ajouter le produit au panier.', 'ufsc-clubs' ), 'error' );
        }
        self::maybe_redirect( $checkout_url );
        return;
    }

    /**

     * Process licence add/update request
     */
    private static function process_licence_request( $licence_id ) {
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'ufsc_save_licence' ) ) {
            wp_die( __( 'Nonce verification failed', 'ufsc-clubs' ) );
        }

        if ( ! is_user_logged_in() ) {
            self::store_form_and_redirect( $_POST, array( __( 'Vous devez être connecté', 'ufsc-clubs' ) ), $licence_id );
        }

        $user_id = get_current_user_id();
        $club_id = ufsc_get_user_club_id( $user_id );
        $can_manage_all = self::can_manage_all_clubs();

        if ( $club_id <= 0 && $can_manage_all && $licence_id > 0 ) {
            $club_id = self::resolve_licence_club_id( $licence_id );
        }
        // global $wpdb;
        // $settings = UFSC_SQL::get_settings();
        // $table    = $settings['table_clubs'];
        // $pk       = $settings['pk_club'];

        // $club_data = $wpdb->get_row(
        //     $wpdb->prepare(
        //         "SELECT statut FROM `{$table}` WHERE `{$pk}` = %d",
        //         $club_id
        //     ),
        //     ARRAY_A
        // );

        
        // if ( $club_data && strtolower($club_data['statut']) === 'en_attente' ) {
        //     // Redirection directe vers checkout
        //     wp_safe_redirect( site_url('/checkout') );
        //     exit;
        // }

        if ( ! $club_id ) {
            self::store_form_and_redirect( $_POST, array( __( 'Aucun club associé à votre compte', 'ufsc-clubs' ) ), $licence_id );
        }

        if ( 0 === $licence_id ) {
            $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id ) : array( 'allowed' => false, 'message' => __( 'Votre club doit renouveler et faire activer son affiliation avant de souscrire ou renouveler des licences.', 'ufsc-clubs' ) );
            if ( empty( $gate['allowed'] ) ) {
                if ( function_exists( 'ufsc_log_licence_affiliation_refusal' ) ) { ufsc_log_licence_affiliation_refusal( $gate, 'save_licence' ); }
                self::store_form_and_redirect( $_POST, array( $gate['message'] ), $licence_id );
            }
        }

        if ( $licence_id > 0 ) {
            $licence = self::get_licence_row( $licence_id, $club_id );
            if ( ! $licence ) {
                self::store_form_and_redirect( $_POST, array( __( 'Licence non trouvée', 'ufsc-clubs' ) ), $licence_id );
            }

            if ( function_exists( 'ufsc_is_licence_locked_for_club' ) && ufsc_is_licence_locked_for_club( $licence ) ) {
                self::store_form_and_redirect( $_POST, array( __( 'Modification non autorisée', 'ufsc-clubs' ) ), $licence_id );
            }
        }

        $data = self::process_licence_data( $_POST );
        if ( is_wp_error( $data ) ) {
            $details = $data->get_error_data();
            $errors = is_array( $details ) && ! empty( $details['errors'] ) ? $details['errors'] : array( $data->get_error_message() );
            self::store_form_and_redirect( $_POST, $errors, $licence_id );
        }

        $result = self::save_licence_data( $licence_id, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::store_form_and_redirect( $_POST, array( $result->get_error_message() ), $licence_id );
        }

        $new_id = $result;
        $submit_action = isset( $_POST['ufsc_submit_action'] ) ? self::normalize_licence_intent( wp_unslash( $_POST['ufsc_submit_action'] ) ) : 'continue';
        if ( 'save_draft' === $submit_action ) {
            self::update_licence_status_db( $new_id, 'brouillon' );
            $step = isset( $_POST['ufsc_wizard_step'] ) ? min( 6, max( 1, absint( $_POST['ufsc_wizard_step'] ) ) ) : 1;
            $redirect_url = add_query_arg( array( 'edit_licence' => $new_id, 'draft_saved' => 1, 'ufsc_wizard_step' => $step ), wp_get_referer() );
            self::maybe_redirect( esc_url_raw( $redirect_url ) );
            return;
        }
		self::save_licence_compliance_audit( $new_id, $_POST );

        $wc_settings = ufsc_get_woocommerce_settings();
        $wants_cart = self::should_add_licence_to_cart( $submit_action );

        $season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $allocation = array( 'included' => false, 'bucket' => 'payante' );
        if ( $wants_cart && ( ! function_exists( 'ufsc_quotas_enabled' ) || ufsc_quotas_enabled() ) && ! empty( $wc_settings['auto_consume_included'] ) && function_exists( 'ufsc_allocate_pack_credit' ) ) {
            $allocation = ufsc_allocate_pack_credit( $new_id, $club_id, $season, $data['role'] ?? '' );
            if ( is_wp_error( $allocation ) ) {
                self::store_form_and_redirect( $_POST, array( $allocation->get_error_message() ), $new_id );
            }
        }

        if ( ! empty( $allocation['included'] ) ) {
            self::update_licence_status_db( $new_id, 'en_attente' );
            $message = __( 'Cette licence est comprise dans votre pack d’affiliation. Aucun paiement supplémentaire n’est nécessaire.', 'ufsc-clubs' );
            if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( $message, 'success' ); }
            $redirect_url = add_query_arg( array( 'licence_included' => 1, 'licence_id' => $new_id, 'pack_bucket' => $allocation['bucket'] ), wp_get_referer() );
            self::maybe_redirect( esc_url_raw( $redirect_url ) );
            return;
        }

        // Saving without checkout never creates a payable line.
        if ( ! $wants_cart ) {
            $redirect_url = add_query_arg( array( 'licence_saved' => 1, 'licence_id' => $new_id ), wp_get_referer() );
            self::maybe_redirect( esc_url_raw( $redirect_url ) );
            return;
        }

        $product_resolution = function_exists( 'ufsc_get_licence_product_resolution' ) ? ufsc_get_licence_product_resolution() : array();
        $product_id = absint( $product_resolution['configured_id'] ?? ( $wc_settings['product_license_id'] ?? 0 ) );
        if ( empty( $product_resolution['valid'] ) ) {
            $message = function_exists( 'ufsc_get_licence_product_message' ) ? ufsc_get_licence_product_message( $product_resolution ) : __( 'Le produit Licence UFSC est introuvable ou non achetable.', 'ufsc-clubs' );
            self::store_form_and_redirect( $_POST, array( $message ), $new_id );
        }
        $cart_ready = function_exists( 'ufsc_ensure_woocommerce_cart' ) ? ufsc_ensure_woocommerce_cart() : new WP_Error( 'ufsc_woocommerce_unavailable', __( 'WooCommerce n’est pas initialisé.', 'ufsc-clubs' ) );
        if ( is_wp_error( $cart_ready ) ) { self::store_form_and_redirect( $_POST, array( $cart_ready->get_error_message() ), $new_id ); }
        $cart_item_data = array(
            'ufsc_licence_id' => $new_id, 'ufsc_club_id' => $club_id,
            'ufsc_nom' => $data['nom'] ?? '', 'ufsc_prenom' => $data['prenom'] ?? '',
            'ufsc_date_naissance' => $data['date_naissance'] ?? '', 'ufsc_role' => $data['role'] ?? '',
            'ufsc_season' => $season, 'ufsc_target_season' => $season,
            'ufsc_operation_type' => $licence_id > 0 ? 'licence_update' : 'new_licence',
            'ufsc_request_type' => $licence_id > 0 ? 'update' : 'new', 'quantity' => 1,
        );
        $add_result = ufsc_add_licence_ids_to_cart_idempotent( $product_id, $club_id, array( $new_id ), $cart_item_data );
        if ( is_wp_error( $add_result ) ) { self::store_form_and_redirect( $_POST, array( $add_result->get_error_message() ), $new_id ); }
        self::update_licence_status_db( $new_id, 'en_attente' );
        if ( function_exists( 'wc_add_notice' ) ) { wc_add_notice( __( 'Les licences incluses dans votre pack sont utilisées. Cette licence a été ajoutée au panier au tarif en vigueur.', 'ufsc-clubs' ), 'success' ); }
        if ( function_exists( 'wc_get_cart_url' ) ) { self::maybe_redirect( wc_get_cart_url() ); return; }

        $redirect_url = esc_url_raw( add_query_arg(
            array(
                'updated'    => 1,
                'licence_id' => $new_id,
            ),
            wp_get_referer()
        ) );
        self::maybe_redirect( $redirect_url );
        return;
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
        foreach ( (array) $errors as $error ) {
            if ( is_array( $error ) && ! empty( $error['step'] ) ) {
                $redirect = add_query_arg( 'ufsc_wizard_step', min( 6, max( 1, absint( $error['step'] ) ) ), $redirect );
                break;
            }
        }
        self::maybe_redirect( $redirect );
        return;
    }

    /**
     * Update licence status directly in database
     */
    private static function update_licence_status_db( $licence_id, $status ) {
        global $wpdb;
        $settings       = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        if ( class_exists( 'UFSC_Licence_Status' ) ) {
            UFSC_Licence_Status::update_status_columns( $licences_table, array( 'id' => $licence_id ), $status, array( '%d' ) );
        } else {
            $wpdb->update( $licences_table, array( 'statut' => $status ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
        }

        $club_id = $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$licences_table} WHERE id = %d", $licence_id ) );
        if ( $club_id ) {
            do_action( 'ufsc_licence_updated', (int) $club_id );
        }
    }


    /**
     * // UFSC: Handle CSV export
     */
    public static function handle_export_stats() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_frontend_nonce', 'nonce' );

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
        
        $intent = isset( $post_data['ufsc_submit_action'] ) ? self::normalize_licence_intent( $post_data['ufsc_submit_action'] ) : 'continue';
        $is_draft = 'save_draft' === $intent;
        // Drafts need only an identity; all checkout fields remain mandatory for finalisation.
        $required_fields = $is_draft ? array( 'prenom', 'nom' ) : array( 'prenom', 'nom', 'sexe', 'date_naissance', 'email', 'adresse', 'ville', 'code_postal', 'pays', 'telephone', 'role' );
        $field_labels = array( 'prenom' => __( 'Prénom', 'ufsc-clubs' ), 'nom' => __( 'Nom', 'ufsc-clubs' ), 'sexe' => __( 'Sexe', 'ufsc-clubs' ), 'date_naissance' => __( 'Date de naissance', 'ufsc-clubs' ), 'email' => __( 'Email', 'ufsc-clubs' ), 'adresse' => __( 'Adresse postale', 'ufsc-clubs' ), 'ville' => __( 'Ville', 'ufsc-clubs' ), 'code_postal' => __( 'Code postal', 'ufsc-clubs' ), 'pays' => __( 'Pays', 'ufsc-clubs' ), 'telephone' => __( 'Téléphone', 'ufsc-clubs' ), 'role' => __( 'Rôle dans le club', 'ufsc-clubs' ) );
        $field_steps = array( 'prenom' => 1, 'nom' => 1, 'sexe' => 1, 'date_naissance' => 1, 'email' => 1, 'telephone' => 1, 'adresse' => 2, 'ville' => 2, 'code_postal' => 2, 'pays' => 2, 'role' => 3 );
        $structured_errors = array();
        foreach ( $required_fields as $field ) {
            if ( empty( $post_data[$field] ) ) {
                $message = sprintf( __( 'Le champ « %s » est obligatoire.', 'ufsc-clubs' ), $field_labels[ $field ] ?? $field );
                $errors[] = $message;
                $structured_errors[] = array( 'field' => $field, 'label' => $field_labels[ $field ] ?? $field, 'step' => $field_steps[ $field ] ?? 1, 'message' => $message );
            } else {
                $data[$field] = sanitize_text_field( $post_data[$field] );
            }
        }
        
        // Email validation (required)
        $email = isset( $post_data['email'] ) ? sanitize_email( wp_unslash( (string) $post_data['email'] ) ) : '';
        if ( ( ! $is_draft || '' !== $email ) && ! is_email( $email ) ) {
            $message = __( 'L’adresse email est invalide.', 'ufsc-clubs' );
            $errors[] = $message;
            $structured_errors[] = array( 'field' => 'email', 'label' => $field_labels['email'], 'step' => 1, 'message' => $message );
        } else {
            $data['email'] = $email;
        }

        $date_naissance = isset( $post_data['date_naissance'] ) ? sanitize_text_field( wp_unslash( (string) $post_data['date_naissance'] ) ) : '';
        if ( ( ! $is_draft || '' !== $date_naissance ) && ! self::is_valid_birth_date( $date_naissance ) ) {
            $message = __( 'La date de naissance est invalide.', 'ufsc-clubs' );
            $errors[] = $message;
            $structured_errors[] = array( 'field' => 'date_naissance', 'label' => $field_labels['date_naissance'], 'step' => 1, 'message' => $message );
        } else {
            $data['date_naissance'] = $date_naissance;
        }

		$strict_checkout = isset( $post_data['ufsc_submit_action'] ) && 'add_to_cart' === sanitize_key( (string) $post_data['ufsc_submit_action'] );
		$role = function_exists( 'ufsc_normalize_club_role' ) ? ufsc_normalize_club_role( $post_data['role'] ?? '' ) : sanitize_key( (string) ( $post_data['role'] ?? '' ) );
        if ( ! $is_draft && '' === $role && ! empty( $post_data['role'] ) ) { $errors[] = __( 'Le rôle dans le club est invalide.', 'ufsc-clubs' ); }
		$is_minor = false;
		if ( self::is_valid_birth_date( $date_naissance ) ) {
			$birth = new DateTimeImmutable( $date_naissance );
			$reference = new DateTimeImmutable( current_time( 'Y-m-d' ) );
			$is_minor = $birth->diff( $reference )->y < 18;
		}
		if ( $strict_checkout && empty( $post_data['health_questionnaire_confirmed'] ) ) {
			$message = $is_minor ? __( 'La lecture du questionnaire de santé mineur doit être confirmée.', 'ufsc-clubs' ) : __( 'La lecture du questionnaire de santé majeur doit être confirmée.', 'ufsc-clubs' );
			$errors[] = $message;
			$structured_errors[] = array( 'field' => $is_minor ? 'ufsc-health-confirm-minor' : 'ufsc-health-confirm-adult', 'label' => __( 'Questionnaire de santé', 'ufsc-clubs' ), 'step' => 5, 'message' => $message );
		}
		if ( $strict_checkout && $is_minor && empty( trim( (string) ( $post_data['legal_representative_name'] ?? '' ) ) ) ) {
			$message = __( 'L’identité du représentant légal est obligatoire pour un mineur.', 'ufsc-clubs' );
			$errors[] = $message;
			$structured_errors[] = array( 'field' => 'legal_representative_name', 'label' => __( 'Représentant légal', 'ufsc-clubs' ), 'step' => 5, 'message' => $message );
		}
		if ( $strict_checkout && function_exists( 'ufsc_role_requires_honorability' ) && ufsc_role_requires_honorability( $role ) && empty( $post_data['honorability_confirmed'] ) ) {
			$message = __( 'La confirmation « Je confirme avoir transmis ou complété l’attestation d’honorabilité requise pour cette fonction » est obligatoire avant la finalisation de ce dossier.', 'ufsc-clubs' );
			$errors[] = $message;
			$structured_errors[] = array( 'field' => 'ufsc-honorability-confirmed', 'label' => __( 'Attestation d’honorabilité', 'ufsc-clubs' ), 'step' => 5, 'message' => $message );
		}
        
        // Optional fields with sanitization
        $optional_fields = array(
            'telephone' => 'sanitize_text_field',
            'adresse' => 'sanitize_textarea_field',
            'ville' => 'sanitize_text_field',
            'code_postal' => 'sanitize_text_field',
            'pays' => 'sanitize_text_field',
            'sexe' => 'sanitize_text_field',
            'poids' => 'sanitize_text_field',
            'fighter_level' => 'sanitize_key',
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
		if ( '' !== $role ) { $data['role'] = $role; }

		if ( ! $is_draft && empty( $data['fighter_level'] ) && function_exists( 'ufsc_get_default_fighter_level' ) ) {
			$data['fighter_level'] = ufsc_get_default_fighter_level( $date_naissance );
		}
		if ( function_exists( 'ufsc_validate_fighter_level' ) && ( ! $is_draft || ! empty( $data['fighter_level'] ) ) ) {
			$level_validation = ufsc_validate_fighter_level( $data['fighter_level'] ?? '', $date_naissance, $is_draft );
			if ( is_wp_error( $level_validation ) ) {
				$errors[] = $level_validation->get_error_message();
				$structured_errors[] = array( 'field' => 'fighter_level', 'label' => __( 'Niveau du boxeur', 'ufsc-clubs' ), 'step' => 2, 'message' => $level_validation->get_error_message() );
			}
		}

        if ( isset( $data['note'] ) && '' !== $data['note'] ) {
            $data['note'] = trim( (string) preg_replace( '/^\s*club\s*:\s*/i', '', $data['note'] ) );
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
        $data['has_license_number'] = empty( $post_data['has_license_number'] ) ? 0 : 1;
        if ( ! $data['has_license_number'] ) {
            $data['numero_licence'] = '';
        } else {
            $raw_previous_number = isset( $post_data['numero_licence'] ) ? sanitize_text_field( wp_unslash( (string) $post_data['numero_licence'] ) ) : '';
            $previous_number = self::normalize_previous_licence_number( true, $raw_previous_number );
            if ( false === $previous_number ) {
                $errors[] = __( 'Le numéro de licence antérieur est obligatoire et doit contenir 1 à 10 lettres ou chiffres, sans espace.', 'ufsc-clubs' );
            } else {
                $data['numero_licence'] = $previous_number;
            }
        }
        
        if ( array_key_exists( 'poids', $post_data ) && class_exists( 'UFSC_Category_Repository' ) ) {
            $raw_weight = sanitize_text_field( wp_unslash( (string) $post_data['poids'] ) );
            $normalized_weight = UFSC_Category_Repository::normalize_weight( $raw_weight );
            if ( null === $normalized_weight && '' !== trim( $raw_weight ) ) {
                $errors[] = __( 'Poids invalide', 'ufsc-clubs' );
            } else {
                $data['poids'] = $normalized_weight;
            }
        }

        if ( ! empty( $errors ) ) {
            $structured_messages = array_map( static function ( $item ) { return is_array( $item ) ? (string) ( $item['message'] ?? '' ) : (string) $item; }, $structured_errors );
            foreach ( $errors as $message ) { if ( ! in_array( $message, $structured_messages, true ) ) { $structured_errors[] = $message; } }
            return new WP_Error( 'validation_failed', implode( ' ', $errors ), array( 'errors' => $structured_errors ?: $errors ) );
        }
        
        if ( ! empty( $post_data['telephone'] ) ) {
            $data['tel_mobile'] = sanitize_text_field( $post_data['telephone'] );
        }

        $data = self::normalize_licence_date_fields( $data );

        return $data;
    }

	/** Persist compliance acknowledgements only; never medical answers. */
	private static function save_licence_compliance_audit( $licence_id, $post_data ) {
		$licence_id = absint( $licence_id );
		if ( $licence_id <= 0 ) {
			return;
		}
		$season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
		$birth_date = sanitize_text_field( (string) ( $post_data['date_naissance'] ?? '' ) );
		$is_minor = self::is_valid_birth_date( $birth_date ) && ( new DateTimeImmutable( $birth_date ) )->diff( new DateTimeImmutable( current_time( 'Y-m-d' ) ) )->y < 18;
		$audit = array(
			'season'                    => $season,
			'user_id'                   => get_current_user_id(),
			'confirmed_at'               => current_time( 'mysql' ),
			'health_confirmed'           => empty( $post_data['health_questionnaire_confirmed'] ) ? 0 : 1,
			'health_type'                => $is_minor ? 'minor' : 'adult',
			'health_document_url'        => $is_minor ? 'https://ufsc-france.fr/wp-content/uploads/2026/08/2021-06-02-5-ANNEXE-4-QUESTIONNAIRE-SANTE-MINEUR.pdf' : 'https://ufsc-france.fr/wp-content/uploads/2026/08/2024-08-28-QUESTIONNAIRE-SANTE-MAJEUR.pdf',
			'legal_representative_name' => $is_minor ? sanitize_text_field( (string) ( $post_data['legal_representative_name'] ?? '' ) ) : '',
			'honorability_confirmed'     => empty( $post_data['honorability_confirmed'] ) ? 0 : 1,
			'honorability_role'          => sanitize_key( (string) ( $post_data['role'] ?? '' ) ),
			'honorability_document_url'  => 'https://ufsc-france.fr/wp-content/uploads/2026/08/2021-06-02-2-ANNEXE-1-NOTE-SUR-LE-CONTROLE-DE-LHONORABILITE.pdf',
		);
		if ( function_exists( 'ufsc_set_option_noautoload' ) ) {
			ufsc_set_option_noautoload( 'ufsc_licence_compliance_' . $licence_id . '_' . sanitize_key( $season ), $audit );
		}
		if ( array_key_exists( 'honorability_confirmed', $post_data ) && function_exists( 'ufsc_get_licences_table' ) ) {
			global $wpdb;
			$table = ufsc_get_licences_table();
			$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
			$confirmation = empty( $post_data['honorability_confirmed'] ) ? 0 : 1;
			$update = array(); $formats = array();
			if ( in_array( 'honorability_confirmed', $columns, true ) ) { $update['honorability_confirmed'] = $confirmation; $formats[] = '%d'; }
			if ( $confirmation && in_array( 'honorability_confirmed_at', $columns, true ) ) { $update['honorability_confirmed_at'] = current_time( 'mysql' ); $formats[] = '%s'; }
			if ( $confirmation && in_array( 'honorability_confirmed_by', $columns, true ) ) { $update['honorability_confirmed_by'] = get_current_user_id(); $formats[] = '%d'; }
			if ( $update ) { $wpdb->update( $table, $update, array( 'id' => $licence_id ), $formats, array( '%d' ) ); }
		}
	}

    /**
     * Validate licence birth date (strict YYYY-MM-DD + valid calendar day).
     */
    private static function is_valid_birth_date( $date ) {
        if ( '' === $date || '0000-00-00' === $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return false;
        }

        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        return $date_obj instanceof DateTime && $date_obj->format( 'Y-m-d' ) === $date;
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
        if ( isset( $data['telephone'] ) ) {
            $data['telephone'] = preg_replace( '/[^0-9+(). -]/', '', $data['telephone'] );
            $digits = preg_replace( '/\D+/', '', $data['telephone'] );
            if ( '' !== $data['telephone'] && ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) ) {
                $errors[] = __( 'Numéro de téléphone invalide', 'ufsc-clubs' );
            }
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

        $columns = array();
        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $licences_table );
        }
        if ( ! empty( $columns ) ) {
            $data = array_intersect_key( $data, array_flip( $columns ) );
        }
        $data['club_id'] = $club_id;

        $column_exists = function( $column ) use ( $columns, $licences_table ) {
            if ( ! empty( $columns ) ) {
                return in_array( $column, $columns, true );
            }
            if ( function_exists( 'ufsc_table_has_column' ) ) {
                return ufsc_table_has_column( $licences_table, $column );
            }
            return true;
        };

        $data = self::add_detected_category_fields( $data, $column_exists );
        $current_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $record_season = $licence_id > 0 && function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence_id ) : $current_season;
        $office_roles = array( 'president', 'secretaire', 'tresorier' );
        if ( in_array( $data['role'] ?? '', $office_roles, true ) ) {
            $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';
            if ( ! $season_column || ! preg_match( '/^\d{4}-\d{4}$/', (string) $record_season ) ) {
                return new WP_Error( 'bureau_season_unavailable', __( 'La saison du dirigeant ne peut pas être vérifiée.', 'ufsc-clubs' ) );
            }
            $season_sql = 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $record_season, $role_season_matches )
                ? $wpdb->prepare( 'season_end_year = %d', (int) $role_season_matches[1] )
                : $wpdb->prepare( "REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', $record_season ) );
            $existing_role_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$licences_table}` WHERE club_id = %d AND role = %s AND id <> %d AND {$season_sql} LIMIT 1", $club_id, $data['role'], absint( $licence_id ) ) );
            if ( $existing_role_id > 0 ) {
                return new WP_Error( 'bureau_role_duplicate', __( 'Cette fonction est déjà attribuée à une licence de ce club pour cette saison. Consultez la fiche existante avant de la modifier.', 'ufsc-clubs' ) );
            }
        }

        if ( $licence_id > 0 ) {
            $data = self::enforce_server_managed_licence_fields( $data, $column_exists );
            // Update
            if ( $column_exists( 'date_modification' ) ) {
                $data['date_modification'] = current_time( 'mysql' );
            }
            $result = $wpdb->update( $licences_table, $data, array( 'id' => $licence_id ) );
            if ( $result === false ) {
                return new WP_Error( 'update_failed', __( 'Erreur lors de la mise à jour', 'ufsc-clubs' ) );
            }
            // Never infer or rewrite the season of an existing historical row.
            self::sync_current_officers_to_club_legacy( $club_id );
            do_action( 'ufsc_licence_updated', (int) $club_id );
            return $licence_id;
        } else {
            $data = self::enforce_server_managed_licence_fields( $data, $column_exists );
			$season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';
			if ( $season_column && ! empty( $data['nom'] ) && ! empty( $data['prenom'] ) && ! empty( $data['date_naissance'] ) ) {
				$season_sql = 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $current_season, $season_matches )
					? $wpdb->prepare( 'season_end_year = %d', (int) $season_matches[1] )
					: $wpdb->prepare( "REPLACE(`{$season_column}`, '/', '-') = %s", $current_season );
				$candidate_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, nom, prenom FROM `{$licences_table}` WHERE club_id = %d AND date_naissance = %s AND {$season_sql}",
						$club_id,
						$data['date_naissance']
					)
				);
				$normalize_identity = static function ( $value ) {
					$value = function_exists( 'remove_accents' ) ? remove_accents( (string) $value ) : (string) $value;
					return strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $value ) ) );
				};
				foreach ( (array) $candidate_rows as $candidate ) {
					if ( $normalize_identity( $candidate->nom ?? '' ) === $normalize_identity( $data['nom'] ) && $normalize_identity( $candidate->prenom ?? '' ) === $normalize_identity( $data['prenom'] ) ) {
						return new WP_Error( 'duplicate_licence', __( 'Une licence existe déjà pour cette personne, ce club et cette saison.', 'ufsc-clubs' ) );
					}
				}
			}
            // Create
            if ( $column_exists( 'date_creation' ) ) {
                $data['date_creation'] = current_time( 'mysql' );
            }
            $data['statut'] = 'brouillon';
            $result = $wpdb->insert( $licences_table, $data );
            if ( $result === false ) {
                return new WP_Error( 'insert_failed', __( 'Erreur lors de la création', 'ufsc-clubs' ) );
            }
            $new_id = (int) $wpdb->insert_id;
            if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
                $stored_season = ufsc_get_licence_season( $new_id );
                if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
					ufsc_set_licence_season( $new_id, $current_season );
                }
            }
            do_action( 'ufsc_licence_created', $new_id, (int) $club_id );
            self::sync_current_officers_to_club_legacy( $club_id );
            do_action( 'ufsc_licence_updated', (int) $club_id );
            return $new_id;
        }
    }

    /**
     * Keep historical club officer columns as a read-only compatibility
     * projection of the canonical current-season licence rows.
     */
    private static function sync_current_officers_to_club_legacy( $club_id ) {
        global $wpdb;
        $club_id = absint( $club_id );
        if ( $club_id <= 0 ) { return; }
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        $clubs_table = $settings['table_clubs'];
        $season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );
        $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $licences_table ) : '';
        if ( ! $season_column || ! preg_match( '/^\d{4}-\d{4}$/', (string) $season ) ) { return; }
        $season_sql = 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $matches )
            ? $wpdb->prepare( 'season_end_year = %d', (int) $matches[1] )
            : $wpdb->prepare( "REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', $season ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$licences_table}` WHERE club_id = %d AND role IN ('president','secretaire','tresorier') AND {$season_sql} ORDER BY id DESC", $club_id ) );
        $projection = array();
        foreach ( array( 'president', 'secretaire', 'tresorier' ) as $role ) {
            foreach ( array( 'prenom', 'nom', 'tel', 'email', 'date_naissance', 'adresse', 'poste' ) as $field ) { $projection[ $role . '_' . $field ] = ''; }
        }
        $seen = array();
        foreach ( (array) $rows as $row ) {
            $role = sanitize_key( (string) ( $row->role ?? '' ) );
            if ( isset( $seen[ $role ] ) || ! in_array( $role, array( 'president', 'secretaire', 'tresorier' ), true ) ) { continue; }
            $seen[ $role ] = true;
            $projection[ $role . '_prenom' ] = sanitize_text_field( (string) ( $row->prenom ?? '' ) );
            $projection[ $role . '_nom' ] = sanitize_text_field( (string) ( $row->nom ?? '' ) );
            $projection[ $role . '_tel' ] = sanitize_text_field( (string) ( $row->telephone ?? ( $row->tel_mobile ?? '' ) ) );
            $projection[ $role . '_email' ] = sanitize_email( (string) ( $row->email ?? '' ) );
            $projection[ $role . '_date_naissance' ] = sanitize_text_field( (string) ( $row->date_naissance ?? '' ) );
            $projection[ $role . '_adresse' ] = sanitize_textarea_field( (string) ( $row->adresse ?? '' ) );
            $projection[ $role . '_poste' ] = $role;
        }
        $club_columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $clubs_table ) : array_keys( $projection );
        $projection = array_intersect_key( $projection, array_flip( $club_columns ) );
        if ( $projection ) { $wpdb->update( $clubs_table, $projection, array( 'id' => $club_id ) ); }
    }

    /**
     * Save club data to database
     */
    private static function save_club_data( $club_id, $data ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $clubs_table );
            if ( ! empty( $columns ) ) {
                $data = array_intersect_key( $data, array_flip( $columns ) );
            }
        }
        
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
            'Rôle', 'Statut', 'Saison', 'Compétition', 'Date Création'
        );
        fputcsv( $output, $headers, ';' );
        
        // Data rows
        foreach ( $results as $row ) {
            $row['season_label'] = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $row ) : ( function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $row ) : '' );
            $row['competition'] = $row['competition'] ? 'Oui' : 'Non';
            $ordered = array(
                $row['prenom'] ?? '',
                $row['nom'] ?? '',
                $row['email'] ?? '',
                $row['telephone'] ?? '',
                $row['sexe'] ?? '',
                $row['date_naissance'] ?? '',
                $row['role'] ?? '',
                $row['statut'] ?? '',
                $row['season_label'] ?? '',
                $row['competition'] ?? '',
                $row['date_creation'] ?? '',
            );
            fputcsv( $output, $ordered, ';' );
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
        $row = self::get_licence_row( $licence_id, $club_id );
        return $row ? (string) ( $row->statut ?? '' ) : '';
    }

    private static function get_licence_row( $licence_id, $club_id ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$licences_table} WHERE id = %d AND club_id = %d",
            $licence_id, $club_id
        ) );
    }

    private static function get_licence_delete_block_reason( $licence ) {
        if ( ! is_object( $licence ) && ! is_array( $licence ) ) {
            return 'Suppression non autorisée';
        }

        $status_raw  = is_array( $licence ) ? ( $licence['statut'] ?? ( $licence['status'] ?? '' ) ) : ( $licence->statut ?? ( $licence->status ?? '' ) );
        $status_norm = function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : strtolower( trim( (string) $status_raw ) );
        if ( 'valide' === $status_norm ) {
            return 'Licence validée — suppression impossible.';
        }

        if ( function_exists( 'ufsc_is_licence_paid' ) && ufsc_is_licence_paid( $licence ) ) {
            return 'Licence liée à une commande — suppression impossible.';
        }

        if ( function_exists( 'ufsc_is_licence_locked_for_club' ) && ufsc_is_licence_locked_for_club( $licence ) ) {
            return 'Suppression non autorisée';
        }

        return '';
    }

    private static function normalize_licence_date_fields( $data ) {
        foreach ( $data as $key => $value ) {
            if ( strpos( (string) $key, 'date_' ) !== 0 && ! in_array( $key, array( 'date_naissance', 'date_certificat_medical' ), true ) ) {
                continue;
            }

            $val = trim( (string) $value );
            if ( '0000-00-00' === $val || '0000-00-00 00:00:00' === $val ) {
                $data[ $key ] = '';
            }
        }

        return $data;
    }

    /**
     * Find an already-created renewal for the same club/person/season.
     *
     * This is a read-only safety net in addition to renewal markers: it prevents
     * duplicates when an older marker is missing but a target-season licence
     * already exists.
     *
     * @param object $licence Source licence.
     * @param int    $club_id Club ID.
     * @param string $target_season Target season label.
     * @param array  $columns Existing licence table columns.
     * @param string $licences_table Licence table name.
     * @return int Existing renewed licence ID, or 0.
     */
    private static function find_equivalent_renewed_licence_id( $licence, $club_id, $target_season, $columns, $licences_table ) {
        global $wpdb;

        if ( empty( $columns ) || ! in_array( 'club_id', $columns, true ) || ! in_array( 'id', $columns, true ) ) {
            return 0;
        }

        $clauses = array( 'club_id = %d' );
        $values  = array( absint( $club_id ) );

        foreach ( array( 'nom', 'prenom', 'date_naissance' ) as $field ) {
            $value = isset( $licence->{$field} ) ? trim( (string) $licence->{$field} ) : '';
            if ( '' !== $value && in_array( $field, $columns, true ) ) {
                $clauses[] = "{$field} = %s";
                $values[]  = $value;
            }
        }

        if ( count( $clauses ) < 4 ) {
            return 0;
        }

        $season_column = '';
        foreach ( array( 'paid_season', 'season', 'saison', 'season_end_year' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                $season_column = $candidate;
                break;
            }
        }

        if ( '' === $season_column ) {
            return 0;
        }

        if ( 'season_end_year' === $season_column ) {
            $target_end_year = function_exists( 'ufsc_get_season_end_year_from_label' ) ? ufsc_get_season_end_year_from_label( $target_season ) : 0;
            if ( $target_end_year <= 0 ) {
                return 0;
            }
            $clauses[] = 'season_end_year = %d';
            $values[]  = $target_end_year;
        } else {
            $clauses[] = "{$season_column} = %s";
            $values[]  = $target_season;
        }

        $source_id = absint( $licence->id ?? 0 );
        if ( $source_id > 0 ) {
            $clauses[] = 'id <> %d';
            $values[]  = $source_id;
        }

        $sql = "SELECT id FROM `{$licences_table}` WHERE " . implode( ' AND ', $clauses ) . ' LIMIT 1';
        return absint( $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) );
    }

    private static function add_detected_category_fields( $data, $column_exists ) {
        if ( ! class_exists( 'UFSC_Category_Repository' ) ) {
            return $data;
        }

        $birthdate = $data['date_naissance'] ?? '';
        $gender    = $data['sexe'] ?? '';
        $weight    = $data['poids'] ?? '';
        $season    = function_exists( 'ufsc_get_current_season_label' ) ? ufsc_get_current_season_label() : UFSC_Category_Repository::DEFAULT_SEASON;
        $summary   = UFSC_Category_Repository::detect_for_athlete(
            array(
                'date_naissance' => $birthdate,
                'sexe'           => $gender,
                'poids'          => $weight,
            ),
            UFSC_Category_Repository::DEFAULT_DISCIPLINE,
            $season
        );

        if ( $column_exists( 'categorie_age_detectee' ) ) {
            $data['categorie_age_detectee'] = $summary['age_category_label'];
        }
        if ( $column_exists( 'categorie_poids_detectee' ) ) {
            $data['categorie_poids_detectee'] = $summary['weight_category_label'];
        }
        if ( $column_exists( 'categorie_updated_at' ) ) {
            $data['categorie_updated_at'] = current_time( 'mysql' );
        }

        return $data;
    }

    private static function enforce_server_managed_licence_fields( $data, $column_exists ) {
        $season = function_exists( 'ufsc_get_current_season_label' ) ? ufsc_get_current_season_label() : '';
        if ( $season ) {
            foreach ( array( 'season', 'saison', 'paid_season' ) as $season_col ) {
                if ( $column_exists( $season_col ) ) {
                    $data[ $season_col ] = $season;
                }
            }
        }

        if ( isset( $data['date_certificat_medical'] ) && '' === trim( (string) $data['date_certificat_medical'] ) ) {
            $data['date_certificat_medical'] = '';
        }

        return self::normalize_licence_date_fields( $data );
    }

    private static function user_can_manage_club( $user_id, $club_id ) {
        // Simple check - could be enhanced with more complex permissions
        return ufsc_get_user_club_id( $user_id ) === $club_id;
    }

    private static function can_manage_all_clubs() {
        if ( class_exists( 'UFSC_Capabilities' ) && method_exists( 'UFSC_Capabilities', 'user_can' ) ) {
            if ( UFSC_Capabilities::user_can( UFSC_Capabilities::CAP_MANAGE_READ ) ) {
                return true;
            }
        }

        return current_user_can( 'manage_options' );
    }

    private static function resolve_licence_club_id( $licence_id ) {
        global $wpdb;

        $licence_id = absint( $licence_id );
        if ( ! $licence_id ) {
            return 0;
        }

        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_licences'];

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$table} WHERE id = %d", $licence_id ) );
    }

    private static function get_bureau_role_label( $role ) {
        $labels = array(
            'president'  => __( 'Président', 'ufsc-clubs' ),
            'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier'  => __( 'Trésorier', 'ufsc-clubs' ),
            'adherent'   => __( 'Adhérent', 'ufsc-clubs' ),
        );

        return isset( $labels[ $role ] ) ? $labels[ $role ] : __( 'Aucun', 'ufsc-clubs' );
    }

    private static function maybe_redirect( $url ) {
        if ( function_exists( 'ufsc_is_wp_cli' ) && ufsc_is_wp_cli() ) {
            return;
        }

        wp_safe_redirect( $url );
        exit;
    }

    private static function redirect_with_error( $message, $licence_id = null ) {
        $redirect_url = wp_get_referer() ?: home_url();
        $redirect_url = remove_query_arg( 'ufsc_error', $redirect_url );

        $args = array( 'ufsc_error' => rawurlencode( $message ) );
        if ( $licence_id ) {
            $args['licence_id'] = $licence_id;
        }

        self::maybe_redirect( add_query_arg( $args, $redirect_url ) );
        return;
    }

    private static function redirect_with_success( $message, $redirect_url = '' ) {
        $redirect_url = $redirect_url ?: ( wp_get_referer() ?: home_url() );
        $redirect_url = remove_query_arg( array( 'ufsc_error', 'ufsc_message', 'deleted', 'view_licence', 'edit_licence', 'licence_id', 'licence', 'id', 'licenceId', 'license_id' ), $redirect_url );
        self::maybe_redirect( add_query_arg( 'ufsc_message', rawurlencode( $message ), $redirect_url ) );
        return;
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
