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

// Normalise the front renewal submitter before the canonical finalization runtime
// inspects POST. The real admin-post handler still performs all security checks.
$ufsc_renewal_intent_compat = dirname( __FILE__ ) . '/renewal-intent-compat.php';
if ( file_exists( $ufsc_renewal_intent_compat ) ) {
    require_once $ufsc_renewal_intent_compat;
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

// Final Compte Club width repair. Presentation only; intentionally isolated from
// affiliation, licence, quota and WooCommerce business logic.
$ufsc_account_overview_layout = dirname( __FILE__ ) . '/account-overview-layout.php';
if ( file_exists( $ufsc_account_overview_layout ) ) {
    require_once $ufsc_account_overview_layout;
}

// Production boundary: deterministic affiliation/licence finalisation while
// keeping all historical rows and non-final assistant steps untouched.
$ufsc_production_readiness = dirname( __FILE__ ) . '/production-readiness-hotfix.php';
if ( file_exists( $ufsc_production_readiness ) ) {
    require_once $ufsc_production_readiness;
}

$ufsc_production_payment_boundary = dirname( __FILE__ ) . '/production-payment-boundary.php';
if ( file_exists( $ufsc_production_payment_boundary ) ) {
    require_once $ufsc_production_payment_boundary;
}

// Shared licence navigation, visible notifications and responsive tables for
// member/admin views. This layer does not mutate licence or affiliation data.
$ufsc_production_licence_ux = dirname( __FILE__ ) . '/production-licence-ux.php';
if ( file_exists( $ufsc_production_licence_ux ) ) {
    require_once $ufsc_production_licence_ux;
}

// DEV acceptance compatibility: keep annual renewals from being labelled as
// identity duplicates across seasons and return WooCommerce settings saves to
// the canonical registered UFSC admin page.
$ufsc_production_admin_compat = dirname( __FILE__ ) . '/production-admin-compat.php';
if ( file_exists( $ufsc_production_admin_compat ) ) {
    require_once $ufsc_production_admin_compat;
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
