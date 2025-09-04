<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Frontend media handler for club profile photos.
 */
class UFSC_Media {
    const MAX_SIZE = 5242880; // 5MB

    /**
     * Register actions.
     */
    public static function init() {
        add_action( 'admin_post_ufsc_upload_profile_photo', array( __CLASS__, 'upload_profile_photo_action' ) );
        add_action( 'admin_post_nopriv_ufsc_upload_profile_photo', array( __CLASS__, 'upload_profile_photo_action' ) );
        add_action( 'admin_post_ufsc_remove_profile_photo', array( __CLASS__, 'remove_profile_photo_action' ) );
        add_action( 'admin_post_nopriv_ufsc_remove_profile_photo', array( __CLASS__, 'remove_profile_photo_action' ) );
    }

    /**
     * Allowed mime types for profile photos.
     *
     * @return array
     */
    public static function allowed_mimes() {
        return array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        );
    }

    /**
     * Handle upload from a form field.
     *
     * @param string $field   Field name in the $_FILES array.
     * @param int    $club_id Club identifier.
     * @return string|WP_Error URL of uploaded file or error.
     */
    public static function handle_profile_photo_upload( $field, $club_id = 0 ) {
        if ( empty( $_FILES[ $field ]['name'] ) ) {
            return new WP_Error( 'no_file', __( 'Aucun fichier envoyé.', 'ufsc-clubs' ) );
        }

        $file = $_FILES[ $field ];

        if ( $file['size'] > self::MAX_SIZE ) {
            return new WP_Error( 'file_too_large', __( 'Image trop volumineuse (max 5MB).', 'ufsc-clubs' ) );
        }

        $filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $allowed  = self::allowed_mimes();
        if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed, true ) ) {
            return new WP_Error( 'invalid_file_type', __( 'Type de fichier non autorisé.', 'ufsc-clubs' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = array(
            'test_form' => false,
            'mimes'     => $allowed,
        );

        $attach_id = media_handle_upload( $field, 0, array(), $overrides );
        if ( is_wp_error( $attach_id ) ) {
            return $attach_id;
        }

        $url = wp_get_attachment_url( $attach_id );

        if ( $club_id ) {
            global $wpdb;
            $settings = UFSC_SQL::get_settings();
            $table    = $settings['table_clubs'];
            $wpdb->update( $table, array( 'profile_photo_url' => $url ), array( 'id' => $club_id ) );
        }

        return $url;
    }

    /**
     * Action handler for uploading a profile photo.
     */
    public static function upload_profile_photo_action() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? (int) $_POST['club_id'] : 0;
        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            wp_die( __( 'Permissions insuffisantes.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_upload_profile_photo', 'ufsc_upload_profile_photo_nonce' );

        $result = self::handle_profile_photo_upload( 'profile_photo', $club_id );
        if ( is_wp_error( $result ) ) {
            wp_die( $result->get_error_message() );
        }

        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    /**
     * Remove profile photo action handler.
     */
    public static function remove_profile_photo_action() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? (int) $_POST['club_id'] : 0;
        if ( ! UFSC_CL_Permissions::ufsc_user_can_edit_club( $club_id ) ) {
            wp_die( __( 'Permissions insuffisantes.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_remove_profile_photo', 'ufsc_remove_profile_photo_nonce' );

        self::remove_profile_photo( $club_id );
        wp_safe_redirect( wp_get_referer() );
        exit;
    }

    /**
     * Remove profile photo from database.
     *
     * @param int $club_id Club identifier.
     */
    public static function remove_profile_photo( $club_id ) {
        if ( ! $club_id ) {
            return;
        }
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table    = $settings['table_clubs'];
        $wpdb->update( $table, array( 'profile_photo_url' => '' ), array( 'id' => $club_id ) );
    }
}
