<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Secure upload handling utility
 */
class UFSC_CL_Uploads {
    
    /**
     * Safely handle file uploads with validation
     * 
     * @param array $file $_FILES array element
     * @param array $allowed_mimes Allowed MIME types
     * @param int $max_size_bytes Maximum file size in bytes
     * @return array|WP_Error Upload result with 'url' and 'attachment_id' on success
     */
    public static function ufsc_safe_handle_upload( $file, $allowed_mimes = array(), $max_size_bytes = 5242880 ) {
        // Default allowed MIME types for documents
        if ( empty( $allowed_mimes ) ) {
            $allowed_mimes = array(
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png'
            );
        }
        
        // Check if file was uploaded
        if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
            return new WP_Error( 'no_file', __( 'Aucun fichier fourni', 'ufsc-clubs' ) );
        }
        
        // Check file size
        if ( $file['size'] > $max_size_bytes ) {
            $max_mb = $max_size_bytes / 1048576;
            return new WP_Error( 'file_too_large', sprintf( 
                __( 'Le fichier est trop volumineux. Taille maximum : %s MB', 'ufsc-clubs' ), 
                $max_mb 
            ) );
        }
        
        // Validate file type using WordPress functions
        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        
        if ( ! $filetype['type'] || ! in_array( $filetype['type'], $allowed_mimes ) ) {
            return new WP_Error( 'invalid_file_type', __( 'Type de fichier non autorisé', 'ufsc-clubs' ) );
        }
        
        // Include required WordPress upload functions
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        
        // Handle upload with custom filename sanitization
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function( $dir, $name, $ext ) {
                $name = remove_accents( $name );
                $name = sanitize_file_name( $name );
                $hash = substr( md5( uniqid( '', true ) ), 0, 8 );
                return $name . '-' . $hash . $ext;
            },
        );
        $uploaded_file = wp_handle_upload( $file, $upload_overrides );
        
        if ( isset( $uploaded_file['error'] ) ) {
            return new WP_Error( 'upload_error', $uploaded_file['error'] );
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title'     => sanitize_file_name( $file['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );
        
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }
        
        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );
        
        return array(
            'url' => $uploaded_file['url'],
            'attachment_id' => $attachment_id,
            'file_path' => $uploaded_file['file']
        );
    }
    
    /**
     * Get allowed MIME types for logo uploads
     * 
     * @return array Allowed MIME types for logos
     */
    public static function get_logo_mime_types() {
        return array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        );
    }
    
    /**
     * Get allowed MIME types for document uploads
     * 
     * @return array Allowed MIME types for documents
     */
    public static function get_document_mime_types() {
        return array(
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        );
    }

    /**
     * Handle upload of required club documents
     *
     * Validates and stores uploaded files for statutory documents.
     * Returns attachment IDs keyed by document field name.
     *
     * @param int $club_id Club identifier for meta association
     * @return array|WP_Error Array of attachment IDs or WP_Error on failure
     */
    public static function handle_required_docs( int $club_id ) {
        $allowed_mimes = self::get_document_mime_types();
        $max_size      = self::get_document_max_size();

        $document_fields = array(
            'doc_statuts'        => 'statuts_upload',
            'doc_recepisse'      => 'recepisse_upload',
            'doc_jo'             => 'jo_upload',
            'doc_pv_ag'          => 'pv_ag_upload',
            'doc_cer'            => 'cer_upload',
            'doc_attestation_cer'=> 'attestation_cer_upload',
        );

        $results = array();

        foreach ( $document_fields as $db_field => $upload_field ) {
            if ( empty( $_FILES[ $upload_field ]['name'] ) ) {
                continue;
            }

            $upload = self::ufsc_safe_handle_upload(
                $_FILES[ $upload_field ],
                $allowed_mimes,
                $max_size
            );

            if ( is_wp_error( $upload ) ) {
                return $upload;
            }

            $attachment_id        = $upload['attachment_id'];
            $results[ $db_field ] = $attachment_id;

            if ( $club_id ) {
                update_post_meta( $club_id, $db_field, $attachment_id );
                update_post_meta( $club_id, $db_field . '_status', 'pending' );
            }
        }

        return $results;
    }
    
    /**
     * Get maximum file size for logos (2MB)
     * 
     * @return int Maximum file size in bytes
     */
    public static function get_logo_max_size() {
        return 2097152; // 2MB
    }
    
    /**
     * Get maximum file size for documents (5MB)
     * 
     * @return int Maximum file size in bytes
     */
    public static function get_document_max_size() {
        return 5242880; // 5MB
    }
}
