<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Presentation-only repair for the FFST administration screen.
 *
 * The FFST page contains several widefat tables and postboxes. On large desktop
 * screens they currently stretch across the whole viewport, making labels,
 * controls and document tracking difficult to scan. This file changes CSS only:
 * no club, licence, season, quota, document or WooCommerce data is touched.
 */
function ufsc_ffst_admin_layout_hotfix() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page scope check.
    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
        ? sanitize_key( wp_unslash( $_GET['page'] ) )
        : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    if ( 'ufsc-ffst-documents' !== $page ) {
        return;
    }
    ?>
    <style id="ufsc-ffst-admin-layout-hotfix">
        .ufsc-ffst-admin {
            width: min(1320px, calc(100% - 20px)) !important;
            max-width: 1320px !important;
            margin-right: auto !important;
        }

        .ufsc-ffst-admin .ufsc-ffst-filters,
        .ufsc-ffst-admin .ufsc-ffst-table-wrap,
        .ufsc-ffst-admin .ufsc-dashboard-cards,
        .ufsc-ffst-admin .postbox {
            width: 100% !important;
            max-width: 1280px !important;
            box-sizing: border-box !important;
        }

        .ufsc-ffst-admin .ufsc-ffst-table-wrap {
            overflow-x: auto;
        }

        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table {
            width: 100%;
            table-layout: fixed;
        }

        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(1),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(1) { width: 32%; }
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(2),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(2) { width: 10%; }
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(3),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(3) { width: 12%; }
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(4),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(4) { width: 10%; }
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(5),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(5) { width: 20%; }
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table th:nth-child(6),
        .ufsc-ffst-admin .ufsc-ffst-table-wrap > table td:nth-child(6) { width: 16%; }

        .ufsc-ffst-admin table.widefat th,
        .ufsc-ffst-admin table.widefat td {
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .ufsc-ffst-admin .ufsc-ffst-table-wrap td:last-child .button,
        .ufsc-ffst-admin .postbox .button {
            white-space: normal;
            height: auto;
            min-height: 30px;
            line-height: 1.25;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .ufsc-ffst-admin .ufsc-dashboard-cards {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 12px !important;
            margin: 18px 0 !important;
        }

        .ufsc-ffst-admin .ufsc-dashboard-card {
            min-width: 0 !important;
            width: auto !important;
            box-sizing: border-box !important;
        }

        .ufsc-ffst-admin .postbox {
            padding: 16px !important;
        }

        .ufsc-ffst-admin .postbox > table.widefat {
            width: 100%;
            table-layout: fixed;
        }

        .ufsc-ffst-admin .postbox > table.widefat th,
        .ufsc-ffst-admin .postbox > table.widefat td {
            vertical-align: top;
        }

        .ufsc-ffst-admin .postbox input[type="text"],
        .ufsc-ffst-admin .postbox input[type="search"],
        .ufsc-ffst-admin .postbox input[type="email"],
        .ufsc-ffst-admin .postbox input[type="url"],
        .ufsc-ffst-admin .postbox select,
        .ufsc-ffst-admin .postbox textarea {
            max-width: 820px;
        }

        .ufsc-ffst-admin .postbox textarea {
            width: min(100%, 820px) !important;
            min-height: 90px;
        }

        .ufsc-ffst-admin .ufsc-ffst-progress {
            min-width: 0 !important;
            max-width: 190px;
        }

        .ufsc-ffst-admin .ufsc-ffst-progress__bar {
            flex: 1 1 auto;
            min-width: 60px;
        }

        @media (max-width: 1200px) {
            .ufsc-ffst-admin .ufsc-dashboard-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 782px) {
            .ufsc-ffst-admin {
                width: calc(100% - 10px) !important;
                max-width: none !important;
            }

            .ufsc-ffst-admin .ufsc-dashboard-cards {
                grid-template-columns: 1fr !important;
            }

            .ufsc-ffst-admin .ufsc-ffst-table-wrap > table,
            .ufsc-ffst-admin .postbox > table.widefat {
                min-width: 760px;
                table-layout: auto;
            }

            .ufsc-ffst-admin .postbox {
                overflow-x: auto;
            }

            .ufsc-ffst-admin .postbox textarea {
                min-width: 0;
            }
        }
    </style>
    <?php
}
add_action( 'admin_head', 'ufsc_ffst_admin_layout_hotfix', 999 );
