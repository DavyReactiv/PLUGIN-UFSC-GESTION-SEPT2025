<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ensure the official FFST affiliation DOCX is available in the uploads tree
 * of the WordPress instance currently handling the request (DEV/PROD).
 *
 * The canonical production document is copied only when the local instance
 * does not already have a readable template. No existing file is overwritten.
 */
final class UFSC_FFST_Template_Sync {
    const FILENAME = '01-AFFIL-REAFFIL-FFST-26-27.docx';
    const CANONICAL_URL = 'https://ufsc-france.fr/wp-content/uploads/2026/09/01-AFFIL-REAFFIL-FFST-26-27.docx';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_sync' ), 1 );
    }

    public static function maybe_sync() {
        if ( ! is_admin() || ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return;
        }

        $action = '';
        if ( isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- dispatch only; target handler validates nonce.
            $action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
        }
        if ( ! in_array( $action, array( 'ufsc_ffst_generate_official_template_affiliation', 'ufsc_ffst_generate_affiliation' ), true ) ) {
            return;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) { return; }

        $directory = trailingslashit( $uploads['basedir'] ) . '2026/09';
        $target = trailingslashit( $directory ) . self::FILENAME;
        if ( is_readable( $target ) ) { return; }

        // A media import may have put the same file in another year/month.
        $pattern = trailingslashit( $uploads['basedir'] ) . '*/*/' . self::FILENAME;
        foreach ( (array) glob( $pattern ) as $existing ) {
            if ( is_readable( $existing ) ) {
                if ( ! wp_mkdir_p( $directory ) ) { return; }
                @copy( $existing, $target );
                if ( is_readable( $target ) ) { return; }
            }
        }

        // DEV and PROD can have distinct upload roots. Use the canonical public
        // UFSC document as a read-only source and cache one local copy.
        $response = wp_safe_remote_get( self::CANONICAL_URL, array( 'timeout' => 15, 'redirection' => 3 ) );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return; }
        $body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $body ) || strlen( $body ) < 100 || 0 !== strncmp( $body, 'PK', 2 ) ) { return; }
        if ( ! wp_mkdir_p( $directory ) ) { return; }

        $written = file_put_contents( $target, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( false === $written || ! is_readable( $target ) ) {
            @unlink( $target );
        }
    }
}

UFSC_FFST_Template_Sync::init();
