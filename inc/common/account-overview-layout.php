<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Final presentation-only repair for Compte Club overview/profile pages.
 * This file deliberately contains no business, licence, quota or payment logic.
 */
function ufsc_account_overview_layout_asset() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) {
        return;
    }

    $css = 'assets/css/ufsc-account-overview-fix.css';
    $version = function_exists( 'ufsc_asset_version' )
        ? ufsc_asset_version( $css )
        : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );

    wp_enqueue_style(
        'ufsc-account-overview-fix',
        UFSC_CL_URL . $css,
        array( 'ufsc-portal-clean' ),
        $version
    );
}
add_action( 'wp_enqueue_scripts', 'ufsc_account_overview_layout_asset', 1001 );
