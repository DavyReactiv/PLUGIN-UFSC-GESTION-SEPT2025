<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Suivi interne FFST : signatures, attestations et pièces archivées.
 *
 * Ce module est volontairement isolé des données métier clubs/licences :
 * il n'écrit que dans une option dédiée par club + saison et dans les uploads.
 */
final class UFSC_FFST_Compliance_Admin {
    const OPTION_PREFIX = 'ufsc_ffst_compliance_';
    const MAX_UPLOAD_BYTES = 10485760; // 10 Mo.

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_panel' ) );
        add_action( 'admin_post_ufsc_ffst_save_compliance', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_ufsc_ffst_upload_signed_document', array( __CLASS__, 'handle_upload' ) );
    }

    private static function can_manage() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function current_season() {
        return class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    }

    private static function normalize_season( $season ) {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::normalize_season( $season );
        }
        $season = sanitize_text_field( (string) $season );
        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
    }

    private static function key( $club_id, $season ) {
        return self::OPTION_PREFIX . absint( $club_id ) . '_' . sanitize_key( str_replace( '/', '-', (string) $season ) );
    }

    public static function get_state( $club_id, $season ) {
        $defaults = array(
            'affiliation_signed' => false,
            'insurance_received' => 0,
            'insurance_expected' => 0,
            'insurance_complete' => false,
            'notes' => '',
            'signed_documents' => array(),
            'updated_at' => '',
            'updated_by' => 0,
        );
        $stored = get_option( self::key( $club_id, $season ), array() );
        return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
    }

    private static function club_exists( $club_id ) {
        global $wpdb;
        $club_id = absint( $club_id );
        if ( ! $club_id ) { return false; }
        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_clubs_table()
            : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) )
            : 'id';
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    public static function render_panel() {
        if ( ! self::can_manage() || ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : '';
        if ( 'ufsc-ffst-documents' !== $page ) { return; }

        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        if ( ! $club_id || ! self::club_exists( $club_id ) ) { return; }

        $requested_season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
            ? wp_unslash( $_GET['season'] )
            : self::current_season();
        $season = self::normalize_season( $requested_season );
        if ( ! $season ) {
            $season = self::normalize_season( self::current_season() );
        }
        if ( ! $season ) { return; }

        $state = self::get_state( $club_id, $season );
        $return_args = array(
            'page' => 'ufsc-ffst-documents',
            'club_id' => $club_id,
            'season' => $season,
        );
        foreach ( array( 's', 'paged', 'per_page' ) as $key ) {
            if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- conservation de filtres lecture seule.
                $value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
                if ( '' !== $value ) {
                    $return_args[ $key ] = $value;
                }
            }
        }
        $return_url = add_query_arg( $return_args, admin_url( 'admin.php' ) );

        echo '<style>
            .ufsc-ffst-compliance{width:auto;max-width:none;margin:18px 0 0;box-sizing:border-box}
            .ufsc-ffst-compliance .postbox{width:100%;max-width:none;padding:20px;border-radius:10px;overflow:hidden;box-sizing:border-box}
            .ufsc-ffst-compliance .form-table{table-layout:fixed;width:100%}
            .ufsc-ffst-compliance .form-table th{width:220px}
            .ufsc-ffst-compliance textarea.large-text{width:100%;max-width:100%;box-sizing:border-box;min-height:110px}
            .ufsc-ffst-compliance form{max-width:100%}
            @media(max-width:782px){
                .ufsc-ffst-compliance .form-table,
                .ufsc-ffst-compliance .form-table tbody,
                .ufsc-ffst-compliance .form-table tr,
                .ufsc-ffst-compliance .form-table th,
                .ufsc-ffst-compliance .form-table td{display:block;width:100%}
                .ufsc-ffst-compliance .form-table th{padding-bottom:4px}
                .ufsc-ffst-compliance .form-table td{padding-top:4px}
            }
        </style>';

        echo '<div class="ufsc-ffst-compliance"><div class="postbox">';
        echo '<h2 style="margin-top:0">' . esc_html__( '4. Suivi, signatures et pièces FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html( sprintf( __( 'Suivi interne UFSC — saison %s. Ces informations n’altèrent ni les fiches clubs, ni les licences, ni les commandes WooCommerce.', 'ufsc-clubs' ), $season ) ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_ffst_save_compliance_' . $club_id . '_' . $season );
        echo '<input type="hidden" name="action" value="ufsc_ffst_save_compliance">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url( $return_url ) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__( 'Dossier affiliation signé', 'ufsc-clubs' ) . '</th><td><label><input type="checkbox" name="affiliation_signed" value="1" ' . checked( ! empty( $state['affiliation_signed'] ), true, false ) . '> ' . esc_html__( 'Document signé reçu', 'ufsc-clubs' ) . '</label></td></tr>';
        echo '<tr><th>' . esc_html__( 'Attestations assurance', 'ufsc-clubs' ) . '</th><td><input type="number" min="0" name="insurance_received" value="' . esc_attr( (int) $state['insurance_received'] ) . '" style="width:90px"> / <input type="number" min="0" name="insurance_expected" value="' . esc_attr( (int) $state['insurance_expected'] ) . '" style="width:90px"> <label style="margin-left:12px"><input type="checkbox" name="insurance_complete" value="1" ' . checked( ! empty( $state['insurance_complete'] ), true, false ) . '> ' . esc_html__( 'Contrôle terminé', 'ufsc-clubs' ) . '</label></td></tr>';
        echo '<tr><th>' . esc_html__( 'Notes internes', 'ufsc-clubs' ) . '</th><td><textarea name="notes" rows="4" class="large-text">' . esc_textarea( $state['notes'] ) . '</textarea></td></tr>';
        echo '</tbody></table>';
        submit_button( __( 'Enregistrer le suivi FFST', 'ufsc-clubs' ) );
        echo '</form>';

        echo '<hr><h3>' . esc_html__( 'Archiver une pièce signée', 'ufsc-clubs' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Formats acceptés : PDF, JPG/JPEG ou PNG, 10 Mo maximum.', 'ufsc-clubs' ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_ffst_upload_signed_document_' . $club_id . '_' . $season );
        echo '<input type="hidden" name="action" value="ufsc_ffst_upload_signed_document">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url( $return_url ) . '">';
        echo '<select name="document_type"><option value="affiliation">' . esc_html__( 'Dossier affiliation signé', 'ufsc-clubs' ) . '</option><option value="assurance">' . esc_html__( 'Attestation assurance signée', 'ufsc-clubs' ) . '</option><option value="autre">' . esc_html__( 'Autre pièce FFST', 'ufsc-clubs' ) . '</option></select> ';
        echo '<input type="file" name="signed_document" accept="application/pdf,image/jpeg,image/png" required> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Archiver la pièce', 'ufsc-clubs' ) . '</button>';
        echo '</form>';

        if ( ! empty( $state['signed_documents'] ) ) {
            echo '<h3>' . esc_html__( 'Pièces archivées', 'ufsc-clubs' ) . '</h3><ul>';
            foreach ( array_reverse( (array) $state['signed_documents'] ) as $document ) {
                $label = isset( $document['label'] ) ? $document['label'] : __( 'Document FFST', 'ufsc-clubs' );
                $url = isset( $document['url'] ) ? $document['url'] : '';
                echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a> — ' . esc_html( isset( $document['date'] ) ? $document['date'] : '' ) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div></div>';
    }

    private static function guard_post( $action ) {
        if ( ! self::can_manage() || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), 403 );
        }
        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? self::normalize_season( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! $season || ! self::club_exists( $club_id ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), 400 );
        }
        check_admin_referer( $action . '_' . $club_id . '_' . $season );
        return array( $club_id, $season );
    }

    public static function handle_save() {
        list( $club_id, $season ) = self::guard_post( 'ufsc_ffst_save_compliance' );
        $state = self::get_state( $club_id, $season );
        $state['affiliation_signed'] = ! empty( $_POST['affiliation_signed'] );
        $state['insurance_received'] = isset( $_POST['insurance_received'] ) ? max( 0, absint( wp_unslash( $_POST['insurance_received'] ) ) ) : 0;
        $state['insurance_expected'] = isset( $_POST['insurance_expected'] ) ? max( 0, absint( wp_unslash( $_POST['insurance_expected'] ) ) ) : 0;
        $state['insurance_complete'] = ! empty( $_POST['insurance_complete'] );
        $state['notes'] = isset( $_POST['notes'] ) && ! is_array( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $state['updated_at'] = current_time( 'mysql' );
        $state['updated_by'] = get_current_user_id();
        update_option( self::key( $club_id, $season ), $state, false );
        self::redirect_back( 'saved', $club_id, $season );
    }

    public static function handle_upload() {
        list( $club_id, $season ) = self::guard_post( 'ufsc_ffst_upload_signed_document' );
        if ( empty( $_FILES['signed_document'] ) || empty( $_FILES['signed_document']['name'] ) ) {
            self::redirect_back( 'missing_file', $club_id, $season );
        }
        if ( ! empty( $_FILES['signed_document']['size'] ) && (int) $_FILES['signed_document']['size'] > self::MAX_UPLOAD_BYTES ) {
            self::redirect_back( 'file_too_large', $club_id, $season );
        }

        $type = isset( $_POST['document_type'] ) && ! is_array( $_POST['document_type'] ) ? sanitize_key( wp_unslash( $_POST['document_type'] ) ) : 'autre';
        if ( ! in_array( $type, array( 'affiliation', 'assurance', 'autre' ), true ) ) { $type = 'autre'; }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = array(
            'test_form' => false,
            'mimes' => array(
                'pdf' => 'application/pdf',
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ),
        );
        $upload = wp_handle_upload( $_FILES['signed_document'], $overrides );
        if ( ! is_array( $upload ) || isset( $upload['error'] ) || empty( $upload['url'] ) || empty( $upload['file'] ) ) {
            self::redirect_back( 'upload_error', $club_id, $season );
        }

        $labels = array(
            'affiliation' => __( 'Dossier affiliation signé', 'ufsc-clubs' ),
            'assurance' => __( 'Attestation assurance signée', 'ufsc-clubs' ),
            'autre' => __( 'Autre pièce FFST', 'ufsc-clubs' ),
        );
        $state = self::get_state( $club_id, $season );
        $state['signed_documents'][] = array(
            'type' => $type,
            'label' => $labels[ $type ],
            'url' => esc_url_raw( $upload['url'] ),
            'file' => sanitize_text_field( $upload['file'] ),
            'date' => current_time( 'mysql' ),
            'user_id' => get_current_user_id(),
        );
        if ( count( $state['signed_documents'] ) > 100 ) {
            $state['signed_documents'] = array_slice( $state['signed_documents'], -100 );
        }
        if ( 'affiliation' === $type ) { $state['affiliation_signed'] = true; }
        $state['updated_at'] = current_time( 'mysql' );
        $state['updated_by'] = get_current_user_id();
        update_option( self::key( $club_id, $season ), $state, false );

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'ffst_signed_document_archived', array( 'club_id' => $club_id, 'season' => $season, 'type' => $type ) );
        }
        self::redirect_back( 'uploaded', $club_id, $season );
    }

    private static function redirect_back( $status, $club_id, $season = '' ) {
        $args = array(
            'page' => 'ufsc-ffst-documents',
            'club_id' => absint( $club_id ),
        );
        $season = self::normalize_season( $season );
        if ( $season ) {
            $args['season'] = $season;
        }
        $fallback = add_query_arg( $args, admin_url( 'admin.php' ) );
        $redirect = isset( $_REQUEST['redirect_to'] ) && ! is_array( $_REQUEST['redirect_to'] )
            ? wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), $fallback )
            : $fallback;
        wp_safe_redirect( add_query_arg( 'ffst_status', sanitize_key( $status ), $redirect ) );
        exit;
    }
}

UFSC_FFST_Compliance_Admin::init();
