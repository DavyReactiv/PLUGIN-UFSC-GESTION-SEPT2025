<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Feature flags for UFSC Gestion.
 */

$ufsc_club_dashboard_hardening = dirname( __FILE__ ) . '/club-dashboard-hardening.php';
if ( file_exists( $ufsc_club_dashboard_hardening ) ) {
    require_once $ufsc_club_dashboard_hardening;
}

$ufsc_p0_quota_cart_kpi = dirname( __FILE__ ) . '/p0-quota-cart-kpi.php';
if ( file_exists( $ufsc_p0_quota_cart_kpi ) ) {
    require_once $ufsc_p0_quota_cart_kpi;
}

$ufsc_p0_quota_ui = dirname( __FILE__ ) . '/p0-quota-ui.php';
if ( file_exists( $ufsc_p0_quota_ui ) ) {
    require_once $ufsc_p0_quota_ui;
}

$ufsc_p0_dev_recipe_v2 = dirname( __FILE__ ) . '/p0-dev-recipe-v2.php';
if ( file_exists( $ufsc_p0_dev_recipe_v2 ) ) {
    require_once $ufsc_p0_dev_recipe_v2;
}

$ufsc_p0_paid_cart_handoff = dirname( __FILE__ ) . '/p0-paid-cart-handoff.php';
if ( file_exists( $ufsc_p0_paid_cart_handoff ) ) {
    require_once $ufsc_p0_paid_cart_handoff;
}

$ufsc_p0_dev_recipe_v3 = dirname( __FILE__ ) . '/p0-dev-recipe-v3.php';
if ( file_exists( $ufsc_p0_dev_recipe_v3 ) ) {
    require_once $ufsc_p0_dev_recipe_v3;
}

function ufsc_quotas_enabled() {
    return (bool) apply_filters( 'ufsc_quotas_enabled', true );
}

function ufsc_handle_add_licence_through_unified_cart_flow() {
    if ( ! class_exists( 'UFSC_Unified_Handlers' ) ) {
        wp_die( __( 'Le gestionnaire de licences UFSC est indisponible.', 'ufsc-clubs' ) );
    }
    if ( ! current_user_can( 'read' ) ) {
        wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
    }
    check_admin_referer( 'ufsc_add_licence' );
    $_POST['_wpnonce'] = wp_create_nonce( 'ufsc_save_licence' );
    UFSC_Unified_Handlers::handle_save_licence();
}

function ufsc_fix_new_licence_cart_route() {
    if ( ! class_exists( 'UFSC_Unified_Handlers' ) ) { return; }
    remove_action( 'admin_post_ufsc_add_licence', array( 'UFSC_Unified_Handlers', 'handle_add_licence' ) );
    remove_action( 'admin_post_nopriv_ufsc_add_licence', array( 'UFSC_Unified_Handlers', 'handle_add_licence' ) );
    add_action( 'admin_post_ufsc_add_licence', 'ufsc_handle_add_licence_through_unified_cart_flow' );
    add_action( 'admin_post_nopriv_ufsc_add_licence', 'ufsc_handle_add_licence_through_unified_cart_flow' );
}
add_action( 'init', 'ufsc_fix_new_licence_cart_route', 20 );

function ufsc_allow_cart_before_honorability_completion( $required, $normalized_role, $raw_role ) {
    unset( $normalized_role, $raw_role );
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) { return $required; }
    $intent = isset( $_POST['ufsc_submit_action'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) ) : '';
    return 'add_to_cart' === $intent ? false : $required;
}
add_filter( 'ufsc_role_requires_honorability', 'ufsc_allow_cart_before_honorability_completion', 10, 3 );
