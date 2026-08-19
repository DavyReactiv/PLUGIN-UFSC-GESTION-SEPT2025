<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Feature flags / runtime composition for UFSC Gestion.
 */

$ufsc_finalization_service = UFSC_CL_DIR . 'includes/core/class-ufsc-licence-finalization-service.php';
if ( file_exists( $ufsc_finalization_service ) ) {
    require_once $ufsc_finalization_service;
}

$ufsc_club_dashboard_hardening = dirname( __FILE__ ) . '/club-dashboard-hardening.php';
if ( file_exists( $ufsc_club_dashboard_hardening ) ) {
    require_once $ufsc_club_dashboard_hardening;
}

// Consolidated journey: presentation and affiliation journey.
$ufsc_club_journey = dirname( __FILE__ ) . '/club-journey.php';
if ( file_exists( $ufsc_club_journey ) ) {
    require_once $ufsc_club_journey;
}

// Structural routing/admin helpers. Its legacy after-the-fact finalizers are
// disabled by the canonical runtime below; the remaining archive/UI helpers stay active.
$ufsc_structural_workflow = dirname( __FILE__ ) . '/licence-workflow-structural.php';
if ( file_exists( $ufsc_structural_workflow ) ) {
    require_once $ufsc_structural_workflow;
}

$ufsc_finalization_runtime = dirname( __FILE__ ) . '/licence-finalization-runtime.php';
if ( file_exists( $ufsc_finalization_runtime ) ) {
    require_once $ufsc_finalization_runtime;
}

// Final UI cascade: one scoped presentation layer replaces the overlapping
// journey/structural/P0 styles without touching their server-side business logic.
$ufsc_portal_ui_cleanup = dirname( __FILE__ ) . '/portal-ui-cleanup.php';
if ( file_exists( $ufsc_portal_ui_cleanup ) ) {
    require_once $ufsc_portal_ui_cleanup;
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
    $intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) ) : '';
    if ( ! $intent && isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) );
    }
    return in_array( $intent, array( 'add_to_cart', 'submit_for_validation' ), true ) ? false : $required;
}
add_filter( 'ufsc_role_requires_honorability', 'ufsc_allow_cart_before_honorability_completion', 10, 3 );
