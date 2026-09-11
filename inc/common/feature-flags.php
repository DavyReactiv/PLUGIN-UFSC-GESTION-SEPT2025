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

// Compte Club presentation compatibility: keep the server-derived action and
// affiliation cards outside the nested logo upload form/column.
$ufsc_account_profile_injection_fix = dirname( __FILE__ ) . '/account-profile-injection-fix.php';
if ( file_exists( $ufsc_account_profile_injection_fix ) ) {
    require_once $ufsc_account_profile_injection_fix;
}

// Production schema compatibility: the traceability installer must confirm the
// live database schema before issuing ALTER TABLE after a deployment cache hit.
$ufsc_traceability_schema_compat = dirname( __FILE__ ) . '/production-traceability-schema-compat.php';
if ( file_exists( $ufsc_traceability_schema_compat ) ) {
    require_once $ufsc_traceability_schema_compat;
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

// Affiliation/WooCommerce state bridge: one pack per club and season, immediate
// pending state for bank-transfer orders, paid -> pending validation, plus an
// idempotent reconciliation pass for orders created during earlier deployments.
$ufsc_affiliation_order_state_bridge = dirname( __FILE__ ) . '/affiliation-order-state-bridge.php';
if ( file_exists( $ufsc_affiliation_order_state_bridge ) ) {
    require_once $ufsc_affiliation_order_state_bridge;
}

// New-club production hardening. Reuses the canonical annual state/bridge and
// honorability model to prevent checkout loops and duplicate affiliations while
// making the first-account -> club -> payment journey explicit.
$ufsc_new_club_onboarding_hardening = dirname( __FILE__ ) . '/new-club-onboarding-hardening.php';
if ( file_exists( $ufsc_new_club_onboarding_hardening ) ) {
    require_once $ufsc_new_club_onboarding_hardening;
}

// Presentation-only French labels for affiliation workflow statuses.
$ufsc_affiliation_status_labels_fr = dirname( __FILE__ ) . '/affiliation-status-labels-fr.php';
if ( file_exists( $ufsc_affiliation_status_labels_fr ) ) {
    require_once $ufsc_affiliation_status_labels_fr;
}

// Front signup compatibility: ordinary club applicants may choose any valid UFSC
// region for their first club, while regional back-office scopes remain enforced.
$ufsc_front_club_registration_scope_hotfix = dirname( __FILE__ ) . '/front-club-registration-scope-hotfix.php';
if ( file_exists( $ufsc_front_club_registration_scope_hotfix ) ) {
    require_once $ufsc_front_club_registration_scope_hotfix;
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

// Additive federation access layer: existing WordPress users can be assigned a
// strict read-only UFSC profile limited to one/many regions or to all regions.
// It does not alter licence, affiliation, quota, WooCommerce or season flows.
$ufsc_readonly_multiregion_admin = dirname( __FILE__ ) . '/readonly-multiregion-admin.php';
if ( file_exists( $ufsc_readonly_multiregion_admin ) ) {
    require_once $ufsc_readonly_multiregion_admin;
}

// Additional hardening for the read-only federation access layer: regional
// dashboard scoping, direct-object scope checks and non-accounting UI cleanup.
$ufsc_readonly_multiregion_admin_hardening = dirname( __FILE__ ) . '/readonly-multiregion-admin-hardening.php';
if ( file_exists( $ufsc_readonly_multiregion_admin_hardening ) ) {
    require_once $ufsc_readonly_multiregion_admin_hardening;
}

// Clear user-facing messages for forbidden read-only routes. Hidden menus stay
// hidden; direct URLs/bookmarks receive an explicit French "droits nécessaires"
// explanation before the generic guard runs.
$ufsc_readonly_access_denied_messages = dirname( __FILE__ ) . '/readonly-access-denied-messages.php';
if ( file_exists( $ufsc_readonly_access_denied_messages ) ) {
    require_once $ufsc_readonly_access_denied_messages;
}

// Presentation-only compatibility: WordPress keeps both callbacks when a submenu
// slug is re-registered. Remove only the legacy renderer so the access page is
// displayed once while retaining the hardened overview callback.
$ufsc_readonly_access_admin_page_dedup = dirname( __FILE__ ) . '/readonly-access-admin-page-dedup.php';
if ( file_exists( $ufsc_readonly_access_admin_page_dedup ) ) {
    require_once $ufsc_readonly_access_admin_page_dedup;
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

// Production licence portal hotfix: included-quota handoff, FFST form cleanup,
// contextual help and cache-safe logout. No schema/data migration.
$ufsc_licence_portal_production_hotfix = dirname( __FILE__ ) . '/licence-portal-production-hotfix.php';
if ( file_exists( $ufsc_licence_portal_production_hotfix ) ) {
    require_once $ufsc_licence_portal_production_hotfix;
}

// P0: a payable licence may leave admin-post.php only when its native
// WooCommerce cart line is really present and the session has been persisted.
$ufsc_paid_licence_cart_postcondition = dirname( __FILE__ ) . '/paid-licence-cart-postcondition.php';
if ( file_exists( $ufsc_paid_licence_cart_postcondition ) ) {
    require_once $ufsc_paid_licence_cart_postcondition;
}

// P0 renewal recovery: reuse an existing annual draft, append paid renewals to
// the native cart and convert runtime failures into recoverable club messages.
$ufsc_renewal_cart_recovery = dirname( __FILE__ ) . '/renewal-cart-recovery.php';
if ( file_exists( $ufsc_renewal_cart_recovery ) ) {
    require_once $ufsc_renewal_cart_recovery;
}

// P0 debug-confirmed Woo cart integrity guard: preserve valid lines, repair a
// malformed renewal row before totals and use Woo's supported session removal hook.
$ufsc_renewal_cart_integrity = dirname( __FILE__ ) . '/renewal-cart-integrity.php';
if ( file_exists( $ufsc_renewal_cart_integrity ) ) {
    require_once $ufsc_renewal_cart_integrity;
}

// P0 retry handoff: append payable renewal targets directly to the native Woo
// cart and persist exactly once after all selected renewals.
$ufsc_renewal_native_cart_handoff = dirname( __FILE__ ) . '/renewal-native-cart-handoff.php';
if ( file_exists( $ufsc_renewal_native_cart_handoff ) ) {
    require_once $ufsc_renewal_native_cart_handoff;
}

// P0 reopened renewal draft: route the unified edit form through the native
// renewal cart handoff without touching ordinary current-season licence edits.
$ufsc_renewal_draft_cart_handoff = dirname( __FILE__ ) . '/renewal-draft-cart-handoff.php';
if ( file_exists( $ufsc_renewal_draft_cart_handoff ) ) {
    require_once $ufsc_renewal_draft_cart_handoff;
}

// Admin-only FFST layout repair. Presentation only; no business/data mutation.
$ufsc_ffst_admin_layout_hotfix = dirname( __FILE__ ) . '/ffst-admin-layout-hotfix.php';
if ( file_exists( $ufsc_ffst_admin_layout_hotfix ) ) {
    require_once $ufsc_ffst_admin_layout_hotfix;
}

// Read-only statistics presentation: replaces misleading empty legacy charts
// with a compact current-season view built from every dossier entered by the club.
$ufsc_club_stats_dashboard_v2 = dirname( __FILE__ ) . '/club-stats-dashboard-v2.php';
if ( file_exists( $ufsc_club_stats_dashboard_v2 ) ) {
    require_once $ufsc_club_stats_dashboard_v2;
}

// Compact contextual guidance across club and UFSC admin screens. Presentation
// only: no licence, affiliation, quota, payment or historical data mutation.
$ufsc_contextual_help = dirname( __FILE__ ) . '/contextual-help.php';
if ( file_exists( $ufsc_contextual_help ) ) {
    require_once $ufsc_contextual_help;
}

// Bank transfer checkout information: display the official UFSC Crédit Mutuel
// account details on checkout, confirmation and customer e-mails only for BACS.
$ufsc_bacs_bank_details = dirname( __FILE__ ) . '/bacs-bank-details.php';
if ( file_exists( $ufsc_bacs_bank_details ) ) {
    require_once $ufsc_bacs_bank_details;
}
