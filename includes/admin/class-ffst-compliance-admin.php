<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Suivi interne FFST : attestations, signatures et documents signés.
 * Aucun élément de ce module n'est exposé côté représentant du club.
 */
final class UFSC_FFST_Compliance_Admin {
    const OPTION_PREFIX = 'ufsc_ffst_compliance_';

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_panel' ) );
        add_action( 'admin_post_ufsc_ffst_save_compliance', array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_ufsc_ffst_upload_signed_document', array( __CLASS__, 'handle_upload' ) );
    }

    private static function can_manage() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
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

    public static function render_panel() {
        if ( ! self::can_manage() || ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-ffst-documents' !== $page ) { return; }
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $club_id ) { return; }
        $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : '';
        $state = self::get_state( $club_id, $season );
        $return_url = admin_url( 'admin.php?page=ufsc-ffst-documents&club_id=' . $club_id );

        echo '<div class="wrap" style="margin-top:18px"><div class="postbox" style="padding:18px">';
        echo '<h2 style="margin-top:0">' . esc_html__( '4. Attestations, signatures et pièces signées', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Suivi interne UFSC uniquement. Utilisez cet écran pour savoir si le dossier signé et les attestations d’assurance ont été reçus.', 'ufsc-clubs' ) . '</p>';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_ffst_save_compliance_' . $club_id );
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
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_ffst_upload_signed_document_' . $club_id );
        echo '<input type="hidden" name="action" value="ufsc_ffst_upload_signed_document">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_url( $return_url ) . '">';
        echo '<select name="document_type"><option value="affiliation">' . esc_html__( 'Dossier affiliation signé', 'ufsc-clubs' ) . '</option><option value="assurance">' . esc_html__( 'Attestation assurance signée', 'ufsc-clubs' ) . '</option><option value="autre">' . esc_html__( 'Autre pièce FFST', 'ufsc-clubs' ) . '</option></select> ';
        echo '<input type="file" name="signed_document" accept="application/pdf,image/jpeg,image/png" required> ';
        echo '<button class="button">' . esc_html__( 'Archiver la pièce', 'ufsc-clubs' ) . '</button></form>';

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

    public static function handle_save() {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ) ); }
        $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
        check_admin_referer( 'ufsc_ffst_save_compliance_' . $club_id );
        $season = isset( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        $state = self::get_state( $club_id, $season );
        $state['affiliation_signed'] = ! empty( $_POST['affiliation_signed'] );
        $state['insurance_received'] = isset( $_POST['insurance_received'] ) ? max( 0, absint( $_POST['insurance_received'] ) ) : 0;
        $state['insurance_expected'] = isset( $_POST['insurance_expected'] ) ? max( 0, absint( $_POST['insurance_expected'] ) ) : 0;
        $state['insurance_complete'] = ! empty( $_POST['insurance_complete'] );
        $state['notes'] = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $state['updated_at'] = current_time( 'mysql' );
        $state['updated_by'] = get_current_user_id();
        update_option( self::key( $club_id, $season ), $state, false );
        self::redirect_back( 'saved' );
    }

    public static function handle_upload() {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ) ); }
        $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
        check_admin_referer( 'ufsc_ffst_upload_signed_document_' . $club_id );
        $season = isset( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        if ( empty( $_FILES['signed_document']['name'] ) ) { self::redirect_back( 'missing_file' ); }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $overrides = array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ) );
        $upload = wp_handle_upload( $_FILES['signed_document'], $overrides );
        if ( isset( $upload['error'] ) ) { self::redirect_back( 'upload_error' ); }
        $type = isset( $_POST['document_type'] ) ? sanitize_key( wp_unslash( $_POST['document_type'] ) ) : 'autre';
        $labels = array( 'affiliation' => __( 'Dossier affiliation signé', 'ufsc-clubs' ), 'assurance' => __( 'Attestation assurance signée', 'ufsc-clubs' ), 'autre' => __( 'Autre pièce FFST', 'ufsc-clubs' ) );
        $state = self::get_state( $club_id, $season );
        $state['signed_documents'][] = array( 'type' => $type, 'label' => isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['autre'], 'url' => esc_url_raw( $upload['url'] ), 'file' => $upload['file'], 'date' => current_time( 'mysql' ), 'user_id' => get_current_user_id() );
        if ( 'affiliation' === $type ) { $state['affiliation_signed'] = true; }
        $state['updated_at'] = current_time( 'mysql' );
        $state['updated_by'] = get_current_user_id();
        update_option( self::key( $club_id, $season ), $state, false );
        self::redirect_back( 'uploaded' );
    }

    private static function redirect_back( $status ) {
        $redirect = isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_REQUEST['redirect_to'] ), admin_url( 'admin.php?page=ufsc-ffst-documents' ) ) : admin_url( 'admin.php?page=ufsc-ffst-documents' );
        wp_safe_redirect( add_query_arg( 'ffst_status', sanitize_key( $status ), $redirect ) );
        exit;
    }
}

UFSC_FFST_Compliance_Admin::init();
