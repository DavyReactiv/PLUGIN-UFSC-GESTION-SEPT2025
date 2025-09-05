<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Manage club documents uploads and downloads.
 */
class UFSC_Documents {

    /**
     * Register hooks for document management.
     */
    public static function init() {
        add_action( 'admin_post_ufsc_upload_document', array( __CLASS__, 'handle_upload' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_download' ) );
        add_action( 'admin_post_ufsc_export_documents', array( __CLASS__, 'export_documents' ) );
    }

    /**
     * Handle document uploads from club dashboard forms.
     */
    public static function handle_upload() {
        if ( ! isset( $_POST['club_id'], $_POST['_wpnonce'] ) ) {
            wp_die( __( 'Requête invalide.', 'ufsc-clubs' ) );
        }

        $club_id = (int) $_POST['club_id'];

        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ufsc_upload_document_' . $club_id ) ) {
            wp_die( __( 'Nonce invalide.', 'ufsc-clubs' ) );
        }

        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        if ( empty( $_FILES['ufsc_document']['name'] ) ) {
            wp_die( __( 'Aucun fichier fourni.', 'ufsc-clubs' ) );
        }

        $uploaded_file = $_FILES['ufsc_document'];

        $allowed_mimes = array(
            'application/pdf',
            'image/jpeg',
            'image/png',
        );
        $max_size = 5 * 1024 * 1024; // 5MB

        if ( $uploaded_file['size'] > $max_size ) {
            wp_die( __( 'Fichier trop volumineux.', 'ufsc-clubs' ) );
        }

        $file_type = wp_check_filetype( $uploaded_file['name'] );
        if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed_mimes, true ) ) {
            wp_die( __( 'Type de fichier non autorisé.', 'ufsc-clubs' ) );
        }

        $file = wp_handle_upload( $uploaded_file, array( 'test_form' => false ) );

        if ( isset( $file['error'] ) ) {
            wp_die( esc_html( $file['error'] ) );
        }

        $file_size = (int) $uploaded_file['size'];

        global $wpdb;
        $table = $wpdb->prefix . 'ufsc_club_docs';

        $wpdb->insert(
            $table,
            array(
                'club_id'     => $club_id,
                'file_name'   => basename( $file['file'] ),
                'file_path'   => $file['file'],
                'file_url'    => $file['url'],
                'file_size'   => $file_size,
                'mime_type'   => $file_type['type'],
                'status'      => 'pending',
                'uploaded_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    /**
     * Provide secure download for documents.
     */
    public static function maybe_download() {
        if ( empty( $_GET['ufsc_doc'] ) || empty( $_GET['nonce'] ) ) {
            return;
        }

        $doc_id = (int) $_GET['ufsc_doc'];
        $nonce  = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'ufsc_download_doc_' . $doc_id ) ) {
            wp_die( __( 'Lien de téléchargement invalide.', 'ufsc-clubs' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ufsc_club_docs';
        $doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );

        if ( ! $doc ) {
            wp_die( __( 'Document introuvable.', 'ufsc-clubs' ) );
        }

        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( (int) $doc->club_id ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        if ( ! file_exists( $doc->file_path ) ) {
            wp_die( __( 'Fichier introuvable.', 'ufsc-clubs' ) );
        }

        header( 'Content-Type: ' . $doc->mime_type );
        header( 'Content-Disposition: attachment; filename="' . basename( $doc->file_name ) . '"' );
        readfile( $doc->file_path );
        exit;
    }

    /**
     * Export club documents as CSV.
     */
    public static function export_documents() {
        if ( ! isset( $_GET['club_id'], $_GET['_wpnonce'] ) ) {
            wp_die( __( 'Requête invalide.', 'ufsc-clubs' ) );
        }

        $club_id = (int) $_GET['club_id'];

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ufsc_export_documents_' . $club_id ) ) {
            wp_die( __( 'Nonce invalide.', 'ufsc-clubs' ) );
        }

        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $documents = self::get_club_documents( $club_id );

        if ( ob_get_level() ) {
            ob_end_clean();
        }

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="club-documents-' . $club_id . '.csv"' );

        echo "\xEF\xBB\xBF";

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Nom', 'URL', 'Type', 'Date' ) );

        if ( $documents ) {
            foreach ( $documents as $doc ) {
                fputcsv( $output, array( $doc->id, $doc->file_name, $doc->file_url, $doc->mime_type, $doc->uploaded_at ) );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Get documents for a club.
     *
     * @param int $club_id Club ID.
     * @return array
     */
    public static function get_club_documents( $club_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ufsc_club_docs';

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE club_id = %d ORDER BY uploaded_at DESC", $club_id )
        );
    }

    /**
     * Determine dashicon class for a mime type.
     *
     * @param string $mime Mime type.
     * @return string
     */
    public static function get_file_icon( $mime ) {
        if ( strpos( $mime, 'pdf' ) !== false ) {
            return 'dashicons-media-document';
        }
        if ( strpos( $mime, 'image/' ) === 0 ) {
            return 'dashicons-format-image';
        }
        if ( strpos( $mime, 'spreadsheet' ) !== false || strpos( $mime, 'excel' ) !== false ) {
            return 'dashicons-media-spreadsheet';
        }
        if ( strpos( $mime, 'word' ) !== false || strpos( $mime, 'text' ) !== false ) {
            return 'dashicons-media-text';
        }
        return 'dashicons-media-default';
    }
}
