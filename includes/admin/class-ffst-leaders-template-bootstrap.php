<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Assure la présence locale du modèle officiel FFST dirigeants avant export.
 * Ne touche à aucune donnée métier : uniquement une copie locale du gabarit.
 */
final class UFSC_FFST_Leaders_Template_Bootstrap {
    const ACTION = 'ufsc_ffst_generate_licences';
    const BASENAME = '02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';
    const OFFICIAL_URL = 'https://ufsc-france.fr/wp-content/uploads/2026/09/02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'ensure_for_export_request' ), 1 );
    }

    public static function ensure_for_export_request() {
        if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) { return; }
        $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        if ( self::ACTION !== $action ) { return; }
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { return; }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) { return; }

        $dir = trailingslashit( $uploads['basedir'] ) . '2026/09';
        $target = trailingslashit( $dir ) . self::BASENAME;
        if ( is_readable( $target ) && filesize( $target ) > 1024 ) { return; }

        if ( ! wp_mkdir_p( $dir ) && ! is_dir( $dir ) ) { return; }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = download_url( self::OFFICIAL_URL, 20 );
        if ( is_wp_error( $tmp ) ) {
            if ( function_exists( 'ufsc_log_error' ) ) {
                ufsc_log_error( 'FFST leaders template download: ' . $tmp->get_error_message() );
            }
            return;
        }

        $valid = is_readable( $tmp ) && filesize( $tmp ) > 1024;
        if ( $valid ) {
            $contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if ( false !== $contents ) {
                $written = file_put_contents( $target, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                $valid = false !== $written && $written > 1024;
            } else {
                $valid = false;
            }
        }
        @unlink( $tmp );

        if ( ! $valid && file_exists( $target ) ) {
            @unlink( $target );
        }
    }
}

UFSC_FFST_Leaders_Template_Bootstrap::init();
