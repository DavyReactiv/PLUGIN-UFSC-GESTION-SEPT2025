<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Correctif visuel ciblé de l'écran Dossiers FFST.
 * Aucun traitement métier ni aucune écriture de données.
 */
final class UFSC_FFST_Documents_Layout_Fix {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_css' ), 999 );
    }

    public static function render_css() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- CSS contextuel uniquement.
        if ( 'ufsc-ffst-documents' !== $page ) { return; }
        ?>
        <style id="ufsc-ffst-documents-layout-fix">
            .ufsc-ffst-compliance,
            .ufsc-ffst-insurance{
                width:calc(100% - 40px)!important;
                max-width:1180px!important;
                margin:18px auto 0!important;
                box-sizing:border-box!important;
            }
            .ufsc-ffst-compliance>.postbox,
            .ufsc-ffst-insurance>.postbox{
                width:100%!important;
                max-width:100%!important;
                margin:0!important;
                padding:20px!important;
                box-sizing:border-box!important;
                border-radius:10px!important;
            }
            .ufsc-ffst-compliance form,
            .ufsc-ffst-insurance form{
                width:100%!important;
                max-width:100%!important;
                box-sizing:border-box!important;
            }
            .ufsc-ffst-compliance .form-table{
                width:100%!important;
                max-width:100%!important;
                table-layout:fixed!important;
            }
            .ufsc-ffst-compliance input[type="file"]{
                max-width:100%;
            }
            @media(max-width:782px){
                .ufsc-ffst-compliance,
                .ufsc-ffst-insurance{
                    width:calc(100% - 20px)!important;
                    margin:14px auto 0!important;
                }
                .ufsc-ffst-compliance>.postbox,
                .ufsc-ffst-insurance>.postbox{
                    padding:16px!important;
                }
            }
        </style>
        <?php
    }
}

UFSC_FFST_Documents_Layout_Fix::init();
