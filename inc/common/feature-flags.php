<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Feature flags for UFSC Gestion.
 */

/**
 * UFSC PATCH: Quotas disabled by default (feature flag).
 *
 * @return bool
 */
function ufsc_quotas_enabled() {
    return (bool) apply_filters( 'ufsc_quotas_enabled', false );
}

/**
 * Route the legacy "add licence" endpoint through the canonical licence workflow.
 *
 * The historical handle_add_licence() callback saves the licence and redirects,
 * but does not execute the WooCommerce allocation/cart branch. The canonical
 * handle_save_licence() -> process_licence_request() flow already owns draft,
 * included-pack and paid-cart behaviour, so reuse it instead of duplicating the
 * checkout logic.
 */
function ufsc_handle_add_licence_through_unified_cart_flow() {
    if ( ! class_exists( 'UFSC_Unified_Handlers' ) ) {
        wp_die( __( 'Le gestionnaire de licences UFSC est indisponible.', 'ufsc-clubs' ) );
    }

    if ( ! current_user_can( 'read' ) ) {
        wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    check_admin_referer( 'ufsc_add_licence' );

    // handle_save_licence() verifies its own canonical nonce before entering
    // process_licence_request(). Re-issue that nonce server-side only after the
    // original add-licence nonce has been successfully verified above.
    $_POST['_wpnonce'] = wp_create_nonce( 'ufsc_save_licence' );

    UFSC_Unified_Handlers::handle_save_licence();
}

/**
 * Replace only the legacy creation endpoint after UFSC_Unified_Handlers::init().
 * Renewal handlers are intentionally untouched.
 */
function ufsc_fix_new_licence_cart_route() {
    if ( ! class_exists( 'UFSC_Unified_Handlers' ) ) {
        return;
    }

    remove_action( 'admin_post_ufsc_add_licence', array( 'UFSC_Unified_Handlers', 'handle_add_licence' ) );
    remove_action( 'admin_post_nopriv_ufsc_add_licence', array( 'UFSC_Unified_Handlers', 'handle_add_licence' ) );

    add_action( 'admin_post_ufsc_add_licence', 'ufsc_handle_add_licence_through_unified_cart_flow' );
    add_action( 'admin_post_nopriv_ufsc_add_licence', 'ufsc_handle_add_licence_through_unified_cart_flow' );
}
add_action( 'init', 'ufsc_fix_new_licence_cart_route', 20 );

/**
 * Honorability remains mandatory for final licence validation, but it must not
 * block WooCommerce checkout. The role/document requirement is evaluated again
 * on later validation requests by ufsc_can_validate_licence().
 */
function ufsc_allow_cart_before_honorability_completion( $required, $normalized_role, $raw_role ) {
    unset( $normalized_role, $raw_role );

    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return $required;
    }

    $intent = isset( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';

    if ( 'add_to_cart' === $intent ) {
        return false;
    }

    return $required;
}
add_filter( 'ufsc_role_requires_honorability', 'ufsc_allow_cart_before_honorability_completion', 10, 3 );
