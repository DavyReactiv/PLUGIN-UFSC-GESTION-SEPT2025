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
        add_action( 'admin_post_ufsc_cancel_licence', array( __CLASS__, 'handle_cancel_licence' ) );
        add_action( 'admin_post_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );
        add_action( 'admin_post_nopriv_ufsc_update_licence_status', array( __CLASS__, 'handle_update_licence_status' ) );
        add_action( 'admin_post_ufsc_sync_licence_statuses', array( __CLASS__, 'handle_sync_licence_statuses' ) );

        // UFSC PATCH: Licence document handlers
        add_action( 'admin_post_ufsc_upload_licence_document', array( __CLASS__, 'handle_upload_licence_document' ) );
        add_action( 'admin_post_ufsc_remove_licence_document', array( __CLASS__, 'handle_remove_licence_document' ) );


        
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
            $added = WC()->cart->add_to_cart( 4823, 1, 0, array(), array( 'ufsc_club_id' => $club_id ) );
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
                $product = wc_get_product( 4823 );
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
            self::store_form_and_redirect( $_POST, array( $data->get_error_message() ), $licence_id );
        }

        $result = self::save_licence_data( $licence_id, $club_id, $data );
        if ( is_wp_error( $result ) ) {
            self::store_form_and_redirect( $_POST, array( $result->get_error_message() ), $licence_id );
        }

        $new_id = $result;

        $wc_settings = ufsc_get_woocommerce_settings();

        if ( ! function_exists( 'ufsc_quotas_enabled' ) || ufsc_quotas_enabled() ) {
            $included_quota   = isset( $wc_settings['included_licenses'] ) ? (int) $wc_settings['included_licenses'] : 10;
            $current_included = UFSC_SQL::count_included_licences( $club_id );

            $auto_consume = ! empty( $wc_settings['auto_consume_included'] );
            if ( $auto_consume && $current_included < $included_quota ) {
                UFSC_SQL::mark_licence_as_included( $new_id );
                $redirect_url = esc_url_raw( add_query_arg(
                    array(
                        'licence_included' => 1,
                        'licence_id'       => $new_id,
                    ),
                    wp_get_referer()
                ) );
               // wp_safe_redirect( $redirect_url );
                //exit;
            }

            // Quota exceeded: add licence product to cart
            $product_id     = isset( $wc_settings['product_license_id'] ) ? absint( $wc_settings['product_license_id'] ) : 0;
            $cart_item_data = array(
                'ufsc_licence_id' => $new_id,
                'ufsc_club_id'    => $club_id,
                'ufsc_nom'        => isset( $data['nom'] ) ? sanitize_text_field( $data['nom'] ) : '',
                'ufsc_prenom'     => isset( $data['prenom'] ) ? sanitize_text_field( $data['prenom'] ) : '',
                'ufsc_date_naissance' => isset( $data['date_naissance'] ) ? sanitize_text_field( $data['date_naissance'] ) : '',
                'season'          => isset( $wc_settings['season'] ) ? sanitize_text_field( $wc_settings['season'] ) : '',
                'category'        => isset( $data['categorie'] ) ? sanitize_text_field( $data['categorie'] ) : '',
            );

            if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_load_cart' ) ) {
                self::store_form_and_redirect( $_POST, array( __( 'Panier indisponible, veuillez réessayer.', 'ufsc-clubs' ) ), $new_id );
            }

            wc_load_cart();
            if ( ! WC() || ! WC()->cart || $product_id <= 0 ) {
                self::store_form_and_redirect( $_POST, array( __( 'Panier indisponible, veuillez réessayer.', 'ufsc-clubs' ) ), $new_id );
            }

            $added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

            if ( ! $added ) {
                self::store_form_and_redirect( $_POST, array( __( 'Impossible d\'ajouter le produit au panier', 'ufsc-clubs' ) ), $new_id );
            }

            self::update_licence_status_db( $new_id, 'en_attente' );
            if ( function_exists( 'wc_add_notice' ) ) {
                wc_add_notice( __( 'Quota de licences dépassé : licence ajoutée au panier.', 'ufsc-clubs' ), 'notice' );
            }
            if ( function_exists( 'wc_get_cart_url' ) ) {
                self::maybe_redirect( wc_get_cart_url() );
                return;
            }
        }

        if ( isset( $_POST['ufsc_submit_action'] ) && 'add_to_cart' === $_POST['ufsc_submit_action'] ) {

            $wc_settings = ufsc_get_woocommerce_settings();
            $product_id  = isset( $wc_settings['product_license_id'] ) ? absint( $wc_settings['product_license_id'] ) : 0;

            if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_load_cart' ) ) {
                self::store_form_and_redirect( $_POST, array( __( 'Panier indisponible, veuillez réessayer.', 'ufsc-clubs' ) ), $new_id );
            }

            wc_load_cart();
            if ( ! WC() || ! WC()->cart || $product_id <= 0 ) {
                self::store_form_and_redirect( $_POST, array( __( 'Panier indisponible, veuillez réessayer.', 'ufsc-clubs' ) ), $new_id );
            }

            $cart_item_data = array(
                'ufsc_licence_id'     => $new_id,
                'ufsc_club_id'        => $club_id,
                'ufsc_nom'            => isset( $data['nom'] ) ? sanitize_text_field( $data['nom'] ) : '',
                'ufsc_prenom'         => isset( $data['prenom'] ) ? sanitize_text_field( $data['prenom'] ) : '',
                'ufsc_date_naissance' => isset( $data['date_naissance'] ) ? sanitize_text_field( $data['date_naissance'] ) : '',
                'season'              => isset( $wc_settings['season'] ) ? sanitize_text_field( $wc_settings['season'] ) : '',
                'category'            => isset( $data['categorie'] ) ? sanitize_text_field( $data['categorie'] ) : '',
            );
            $added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

            if ( ! $added ) {
                self::store_form_and_redirect( $_POST, array( __( 'Impossible d\'ajouter le produit au panier', 'ufsc-clubs' ) ), $new_id );
            }

            self::update_licence_status_db( $new_id, 'en_attente' );
            if ( function_exists( 'wc_get_cart_url' ) ) {
                self::maybe_redirect( wc_get_cart_url() );
                return;
            }
        }

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
        
        // Required fields
        $required_fields = array( 'prenom', 'nom', 'email','adresse' ,'ville','code_postal','telephone' );
        foreach ( $required_fields as $field ) {
            if ( empty( $post_data[$field] ) ) {
                $errors[] = sprintf( __( 'Le champ %s est requis', 'ufsc-clubs' ), $field );
            } else {
                $data[$field] = sanitize_text_field( $post_data[$field] );
            }
        }
        
        // Email validation (required)
        $email = isset( $post_data['email'] ) ? sanitize_email( wp_unslash( (string) $post_data['email'] ) ) : '';
        if ( '' === $email || ! is_email( $email ) ) {
            $errors[] = __( 'Adresse email invalide', 'ufsc-clubs' );
        } else {
            $data['email'] = $email;
        }

        $date_naissance = isset( $post_data['date_naissance'] ) ? sanitize_text_field( wp_unslash( (string) $post_data['date_naissance'] ) ) : '';
        if ( '' === $date_naissance || '0000-00-00' === $date_naissance || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_naissance ) ) {
            $errors[] = __( 'Date de naissance invalide (YYYY-MM-DD requis)', 'ufsc-clubs' );
        } else {
            $data['date_naissance'] = $date_naissance;
        }
        
        // Optional fields with sanitization
        $optional_fields = array(
            'telephone' => 'sanitize_text_field',
            'adresse' => 'sanitize_textarea_field',
            'ville' => 'sanitize_text_field',
            'code_postal' => 'sanitize_text_field',
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
        if ( empty( $post_data['has_license_number'] ) ) {
            $data['numero_licence'] = '';
        } elseif ( ! empty( $post_data['numero_licence'] ) ) {
            $data['numero_licence'] = sanitize_text_field( $post_data['numero_licence'] );
        }
        
        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation_failed', implode( ', ', $errors ) );
        }
        
        if ( ! empty( $post_data['telephone'] ) ) {
            $data['tel_mobile'] = sanitize_text_field( $post_data['telephone'] );
        }

        $data = self::normalize_licence_date_fields( $data );

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
            if ( function_exists( 'ufsc_get_licence_season' ) && function_exists( 'ufsc_set_licence_season' ) ) {
                $stored_season = ufsc_get_licence_season( (int) $licence_id );
                if ( ! is_string( $stored_season ) || '' === trim( $stored_season ) ) {
                    ufsc_set_licence_season( (int) $licence_id, ufsc_get_current_season() );
                }
            }
            do_action( 'ufsc_licence_updated', (int) $club_id );
            return $licence_id;
        } else {
            $data = self::enforce_server_managed_licence_fields( $data, $column_exists );
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
                    ufsc_set_licence_season( $new_id, ufsc_get_current_season() );
                }
            }
            do_action( 'ufsc_licence_created', $new_id, (int) $club_id );
            do_action( 'ufsc_licence_updated', (int) $club_id );
            return $new_id;
        }
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
