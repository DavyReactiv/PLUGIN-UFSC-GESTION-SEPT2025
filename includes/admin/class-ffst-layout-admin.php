<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Layout unifié de l'écran Dossiers FFST.
 *
 * Correctif purement visuel : aucune lecture/écriture métier supplémentaire.
 */
final class UFSC_FFST_Layout_Admin {
    public static function init() {
        add_action( 'admin_head', array( __CLASS__, 'render_css' ), 99 );
    }

    public static function render_css() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage uniquement.
        if ( 'ufsc-ffst-documents' !== $page ) { return; }
        ?>
        <style id="ufsc-ffst-layout-fix">
            :root{--ufsc-ffst-content-width:1280px}

            /* Une seule largeur de référence pour toute la page. */
            .ufsc-ffst-admin-v2 .ufsc-ffst-shell,
            .ufsc-ffst-official-pack,
            .ufsc-ffst-compliance,
            .ufsc-ffst-insurance{
                width:calc(100% - 40px)!important;
                max-width:var(--ufsc-ffst-content-width)!important;
                box-sizing:border-box!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-shell{margin-right:auto!important}
            .ufsc-ffst-official-pack{margin-right:auto!important}
            .ufsc-ffst-compliance,
            .ufsc-ffst-insurance{margin:18px 20px 0!important}

            /* Les cartes et sections du dossier occupent la même grille. */
            .ufsc-ffst-admin-v2 .postbox,
            .ufsc-ffst-compliance .postbox,
            .ufsc-ffst-insurance .postbox{
                width:100%!important;
                max-width:none!important;
                box-sizing:border-box!important;
            }

            /* Tableau clubs : lisible sur desktop, Action jamais écrasée. */
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap{
                width:100%!important;
                max-width:100%!important;
                overflow-x:hidden!important;
                box-sizing:border-box!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap table{
                width:100%!important;
                min-width:0!important;
                table-layout:fixed!important;
                border-collapse:collapse!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th,
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td{
                vertical-align:middle!important;
                overflow-wrap:break-word;
                word-break:normal!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(1),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(1){width:25%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(2),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(2){width:11%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(3),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(3){width:17%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(4),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(4){width:10%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(5),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(5){width:9%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(6),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(6){width:16%}
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap th:nth-child(7),
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(7){
                width:12%;
                min-width:145px;
                white-space:nowrap!important;
                overflow-wrap:normal!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap td:nth-child(7) .button{
                display:inline-flex!important;
                align-items:center;
                justify-content:center;
                width:auto!important;
                min-width:126px;
                white-space:nowrap!important;
            }
            .ufsc-ffst-admin-v2 .ufsc-ffst-progress{min-width:0!important;width:100%!important}
            .ufsc-ffst-admin-v2 .ufsc-ffst-progress__bar{flex:1 1 auto;min-width:70px;max-width:120px}

            /* Le suivi ne doit plus traverser tout l'écran. */
            .ufsc-ffst-compliance .form-table{width:100%!important;table-layout:auto!important}
            .ufsc-ffst-compliance .form-table th{width:220px!important;min-width:180px}
            .ufsc-ffst-compliance textarea.large-text{
                width:100%!important;
                max-width:820px!important;
                min-height:110px;
                box-sizing:border-box!important;
            }
            .ufsc-ffst-compliance form{width:100%!important;max-width:100%!important}

            /* Même largeur pour le dernier bloc assurances. */
            .ufsc-ffst-insurance{max-width:var(--ufsc-ffst-content-width)!important}

            /* En dessous de 1100 px : on privilégie la lisibilité à l'écrasement. */
            @media (max-width:1100px){
                .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap{overflow-x:auto!important}
                .ufsc-ffst-admin-v2 .ufsc-ffst-table-wrap table{min-width:980px!important}
            }

            @media (max-width:782px){
                .ufsc-ffst-admin-v2 .ufsc-ffst-shell,
                .ufsc-ffst-official-pack,
                .ufsc-ffst-compliance,
                .ufsc-ffst-insurance{
                    width:calc(100% - 20px)!important;
                    max-width:none!important;
                    margin-left:10px!important;
                    margin-right:10px!important;
                }
                .ufsc-ffst-compliance .form-table,
                .ufsc-ffst-compliance .form-table tbody,
                .ufsc-ffst-compliance .form-table tr,
                .ufsc-ffst-compliance .form-table th,
                .ufsc-ffst-compliance .form-table td{display:block;width:100%!important;min-width:0}
                .ufsc-ffst-compliance textarea.large-text{max-width:100%!important}
            }
        </style>
        <?php
    }
}

UFSC_FFST_Layout_Admin::init();
