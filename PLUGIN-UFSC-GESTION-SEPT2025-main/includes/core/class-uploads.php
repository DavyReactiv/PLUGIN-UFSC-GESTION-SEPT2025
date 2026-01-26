<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Upload handling using WordPress media_handle_upload
 */
class UFSC_Uploads {

    /**
     * Allowed MIME types for logos.
     *
     * @return array
     */
    public static function get_logo_mime_types() {
        return array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
        );
    }

    /**
     * Allowed MIME types for documents.
     *
     * @return array
     */
    public static function get_document_mime_types() {
        return array(
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        );
    }

    /**
     * Maximum logo file size in bytes.
     *
     * @return int
     */
    public static function get_logo_max_size() {
        return 2097152; // 2MB
    }

    /**
     * Maximum document file size in bytes.
     *
     * @return int
     */
    public static function get_document_max_size() {
        return 5242880; // 5MB
    }

    /**
     * Handle a single upload field using media_handle_upload.
     *
     * @param string $field        Field name in the $_FILES array.
     * @param int    $post_id      Post ID to associate, 0 for none.
     * @param array  $allowed_mimes Allowed MIME types.
     * @param int    $max_size      Maximum file size in bytes.
     * @return int|WP_Error Attachment ID on success.
     */
    public static function handle_single_upload_field( $field, $post_id = 0, $allowed_mimes = array(), $max_size = 5242880 ) {
        if ( empty( $_FILES[ $field ]['name'] ) ) {
            return 0;
        }

        $file = $_FILES[ $field ];

        if ( $file['size'] > $max_size ) {
            $max_mb = $max_size / 1048576;
            return new WP_Error( 'file_too_large', sprintf( __( 'Le fichier est trop volumineux. Taille maximum : %s MB', 'ufsc-clubs' ), $max_mb ) );
        }

        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( $allowed_mimes && ( ! $filetype['type'] || ! in_array( $filetype['type'], $allowed_mimes, true ) ) ) {
            return new WP_Error( 'invalid_file_type', __( 'Type de fichier non autorisé', 'ufsc-clubs' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = array( 'test_form' => false );
        if ( ! empty( $allowed_mimes ) ) {
            $overrides['mimes'] = $allowed_mimes;
        }

        $attach_id = media_handle_upload( $field, $post_id, array(), $overrides );
        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        return (int) $attach_id;
    }

    /**
     * Handle required club document uploads.
     *
     * @param int $post_id Post ID to associate, 0 for none.
     * @return array|WP_Error Array of meta_key => attachment_id.
     */
    public static function handle_required_docs( $post_id = 0 ) {
        $allowed_mimes = self::get_document_mime_types();
        $max_size      = self::get_document_max_size();

        $fields = array(
            'doc_statuts'         => 'statuts_upload',
            'doc_recepisse'       => 'recepisse_upload',
            'doc_jo'              => 'jo_upload',
            'doc_pv_ag'           => 'pv_ag_upload',
            'doc_cer'             => 'cer_upload',
            'doc_attestation_cer' => 'attestation_cer_upload',
        );

        $results = array();
        foreach ( $fields as $meta_key => $field_name ) {
            if ( empty( $_FILES[ $field_name ]['name'] ) ) {
                continue;
            }

            $attach_id = self::handle_single_upload_field( $field_name, $post_id, $allowed_mimes, $max_size );
            if ( is_wp_error( $attach_id ) ) {
                return $attach_id;
            }

            $results[ $meta_key ] = (int) $attach_id;
        }

        return $results;
    }
}
